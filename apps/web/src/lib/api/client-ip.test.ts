import { describe, expect, it } from "vitest";
import { forwardedForHeaders } from "@/lib/api/client-ip";

/**
 * This is the fix for B1. Every write the browser makes is relayed to the API by
 * a route handler, so if the visitor's address is not forwarded the API sees one
 * address for the whole platform and `Limit::perMinute(5)->by($request->ip())`
 * on sign-in becomes a site-wide cap of five logins per minute.
 */
function requestWith(headers: Record<string, string>): Request {
  return new Request("https://example.test/api/session/login", { headers });
}

describe("forwardedForHeaders", () => {
  it("forwards the visitor's address so the API can meter per client", () => {
    expect(forwardedForHeaders(requestWith({ "x-forwarded-for": "203.0.113.7" }))).toEqual({
      "X-Forwarded-For": "203.0.113.7",
    });
  });

  it("takes the original client from a proxy chain, not the nearest hop", () => {
    expect(
      forwardedForHeaders(
        requestWith({ "x-forwarded-for": "203.0.113.7, 70.41.3.18, 150.172.238.178" }),
      ),
    ).toEqual({ "X-Forwarded-For": "203.0.113.7" });
  });

  it("tolerates the whitespace real proxies emit", () => {
    expect(
      forwardedForHeaders(requestWith({ "x-forwarded-for": "  203.0.113.7 , 70.41.3.18" })),
    ).toEqual({ "X-Forwarded-For": "203.0.113.7" });
  });

  it("falls back to x-real-ip when no chain is present", () => {
    expect(forwardedForHeaders(requestWith({ "x-real-ip": "198.51.100.4" }))).toEqual({
      "X-Forwarded-For": "198.51.100.4",
    });
  });

  it("sends nothing when there is no edge to learn the address from", () => {
    // Local `next start` has no edge. Forwarding an empty or invented value would
    // be worse than letting the API fall back to the socket address.
    expect(forwardedForHeaders(requestWith({}))).toEqual({});
    expect(forwardedForHeaders(requestWith({ "x-forwarded-for": "" }))).toEqual({});
    expect(forwardedForHeaders(requestWith({ "x-forwarded-for": "  " }))).toEqual({});
  });
});
