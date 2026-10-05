import { describe, expect, it } from "vitest";
import { scrubEvent, scrubUrl } from "@/lib/sentry-scrub";

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
