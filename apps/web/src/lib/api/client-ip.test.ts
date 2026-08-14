import { afterEach, describe, expect, it } from "vitest";
import { forwardedForHeaders } from "@/lib/api/client-ip";

/**
 * This is the fix for B1. Every write the browser makes is relayed to the API by
 * a route handler, so if the visitor's address is not forwarded the API sees one
 * address for the whole platform and the sign-in limit becomes a site-wide cap.
 */
function requestWith(headers: Record<string, string>): Request {
  return new Request("https://example.test/api/session/login", { headers });
}

afterEach(() => {
  delete process.env.CLIENT_IP_HEADER;
});

describe("forwardedForHeaders", () => {
  it("forwards the visitor's address so the API can meter per client", () => {
    expect(
      forwardedForHeaders(requestWith({ "x-forwarded-for": "203.0.113.7" })),
    ).toEqual({ "X-Forwarded-For": "203.0.113.7" });
  });

  it("takes the address the last hop saw, not a caller-supplied one", () => {
    // Each hop appends, so a value the caller sent lands on the LEFT. Trusting
    // the leftmost entry would let anyone rotate it and reset their own bucket.
    expect(
      forwardedForHeaders(
        requestWith({ "x-forwarded-for": "1.2.3.4, 203.0.113.7" }),
      ),
    ).toEqual({ "X-Forwarded-For": "203.0.113.7" });
  });

  it("ignores x-real-ip and cf-connecting-ip unless they are configured", () => {
    // Behind an edge that does not write them, these are caller-supplied. The
    // deployment has to opt in, because this code cannot tell the difference.
    expect(
      forwardedForHeaders(
        requestWith({
          "x-forwarded-for": "1.2.3.4, 203.0.113.7",
          "x-real-ip": "9.9.9.9",
          "cf-connecting-ip": "8.8.8.8",
        }),
      ),
    ).toEqual({ "X-Forwarded-For": "203.0.113.7" });
  });

  it("uses the configured header when the deployment names one", () => {
    process.env.CLIENT_IP_HEADER = "cf-connecting-ip";

    expect(
      forwardedForHeaders(
        requestWith({
          "x-forwarded-for": "1.2.3.4",
          "cf-connecting-ip": "203.0.113.7",
        }),
      ),
    ).toEqual({ "X-Forwarded-For": "203.0.113.7" });
  });

  it("falls back to the chain when the configured header is absent", () => {
    process.env.CLIENT_IP_HEADER = "cf-connecting-ip";

    expect(
      forwardedForHeaders(requestWith({ "x-forwarded-for": "203.0.113.7" })),
    ).toEqual({ "X-Forwarded-For": "203.0.113.7" });
  });

  it("tolerates the whitespace real proxies emit", () => {
    expect(
      forwardedForHeaders(
        requestWith({ "x-forwarded-for": "  1.2.3.4 , 203.0.113.7  " }),
      ),
    ).toEqual({ "X-Forwarded-For": "203.0.113.7" });
  });

  it("sends nothing when there is no edge to learn the address from", () => {
    expect(forwardedForHeaders(requestWith({}))).toEqual({});
    expect(forwardedForHeaders(requestWith({ "x-forwarded-for": "" }))).toEqual(
      {},
    );
    expect(
      forwardedForHeaders(requestWith({ "x-forwarded-for": "  " })),
    ).toEqual({});
  });
});
