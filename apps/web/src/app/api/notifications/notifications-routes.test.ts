import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

/**
 * W8-B route handlers: the signed one-click unsubscribe (mail clients POST
 * without an Origin or a session; the token is the authorisation), the
 * on-screen review views (crawlers dropped, ids bounded) and the e-mail
 * switches (only known keys and booleans reach the API).
 */
const session = vi.hoisted(() => ({ token: null as string | null }));

vi.mock("@/lib/auth/session", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/auth/session")>()),
  getSessionToken: async () => session.token,
}));

const unsubscribe = await import("@/app/api/notifications/unsubscribe/route");
const views = await import("@/app/api/reviews/views/route");
const preferences = await import("@/app/api/notifications/preferences/route");

const SITE = "https://zdravje.test";
const TOKEN = `12.review_helpful.${"A".repeat(43)}`;
const BROWSER =
  "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15";
const fetchMock = vi.fn();

function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

beforeEach(() => {
  session.token = null;
  vi.stubEnv("NEXT_PUBLIC_SITE_URL", SITE);
  vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.test");
  vi.stubGlobal("fetch", fetchMock);
  fetchMock.mockResolvedValue(json(200, { data: { ok: true } }));
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

function upstream(): { url: string; init: RequestInit } {
  const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];

  return { url, init };
}

describe("POST /api/notifications/unsubscribe", () => {
  it("accepts the mail client's RFC 8058 one-click POST without an origin or a session", async () => {
    const response = await unsubscribe.POST(
      new Request(`${SITE}/api/notifications/unsubscribe?token=${TOKEN}`, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "List-Unsubscribe=One-Click",
      }),
    );

    expect(response.status).toBe(200);
    const { url, init } = upstream();
    expect(url).toBe("https://api.test/api/v1/notifications/unsubscribe");
    expect(JSON.parse(String(init.body))).toEqual({ token: TOKEN });
    expect(new Headers(init.headers).has("Authorization")).toBe(false);
  });

  it("takes the page's JSON body only from our own origin", async () => {
    const ours = await unsubscribe.POST(
      new Request(`${SITE}/api/notifications/unsubscribe`, {
        method: "POST",
        headers: { "Content-Type": "application/json", origin: SITE },
        body: JSON.stringify({ token: TOKEN }),
      }),
    );
    expect(ours.status).toBe(200);

    const foreign = await unsubscribe.POST(
      new Request(`${SITE}/api/notifications/unsubscribe`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          origin: "https://evil.example",
        },
        body: JSON.stringify({ token: TOKEN }),
      }),
    );
    expect(foreign.status).toBe(403);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("refuses a malformed token before calling the API", async () => {
    const response = await unsubscribe.POST(
      new Request(`${SITE}/api/notifications/unsubscribe?token=12.x.short`, {
        method: "POST",
      }),
    );

    expect(response.status).toBe(404);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe("POST /api/reviews/views", () => {
  function report(ids: unknown, agent = BROWSER): Request {
    return new Request(`${SITE}/api/reviews/views`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        origin: SITE,
        "user-agent": agent,
        "x-z360-consent": "statistics",
      },
      body: JSON.stringify({ ids }),
    });
  }

  it("relays a browser's report, with the session when there is one", async () => {
    session.token = "tok";

    const response = await views.POST(report([3, 4]));

    expect(response.status).toBe(200);
    const { url, init } = upstream();
    expect(url).toBe("https://api.test/api/v1/reviews/views");
    expect(JSON.parse(String(init.body))).toEqual({ ids: [3, 4] });
    expect(new Headers(init.headers).get("Authorization")).toBe("Bearer tok");
    expect(new Headers(init.headers).get("X-Z360-Consent")).toBe("statistics");
  });

  it("drops crawlers without counting", async () => {
    const response = await views.POST(
      report(
        [3],
        "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)",
      ),
    );

    expect(await response.json()).toEqual({ data: { counted: 0 } });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("counts nothing without the statistics-consent header (204)", async () => {
    const request = report([3]);
    request.headers.delete("x-z360-consent");

    const response = await views.POST(request);

    expect(response.status).toBe(204);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it.each([["sec-gpc"], ["dnt"]])(
    "still counts with consent when the request carries %s: 1",
    async (header) => {
      const request = report([3]);
      request.headers.set(header, "1");

      const response = await views.POST(request);

      expect(response.status).toBe(200);
      expect(fetchMock).toHaveBeenCalledOnce();
    },
  );

  it.each([
    [[]],
    [Array.from({ length: 31 }, (_, i) => i + 1)],
    [["1"]],
    [[0]],
    ["3"],
  ])("refuses ids %j", async (ids) => {
    expect((await views.POST(report(ids))).status).toBe(422);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe("PUT /api/notifications/preferences", () => {
  it("passes only known switches and booleans", async () => {
    session.token = "tok";

    const response = await preferences.PUT(
      new Request(`${SITE}/api/notifications/preferences`, {
        method: "PUT",
        headers: { "Content-Type": "application/json", origin: SITE },
        body: JSON.stringify({
          email_enabled: "yes",
          types: { impact_digest: true, marketing: true, moderation: 1 },
        }),
      }),
    );

    expect(response.status).toBe(200);
    expect(JSON.parse(String(upstream().init.body))).toEqual({
      types: { impact_digest: true },
    });
  });

  it("needs a session", async () => {
    const response = await preferences.PUT(
      new Request(`${SITE}/api/notifications/preferences`, {
        method: "PUT",
        headers: { "Content-Type": "application/json", origin: SITE },
        body: JSON.stringify({ email_enabled: false }),
      }),
    );

    expect(response.status).toBe(401);
  });
});
