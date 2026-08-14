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
    expect(
      forwardedForHeaders(requestWith({ "x-forwarded-for": "203.0.113.7" })),
    ).toEqual({
      "X-Forwarded-For": "203.0.113.7",
    });
  });

  it("takes the address the last hop saw, not a client-supplied one", () => {
    // X-Forwarded-For is appended to by each hop, so on an appending edge a
    // caller's own header lands on the LEFT. Trusting the leftmost entry would
    // let anyone rotate a value and reset their own rate-limit bucket.
    expect(
      forwardedForHeaders(
        requestWith({ "x-forwarded-for": "1.2.3.4, 203.0.113.7" }),
      ),
    ).toEqual({ "X-Forwarded-For": "203.0.113.7" });
  });

  it("prefers the single-value headers an edge writes itself", () => {
    // A client can contribute to x-forwarded-for; it cannot to these.
    expect(
      forwardedForHeaders(
        requestWith({
          "x-forwarded-for": "1.2.3.4",
          "x-real-ip": "203.0.113.7",
        }),
      ),
    ).toEqual({ "X-Forwarded-For": "203.0.113.7" });

    expect(
      forwardedForHeaders(
        requestWith({
          "x-forwarded-for": "1.2.3.4",
          "cf-connecting-ip": "203.0.113.9",
          "x-real-ip": "198.51.100.1",
        }),
      ),
    ).toEqual({ "X-Forwarded-For": "203.0.113.9" });
  });

  it("behaves identically on an overwriting edge, where the chain is one entry", () => {
    expect(
      forwardedForHeaders(requestWith({ "x-forwarded-for": "203.0.113.7" })),
    ).toEqual({
      "X-Forwarded-For": "203.0.113.7",
    });
  });

  it("tolerates the whitespace real proxies emit", () => {
    expect(
      forwardedForHeaders(
        requestWith({ "x-forwarded-for": "  1.2.3.4 , 203.0.113.7  " }),
      ),
    ).toEqual({ "X-Forwarded-For": "203.0.113.7" });
  });

  it("falls back to x-real-ip when no chain is present", () => {
    expect(
      forwardedForHeaders(requestWith({ "x-real-ip": "198.51.100.4" })),
    ).toEqual({
      "X-Forwarded-For": "198.51.100.4",
    });
  });

  it("sends nothing when there is no edge to learn the address from", () => {
    // Local `next start` has no edge. Forwarding an empty or invented value would
    // be worse than letting the API fall back to the socket address.
    expect(forwardedForHeaders(requestWith({}))).toEqual({});
    expect(forwardedForHeaders(requestWith({ "x-forwarded-for": "" }))).toEqual(
      {},
    );
    expect(
      forwardedForHeaders(requestWith({ "x-forwarded-for": "  " })),
    ).toEqual({});
  });
});
