import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

/**
 * „Мој профил“ route handlers: each forwards one call with the session token
 * and only the fields it is meant to, and refuses malformed ids and
 * cross-site requests before reaching the API.
 */
vi.mock("@/lib/auth/session", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/auth/session")>()),
  getSessionToken: async () => "doctor-token",
}));

const { PATCH: saveProfile } =
  await import("@/app/api/doctor-dashboard/profile/route");
const { POST: requestChange } =
  await import("@/app/api/doctor-dashboard/change-requests/route");
const { PUT: putReply, DELETE: deleteReply } =
  await import("@/app/api/doctor-dashboard/reviews/[id]/reply/route");
const { POST: claim } = await import("@/app/api/doctor-dashboard/claim/route");

const SITE = "https://zdravje.test";
const fetchMock = vi.fn();

beforeEach(() => {
  vi.stubEnv("NEXT_PUBLIC_SITE_URL", SITE);
  vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.test");
  vi.stubGlobal("fetch", fetchMock);
  fetchMock.mockResolvedValue(
    new Response(JSON.stringify({ data: {} }), {
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

function jsonRequest(
  method: string,
  body: unknown,
  headers: Record<string, string> = { origin: SITE },
): Request {
  return new Request(`${SITE}/api/doctor-dashboard`, {
    method,
    headers: { "Content-Type": "application/json", ...headers },
    body: JSON.stringify(body),
  });
}

function upstream(): { url: string; init: RequestInit } {
  const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
  return { url, init };
}

describe("PATCH /api/doctor-dashboard/profile", () => {
  it("forwards only the practice fields, with the token", async () => {
    const response = await saveProfile(
      jsonRequest("PATCH", {
        phone: "070 123 456",
        bio: "Кардиолог.",
        is_featured: true,
        is_sponsored: true,
        slug: "nov-slug",
        full_name: "Друго Име",
      }),
    );

    expect(response.status).toBe(200);
    const { url, init } = upstream();
    expect(url).toBe("https://api.test/api/v1/me/doctor");
    expect(init.method).toBe("PATCH");
    expect(new Headers(init.headers).get("authorization")).toBe(
      "Bearer doctor-token",
    );
    expect(JSON.parse(String(init.body))).toEqual({
      phone: "070 123 456",
      bio: "Кардиолог.",
    });
  });

  it("refuses a cross-site request without calling the API", async () => {
    const response = await saveProfile(
      jsonRequest("PATCH", { phone: "1" }, { origin: "https://evil.test" }),
    );

    expect(response.status).toBe(403);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe("POST /api/doctor-dashboard/change-requests", () => {
  it("forwards the sensitive fields only", async () => {
    await requestChange(
      jsonRequest("POST", {
        full_name: "д-р Ана",
        specialty_ids: [1],
        is_published: false,
      }),
    );

    expect(JSON.parse(String(upstream().init.body))).toEqual({
      full_name: "д-р Ана",
      specialty_ids: [1],
    });
  });
});

describe("/api/doctor-dashboard/reviews/[id]/reply", () => {
  it("writes the reply to the review's path", async () => {
    await putReply(jsonRequest("PUT", { body: "Благодарам.", extra: 1 }), {
      params: Promise.resolve({ id: "42" }),
    });

    const { url, init } = upstream();
    expect(url).toBe("https://api.test/api/v1/me/doctor/reviews/42/reply");
    expect(JSON.parse(String(init.body))).toEqual({ body: "Благодарам." });
  });

  it("refuses an id that is not a number", async () => {
    const response = await deleteReply(
      new Request(`${SITE}/api/doctor-dashboard/reviews/x/reply`, {
        method: "DELETE",
        headers: { origin: SITE },
      }),
      { params: Promise.resolve({ id: "../me" }) },
    );

    expect(response.status).toBe(404);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe("POST /api/doctor-dashboard/claim", () => {
  it("files the request against the profile's slug", async () => {
    await claim(
      jsonRequest("POST", {
        slug: "ana-petrovska",
        message: "Јас сум.",
        contact: "070 123 456",
      }),
    );

    const { url, init } = upstream();
    expect(url).toBe(
      "https://api.test/api/v1/doctors/ana-petrovska/claim-requests",
    );
    expect(JSON.parse(String(init.body))).toEqual({
      message: "Јас сум.",
      contact: "070 123 456",
    });
  });

  it("refuses a slug that is not one", async () => {
    const response = await claim(
      jsonRequest("POST", { slug: "../me", message: "x", contact: "y" }),
    );

    expect(response.status).toBe(404);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
