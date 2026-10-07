import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

/**
 * /api/forum/posts/helpful (W8-C): „Корисно“ on a forum reply. Members only;
 * the reply id must be a positive integer; PUT and DELETE reach the API's
 * /forum/posts/{id}/helpful with the session token.
 */
const session = vi.hoisted(() => ({ token: "tok" as string | null }));

vi.mock("@/lib/auth/session", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/auth/session")>()),
  getSessionToken: async () => session.token,
}));

const { PUT, DELETE } = await import("@/app/api/forum/posts/helpful/route");

const SITE = "https://zdravje.test";
const fetchMock = vi.fn();

beforeEach(() => {
  session.token = "tok";
  vi.stubEnv("NEXT_PUBLIC_SITE_URL", SITE);
  vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.test");
  vi.stubGlobal("fetch", fetchMock);
  fetchMock.mockImplementation(
    async () =>
      new Response(
        JSON.stringify({ data: { helpful_count: 1, has_voted_helpful: true } }),
        { status: 200, headers: { "Content-Type": "application/json" } },
      ),
  );
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

function request(method: string, body: unknown): Request {
  return new Request(`${SITE}/api/forum/posts/helpful`, {
    method,
    headers: { "Content-Type": "application/json", origin: SITE },
    body: JSON.stringify(body),
  });
}

describe("/api/forum/posts/helpful", () => {
  it("forwards a vote and its removal to the reply's API route", async () => {
    expect((await PUT(request("PUT", { id: 42 }))).status).toBe(200);
    expect((await DELETE(request("DELETE", { id: 42 }))).status).toBe(200);

    const [putUrl, putInit] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(putUrl).toBe("https://api.test/api/v1/forum/posts/42/helpful");
    expect(putInit.method).toBe("PUT");
    expect(new Headers(putInit.headers).get("authorization")).toBe(
      "Bearer tok",
    );
    expect(fetchMock.mock.calls[1][1]).toMatchObject({ method: "DELETE" });
  });

  it("refuses a visitor without a session and a malformed id", async () => {
    expect((await PUT(request("PUT", { id: "42/../1" }))).status).toBe(422);

    session.token = null;
    expect((await PUT(request("PUT", { id: 42 }))).status).toBe(401);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
