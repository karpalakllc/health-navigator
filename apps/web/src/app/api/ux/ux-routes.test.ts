import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const { POST: events } = await import("@/app/api/ux/events/route");
const { GET: heatmap } = await import("@/app/api/ux/heatmap/route");

const SITE = "https://zdravje.test";
const TOKEN = `${"a".repeat(60)}.${"b".repeat(43)}`;
const fetchMock = vi.fn();

const click = {
  r: "/doctors/[slug]",
  vc: "desktop",
  wb: 1440,
  x: 40,
  y: 12,
  k: "doctor-card/heading",
  d: true,
  g: false,
};

beforeEach(() => {
  vi.stubEnv("NEXT_PUBLIC_SITE_URL", SITE);
  vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.test");
  vi.stubEnv("NODE_ENV", "production");
  fetchMock.mockResolvedValue(new Response(null, { status: 204 }));
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

function post(body: unknown, headers: Record<string, string> = {}) {
  return new Request(`${SITE}/api/ux/events`, {
    method: "POST",
    headers: {
      Origin: SITE,
      "Content-Type": "application/json",
      "X-Z360-Consent": "statistics",
      ...headers,
    },
    body: typeof body === "string" ? body : JSON.stringify(body),
  });
}

describe("POST /api/ux/events", () => {
  it("relays a rebuilt batch with only the known fields", async () => {
    const response = await events(
      post({
        clicks: [{ ...click, text: "Болка" }],
        views: [],
        sid: "tab-1",
      }),
    );

    expect(response.status).toBe(204);
    expect(fetchMock).toHaveBeenCalledOnce();
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("https://api.test/api/v1/ux/events");
    expect(JSON.parse(init.body)).toEqual({ clicks: [click], views: [] });
    expect(init.headers["X-Z360-Consent"]).toBe("statistics");
    expect(init.headers).not.toHaveProperty("Authorization");
    expect(init.headers).not.toHaveProperty("Cookie");
  });

  it("refuses another site's page", async () => {
    const response = await events(
      post({ clicks: [click], views: [] }, { Origin: "https://evil.test" }),
    );

    expect(response.status).toBe(403);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it.each([[{ "X-Z360-Consent": "" }], [{ "X-Z360-Consent": "marketing" }]])(
    "stores nothing without the statistics-consent header %o",
    async (headers) => {
      const response = await events(
        post({ clicks: [click], views: [] }, headers),
      );

      expect(response.status).toBe(204);
      expect(fetchMock).not.toHaveBeenCalled();
    },
  );

  it.each([[{ "Sec-GPC": "1" }], [{ DNT: "1" }]])(
    "still relays with consent when the browser sends %o (an explicit yes wins)",
    async (headers) => {
      const response = await events(
        post({ clicks: [click], views: [] }, headers),
      );

      expect(response.status).toBe(204);
      expect(fetchMock).toHaveBeenCalledOnce();
    },
  );

  it("drops non-JSON, oversized and invalid batches without calling the API", async () => {
    await events(post("clicks", { "Content-Type": "text/plain" }));
    await events(post("x".repeat(20 * 1024)));
    await events(post("{not json"));
    await events(post({ clicks: [{ ...click, r: "/account" }], views: [] }));

    expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe("POST /api/ux/events under load", () => {
  it("has no shared ceiling: one busy client cannot switch the relay off for everyone", async () => {
    // The API limits per visitor network; a per-process counter here would be
    // used up by a single client before that limit ever applied.
    for (let i = 0; i < 1_250; i++) {
      await events(post({ clicks: [click], views: [] }));
    }

    expect(fetchMock).toHaveBeenCalledTimes(1_250);
  });

  it("gives up on a hung API after 3 s", async () => {
    const timeout = vi.spyOn(AbortSignal, "timeout");

    await events(post({ clicks: [click], views: [] }));

    expect(timeout).toHaveBeenCalledWith(3_000);
    const [, init] = fetchMock.mock.calls[0];
    expect(init.signal).toBe(timeout.mock.results[0].value);
    timeout.mockRestore();
  });
});

describe("GET /api/ux/heatmap", () => {
  function get(query: string, token?: string) {
    return new Request(`${SITE}/api/ux/heatmap?${query}`, {
      headers: token ? { "X-Ux-Overlay-Token": token } : {},
    });
  }

  it("answers 403 without a token and never asks the API", async () => {
    const response = await heatmap(get("route=/doctors&vc=desktop"));

    expect(response.status).toBe(403);
    expect(response.headers.get("cache-control")).toContain("no-store");
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("does not accept the token in the query string", async () => {
    const response = await heatmap(
      get(`route=/doctors&vc=desktop&token=${TOKEN}`),
    );

    expect(response.status).toBe(403);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("rejects pages that are not tracked", async () => {
    const response = await heatmap(get("route=/account&vc=desktop", TOKEN));

    expect(response.status).toBe(422);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("passes the token to the API, which decides, and the answer through", async () => {
    fetchMock.mockResolvedValue(
      Response.json({ message: "Forbidden." }, { status: 403 }),
    );

    const response = await heatmap(
      get("route=/doctors/[slug]&vc=mobile&wb=320", TOKEN),
    );

    expect(response.status).toBe(403);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(
      "https://api.test/api/v1/ux/heatmap?route=%2Fdoctors%2F%5Bslug%5D&vc=mobile&wb=320",
    );
    expect(init.headers["x-ux-overlay-token"]).toBe(TOKEN);
    expect(response.headers.get("cache-control")).toContain("no-store");
  });
});
