import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { GET } from "@/app/api/altcha/challenge/route";

/**
 * /api/altcha/challenge relays a fresh challenge from the API as plain JSON
 * (the shape the widget reads), never cached, and only to this site's own
 * pages.
 */
const SITE = "https://zdravje.test";
const fetchMock = vi.fn();
const CHALLENGE = {
  parameters: { algorithm: "PBKDF2/SHA-256", cost: 1000, nonce: "ab" },
  signature: "cd",
};

beforeEach(() => {
  vi.stubEnv("NEXT_PUBLIC_SITE_URL", SITE);
  vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.test");
  vi.stubGlobal("fetch", fetchMock);
  fetchMock.mockResolvedValue(
    new Response(JSON.stringify(CHALLENGE), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    }),
  );
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

function get(headers: Record<string, string>): Request {
  return new Request(`${SITE}/api/altcha/challenge`, { headers });
}

describe("GET /api/altcha/challenge", () => {
  it("relays the API's challenge unchanged and uncached", async () => {
    const response = await GET(get({ "sec-fetch-site": "same-origin" }));

    expect(response.status).toBe(200);
    expect(response.headers.get("cache-control")).toBe("no-store");
    expect(await response.json()).toEqual(CHALLENGE);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe("https://api.test/api/v1/altcha/challenge");
    expect(init.cache).toBe("no-store");
  });

  it("passes the API's rate limit through", async () => {
    fetchMock.mockResolvedValue(
      new Response(JSON.stringify({ message: "Too Many Attempts." }), {
        status: 429,
        headers: { "Content-Type": "application/json" },
      }),
    );

    const response = await GET(get({ "sec-fetch-site": "same-origin" }));

    expect(response.status).toBe(429);
  });

  it("refuses other sites", async () => {
    expect((await GET(get({ origin: "https://evil.example" }))).status).toBe(
      403,
    );
    expect((await GET(get({ "sec-fetch-site": "cross-site" }))).status).toBe(
      403,
    );
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
