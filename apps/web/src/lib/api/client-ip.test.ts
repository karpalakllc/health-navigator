import { afterEach, describe, expect, it, vi } from "vitest";

// Next resolves "server-only" at compile time; plain vitest has no such module.
vi.mock("server-only", () => ({}));

const { clientIpFrom, forwardedForHeaders, webTierHeaders } =
  await import("@/lib/api/client-ip");

/**
 * Every server-side call to the API comes from this server's address, so if
 * the visitor is not identified the API meters the whole site as one client
 * (B1 for sign-in; the shared SSR bucket for anonymous page views).
 */
const SECRET = "s".repeat(32);

function requestWith(headers: Record<string, string>): Request {
  return new Request("https://example.test/api/session/login", { headers });
}

function ipFrom(headers: Record<string, string>): string | undefined {
  return clientIpFrom(new Headers(headers));
}

afterEach(() => {
  delete process.env.CLIENT_IP_HEADER;
  delete process.env.WEB_TIER_SECRET;
});

describe("clientIpFrom", () => {
  it("takes the address the last hop saw, not a caller-supplied one", () => {
    // Each hop appends, so a value the caller sent lands on the LEFT. Trusting
    // the leftmost entry would let anyone rotate it and reset their own bucket.
    expect(ipFrom({ "x-forwarded-for": "1.2.3.4, 203.0.113.7" })).toBe(
      "203.0.113.7",
    );
  });

  it("ignores x-real-ip and cf-connecting-ip unless they are configured", () => {
    // Behind an edge that does not write them, these are caller-supplied. The
    // deployment has to opt in, because this code cannot tell the difference.
    expect(
      ipFrom({
        "x-forwarded-for": "1.2.3.4, 203.0.113.7",
        "x-real-ip": "9.9.9.9",
        "cf-connecting-ip": "8.8.8.8",
      }),
    ).toBe("203.0.113.7");
  });

  it("uses the configured header when the deployment names one", () => {
    process.env.CLIENT_IP_HEADER = "CF-Connecting-IP";

    expect(
      ipFrom({
        "x-forwarded-for": "1.2.3.4",
        "cf-connecting-ip": "203.0.113.7",
      }),
    ).toBe("203.0.113.7");
  });

  it("falls back to the chain when the configured header is absent", () => {
    process.env.CLIENT_IP_HEADER = "cf-connecting-ip";

    expect(ipFrom({ "x-forwarded-for": "203.0.113.7" })).toBe("203.0.113.7");
  });

  it("forwards nothing when the deployment says no header can be trusted", () => {
    // `next start` exposed with no proxy in front: the caller writes the whole
    // x-forwarded-for, so there is no trustworthy address to forward.
    process.env.CLIENT_IP_HEADER = "none";

    expect(ipFrom({ "x-forwarded-for": "203.0.113.7" })).toBeUndefined();
  });

  it("tolerates the whitespace real proxies emit", () => {
    expect(ipFrom({ "x-forwarded-for": "  1.2.3.4 , 203.0.113.7  " })).toBe(
      "203.0.113.7",
    );
  });

  it("strips a source port some proxies append", () => {
    expect(ipFrom({ "x-forwarded-for": "203.0.113.7:51234" })).toBe(
      "203.0.113.7",
    );
    expect(ipFrom({ "x-forwarded-for": "[2001:db8::7]:443" })).toBe(
      "2001:db8::7",
    );
    expect(ipFrom({ "x-forwarded-for": "2001:db8::7" })).toBe("2001:db8::7");
  });

  it("drops a value that is not an address", () => {
    expect(ipFrom({ "x-forwarded-for": "unknown" })).toBeUndefined();
    expect(ipFrom({ "x-forwarded-for": "203.0.113.7; DROP" })).toBeUndefined();
  });

  it("finds nothing when there is no edge to learn the address from", () => {
    expect(ipFrom({})).toBeUndefined();
    expect(ipFrom({ "x-forwarded-for": "" })).toBeUndefined();
    expect(ipFrom({ "x-forwarded-for": "  " })).toBeUndefined();
  });
});

describe("webTierHeaders", () => {
  it("vouches for the visitor with the secret when one is configured", () => {
    process.env.WEB_TIER_SECRET = SECRET;

    expect(webTierHeaders("203.0.113.7")).toEqual({
      "X-Web-Tier-Auth": SECRET,
      "X-Client-IP": "203.0.113.7",
    });
  });

  it("still authenticates when no visitor address is known", () => {
    process.env.WEB_TIER_SECRET = SECRET;

    expect(webTierHeaders(undefined)).toEqual({ "X-Web-Tier-Auth": SECRET });
  });

  it("never sends an auth header without a secret", () => {
    // Pre-secret deployments keep the old single-entry X-Forwarded-For.
    expect(webTierHeaders("203.0.113.7")).toEqual({
      "X-Forwarded-For": "203.0.113.7",
    });
    expect(webTierHeaders(undefined)).toEqual({});

    process.env.WEB_TIER_SECRET = "   ";
    expect(webTierHeaders("203.0.113.7")).not.toHaveProperty("X-Web-Tier-Auth");
  });

  it("does not send a secret the API would refuse as too short", () => {
    process.env.WEB_TIER_SECRET = "s".repeat(31);

    expect(webTierHeaders("203.0.113.7")).toEqual({
      "X-Forwarded-For": "203.0.113.7",
    });
  });
});

describe("forwardedForHeaders", () => {
  it("builds the route-handler headers from the incoming request", () => {
    process.env.WEB_TIER_SECRET = SECRET;

    expect(
      forwardedForHeaders(
        requestWith({ "x-forwarded-for": "1.2.3.4, 203.0.113.7" }),
      ),
    ).toEqual({ "X-Web-Tier-Auth": SECRET, "X-Client-IP": "203.0.113.7" });
  });
});
