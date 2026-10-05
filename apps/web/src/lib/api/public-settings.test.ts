import { describe, expect, it } from "vitest";
import {
  isModuleOn,
  publicSettingsDefaults,
  resolvePublicSettings,
  SettingsUnavailableError,
  shouldAbortSitemap,
} from "@/lib/api/public-settings";

const MODULES = [
  "public_forum",
  "public_guidance",
  "public_pharmacies",
  "public_products",
] as const;

describe("resolvePublicSettings", () => {
  it("uses the API's values, filling gaps from the defaults", () => {
    const settings = resolvePublicSettings({
      kind: "ok",
      data: { public_forum: false, maintenance_mode: true },
    });

    expect(settings.public_forum).toBe(false);
    expect(settings.maintenance_mode).toBe(true);
    expect(settings.copyright_name).toBe(publicSettingsDefaults.copyright_name);
  });

  it("shows the maintenance page when the API answers 503", () => {
    const settings = resolvePublicSettings({
      kind: "http-error",
      status: 503,
    });

    // The one failure that must not fail open into a normal-looking site.
    expect(settings.maintenance_mode).toBe(true);
  });

  it.each([
    { kind: "http-error" as const, status: 500 },
    { kind: "http-error" as const, status: 429 },
    { kind: "network-error" as const },
  ])("keeps the site up but turns every module off on $kind $status", (o) => {
    const settings = resolvePublicSettings(o);

    expect(settings.maintenance_mode).toBe(false);
    for (const flag of MODULES) {
      // The defaults have the forum on; an outage must not advertise it.
      expect(settings[flag]).toBe(false);
    }
  });
});

describe("degraded settings", () => {
  it("marks only a fallback as degraded", () => {
    expect(resolvePublicSettings({ kind: "ok", data: {} }).degraded).toBe(
      false,
    );
    expect(
      resolvePublicSettings({ kind: "ok", data: { degraded: true } }).degraded,
    ).toBe(false);
    expect(resolvePublicSettings({ kind: "network-error" }).degraded).toBe(
      true,
    );
    expect(
      resolvePublicSettings({ kind: "http-error", status: 500 }).degraded,
    ).toBe(true);
  });
});

describe("isModuleOn", () => {
  const read = (data: Parameters<typeof resolvePublicSettings>[0]) =>
    resolvePublicSettings(data);

  it("is true for a module the admin left on", () => {
    expect(
      isModuleOn(
        read({ kind: "ok", data: { public_forum: true } }),
        "public_forum",
      ),
    ).toBe(true);
  });

  it("is false (404 / not-available page) when the admin switched it off", () => {
    expect(
      isModuleOn(
        read({ kind: "ok", data: { public_forum: false } }),
        "public_forum",
      ),
    ).toBe(false);
  });

  it.each([
    "public_forum",
    "public_pharmacies",
    "public_products",
    "public_guidance",
  ] as const)(
    "throws instead of claiming %s is off when settings could not be read",
    (flag) => {
      // A 404 here during an API blip would get real URLs deindexed.
      expect(() => isModuleOn(read({ kind: "network-error" }), flag)).toThrow(
        SettingsUnavailableError,
      );
      expect(() =>
        isModuleOn(read({ kind: "http-error", status: 502 }), flag),
      ).toThrow(SettingsUnavailableError);
    },
  );
});

describe("shouldAbortSitemap", () => {
  it("keeps the previous sitemap at runtime when settings are degraded", () => {
    expect(shouldAbortSitemap({ degraded: true }, undefined)).toBe(true);
    expect(shouldAbortSitemap({ degraded: false }, undefined)).toBe(false);
  });

  it("never fails `next build` over an unreachable API", () => {
    expect(
      shouldAbortSitemap({ degraded: true }, "phase-production-build"),
    ).toBe(false);
  });
});
