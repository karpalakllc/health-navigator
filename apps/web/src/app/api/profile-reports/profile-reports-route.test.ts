import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

/**
 * /api/profile-reports („Пријави профил“): open to everyone. It picks the
 * API route by profile kind, forwards the session token only when there is
 * one (and drops an expired one), relays only the fields the API takes —
 * the ALTCHA payload and the honeypot included, for the API to judge — and
 * refuses malformed targets, reasons and cross-site posts first.
 */
const session = vi.hoisted(() => ({ token: null as string | null }));

vi.mock("@/lib/auth/session", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/auth/session")>()),
  getSessionToken: async () => session.token,
}));

const { POST } = await import("@/app/api/profile-reports/route");

const SITE = "https://zdravje.test";
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
  fetchMock.mockResolvedValue(
    json(201, { data: { status: "received", message: "Примено." } }),
  );
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

function post(body: unknown, origin = SITE): Request {
  return new Request(`${SITE}/api/profile-reports`, {
    method: "POST",
    headers: { "Content-Type": "application/json", origin },
    body: JSON.stringify(body),
  });
}

describe("POST /api/profile-reports", () => {
  it("relays a guest's pharmacy report with the ALTCHA payload and honeypot", async () => {
    const response = await POST(
      post({
        subject: "pharmacy",
        slug: "apteka-centar",
        reason: "no_longer_here",
        note: "Затворена е.",
        altcha: "c29sdmVk",
        website: "",
        user_id: 3,
      }),
    );

    expect(response.status).toBe(201);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(
      "https://api.test/api/v1/pharmacies/apteka-centar/profile-reports",
    );
    expect(new Headers(init.headers).get("authorization")).toBeNull();
    expect(JSON.parse(String(init.body))).toEqual({
      reason: "no_longer_here",
      note: "Затворена е.",
      altcha: "c29sdmVk",
      website: "",
    });
  });

  it("adds the session token and, if it has expired, sends the report without it", async () => {
    session.token = "stale-token";
    fetchMock
      .mockResolvedValueOnce(json(401, { message: "Unauthenticated." }))
      .mockResolvedValueOnce(json(201, { data: { status: "received" } }));

    const response = await POST(
      post({
        subject: "doctor",
        slug: "ana-petrovska",
        reason: "wrong_person",
      }),
    );

    expect(response.status).toBe(201);
    expect(fetchMock).toHaveBeenCalledTimes(2);
    const first = fetchMock.mock.calls[0][1] as RequestInit;
    const second = fetchMock.mock.calls[1][1] as RequestInit;
    expect(new Headers(first.headers).get("authorization")).toBe(
      "Bearer stale-token",
    );
    expect(new Headers(second.headers).get("authorization")).toBeNull();
  });

  it("refuses an unknown kind or a malformed slug without calling the API", async () => {
    for (const body of [
      { subject: "user", slug: "ana", reason: "other" },
      { subject: "doctor", slug: "../me", reason: "other" },
    ]) {
      expect((await POST(post(body))).status).toBe(404);
    }

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("refuses a reason the API does not know", async () => {
    const response = await POST(
      post({ subject: "facility", slug: "klinika", reason: "spam" }),
    );

    expect(response.status).toBe(422);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("refuses a cross-site post", async () => {
    const response = await POST(
      post(
        { subject: "doctor", slug: "ana", reason: "other" },
        "https://evil.example",
      ),
    );

    expect(response.status).toBe(403);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
