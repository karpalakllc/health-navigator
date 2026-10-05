import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

/**
 * The route handlers themselves, not just the guard: each one has to call it.
 * The session lookup reads next/headers, which needs a live request, so it is
 * replaced with a stub that pretends someone is signed in.
 */
vi.mock("@/lib/auth/session", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/auth/session")>()),
  getSessionToken: async () => "victim-token",
}));

const { POST: login } = await import("@/app/api/session/login/route");
const { POST: logout } = await import("@/app/api/session/logout/route");
const { POST: review } = await import("@/app/api/reviews/route");
const { POST: reply } = await import("@/app/api/forum/posts/route");
const { POST: avatar } = await import("@/app/api/session/avatar/route");
const { SESSION_COOKIE } = await import("@/lib/auth/session");

const SITE = "https://zdravje.test";
const fetchMock = vi.fn();

beforeEach(() => {
  vi.stubEnv("NEXT_PUBLIC_SITE_URL", SITE);
  vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.test");
  vi.stubEnv("NODE_ENV", "production");
  fetchMock.mockResolvedValue(
    new Response(
      JSON.stringify({ data: { token: "attacker-token", user: {} } }),
      { status: 200 },
    ),
  );
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

function request(
  path: string,
  headers: Record<string, string>,
  body?: BodyInit,
): Request {
  return new Request(`${SITE}${path}`, { method: "POST", headers, body });
}

describe("login CSRF", () => {
  it("does not sign anyone in from a cross-site text/plain form post", async () => {
    // What <form enctype="text/plain" action=".../api/session/login"> sends.
    const response = await login(
      request(
        "/api/session/login",
        { origin: "https://evil.example", "content-type": "text/plain" },
        '{"email":"attacker@evil.example","password":"x","_":"="}',
      ),
    );

    expect(response.status).toBe(403);
    expect(response.headers.get("set-cookie")).toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("refuses text/plain even from our own origin", async () => {
    const response = await login(
      request(
        "/api/session/login",
        { origin: SITE, "content-type": "text/plain" },
        "{}",
      ),
    );

    expect(response.status).toBe(415);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("still signs in from our own login form", async () => {
    const response = await login(
      request(
        "/api/session/login",
        { origin: SITE, "content-type": "application/json" },
        JSON.stringify({ email: "a@b.mk", password: "x" }),
      ),
    );

    expect(response.status).toBe(200);
    expect(response.headers.get("set-cookie")).toContain(SESSION_COOKIE);
  });

  it("answers malformed JSON with 400", async () => {
    const response = await login(
      request(
        "/api/session/login",
        { origin: SITE, "content-type": "application/json" },
        "{",
      ),
    );

    expect(response.status).toBe(400);
  });
});

describe("logout", () => {
  it("cannot be forced by another site", async () => {
    const response = await logout(
      request("/api/session/logout", { origin: "https://evil.example" }),
    );

    expect(response.status).toBe(403);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("clears the cookie even when the API is unreachable", async () => {
    fetchMock.mockRejectedValue(new TypeError("fetch failed"));

    const response = await logout(
      request("/api/session/logout", { origin: SITE }),
    );

    expect(response.status).toBe(200);
    expect(response.headers.get("set-cookie")).toMatch(
      new RegExp(`${SESSION_COOKIE}=;.*Max-Age=0`, "i"),
    );
  });
});

describe("slugs in request bodies", () => {
  it("refuses a review slug that would leave /doctors/{slug}/reviews", async () => {
    const response = await review(
      request(
        "/api/reviews",
        { origin: SITE, "content-type": "application/json" },
        JSON.stringify({ kind: "doctor", slug: "../../me", rating: 5 }),
      ),
    );

    expect(response.status).toBe(422);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("refuses forum slugs with separators", async () => {
    const response = await reply(
      request(
        "/api/forum/posts",
        { origin: SITE, "content-type": "application/json" },
        JSON.stringify({
          categorySlug: "a",
          topicSlug: "b/../../x",
          body: "x",
        }),
      ),
    );

    expect(response.status).toBe(422);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe("avatar upload", () => {
  it("does not forward a non-image to the API", async () => {
    const form = new FormData();
    form.append("avatar", new File(["hello"], "me.png", { type: "image/png" }));
    const encoded = new Response(form);

    const response = await avatar(
      request(
        "/api/session/avatar",
        {
          origin: SITE,
          "content-type": encoded.headers.get("content-type") ?? "",
        },
        await encoded.arrayBuffer(),
      ),
    );

    expect(response.status).toBe(422);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
