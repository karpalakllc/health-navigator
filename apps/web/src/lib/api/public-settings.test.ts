import { describe, expect, it } from "vitest";
import {
  publicSettingsDefaults,
  resolvePublicSettings,
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
