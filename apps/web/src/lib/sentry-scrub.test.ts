import { describe, expect, it } from "vitest";
import {
  isSettingsUnavailable,
  SettingsUnavailableError,
} from "@/lib/api/public-settings";
import { beforeSendFilter, scrubEvent, scrubUrl } from "@/lib/sentry-scrub";

/**
 * A reset link is /reset-password?token=…&email=…, and beforeSend used to filter
 * only request bodies — so any error on that page shipped the live token to
 * Sentry in the event URL and its breadcrumbs.
 */
const RESET_URL =
  "https://zdravje.test/reset-password?token=secret-token&email=ana%40example.mk";

function hasSecret(value: unknown): boolean {
  const text = JSON.stringify(value);
  return text.includes("secret-token") || text.includes("ana%40example.mk");
}

describe("scrubUrl", () => {
  it("filters token and email from an absolute URL", () => {
    const scrubbed = scrubUrl(RESET_URL);
    expect(hasSecret(scrubbed)).toBe(false);
    expect(scrubbed.startsWith("https://zdravje.test/reset-password?")).toBe(
      true,
    );
  });

  it("keeps a relative URL relative", () => {
    const scrubbed = scrubUrl("/reset-password?token=secret-token&page=2#x");
    expect(hasSecret(scrubbed)).toBe(false);
    expect(scrubbed.startsWith("/reset-password?")).toBe(true);
    expect(scrubbed).toContain("page=2");
    expect(scrubbed.endsWith("#x")).toBe(true);
  });

  it("returns URLs without credentials untouched", () => {
    expect(scrubUrl("/doctors?page=2")).toBe("/doctors?page=2");
    expect(scrubUrl("not a url ::")).toBe("not a url ::");
  });
});

describe("scrubEvent", () => {
  it("filters the request URL, query string, Referer and body", () => {
    const event = scrubEvent({
      request: {
        url: RESET_URL,
        query_string: "token=secret-token&email=ana%40example.mk",
        headers: { Referer: RESET_URL, "User-Agent": "x" },
        data: { password: "hunter2", token: "secret-token", rating: 5 },
      },
    });

    expect(hasSecret(event)).toBe(false);
    expect(JSON.stringify(event)).not.toContain("hunter2");
    expect(event.request?.headers?.["User-Agent"]).toBe("x");
    expect((event.request?.data as Record<string, unknown>).rating).toBe(5);
  });

  it("filters object and tuple query strings", () => {
    expect(
      hasSecret(
        scrubEvent({ request: { query_string: { token: "secret-token" } } }),
      ),
    ).toBe(false);
    expect(
      hasSecret(
        scrubEvent({ request: { query_string: [["token", "secret-token"]] } }),
      ),
    ).toBe(false);
  });

  it("filters navigation and fetch breadcrumbs", () => {
    const event = scrubEvent({
      breadcrumbs: [
        { data: { from: RESET_URL, to: "/reset-password/new" } },
        { data: { url: `/api/x?token=secret-token`, method: "POST" } },
        {},
      ],
    });

    expect(hasSecret(event)).toBe(false);
    expect(event.breadcrumbs?.[0].data?.to).toBe("/reset-password/new");
  });
});

/**
 * isModuleOn() throws SettingsUnavailableError on every page view during a
 * settings outage. settings.ts already reports the outage once a minute; the
 * per-view errors from onRequestError and the error boundary are noise that
 * defeats that throttle.
 */
describe("beforeSendFilter", () => {
  it("drops a server event for SettingsUnavailableError", () => {
    const event = {
      exception: {
        values: [
          {
            type: "SettingsUnavailableError",
            value: "Public settings unavailable; module state unknown",
          },
        ],
      },
    };

    expect(beforeSendFilter(event)).toBeNull();
    expect(
      beforeSendFilter(
        {},
        { originalException: new SettingsUnavailableError() },
      ),
    ).toBeNull();
  });

  it("keeps and scrubs every other event", () => {
    const event = {
      exception: { values: [{ type: "TypeError", value: "x is undefined" }] },
      request: { url: RESET_URL },
    };

    const result = beforeSendFilter(event, {
      originalException: new TypeError("x is undefined"),
    });

    expect(result).not.toBeNull();
    expect(hasSecret(result)).toBe(false);
  });
});

describe("isSettingsUnavailable", () => {
  it("recognises the error as the client boundary receives it in production", () => {
    // Next masks the message and the name of a server error before it reaches
    // the client, but keeps a digest the error already carried.
    const masked = Object.assign(
      new Error("An error occurred in the Server Components render."),
      { digest: new SettingsUnavailableError().digest },
    );

    expect(isSettingsUnavailable(masked)).toBe(true);
    expect(isSettingsUnavailable(new SettingsUnavailableError())).toBe(true);
  });

  it("does not match other errors, including other digests", () => {
    expect(isSettingsUnavailable(new Error("boom"))).toBe(false);
    expect(
      isSettingsUnavailable(
        Object.assign(new Error("masked"), { digest: "123" }),
      ),
    ).toBe(false);
    expect(isSettingsUnavailable(undefined)).toBe(false);
  });
});
