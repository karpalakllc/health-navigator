import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

/**
 * /api/corrections: open to everyone. It forwards the session token only when
 * there is one, only the fields the API takes (the honeypot included, so the
 * API can drop a bot's request), and refuses malformed targets and
 * cross-site posts before reaching the API.
 */
const session = vi.hoisted(() => ({ token: null as string | null }));

vi.mock("@/lib/auth/session", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/auth/session")>()),
  getSessionToken: async () => session.token,
}));

const { POST } = await import("@/app/api/corrections/route");

const SITE = "https://zdravje.test";
const fetchMock = vi.fn();

beforeEach(() => {
  session.token = null;
  vi.stubEnv("NEXT_PUBLIC_SITE_URL", SITE);
  vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.test");
  vi.stubGlobal("fetch", fetchMock);
  fetchMock.mockResolvedValue(
    new Response(
      JSON.stringify({ data: { status: "received", message: "Примено." } }),
      { status: 201, headers: { "Content-Type": "application/json" } },
    ),
  );
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

function post(body: unknown, origin = SITE): Request {
  return new Request(`${SITE}/api/corrections`, {
    method: "POST",
    headers: { "Content-Type": "application/json", origin },
    body: JSON.stringify(body),
  });
}

function upstream(): { url: string; init: RequestInit } {
  const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
  return { url, init };
}

describe("POST /api/corrections", () => {
  it("relays an anonymous doctor correction without a token", async () => {
    const response = await POST(
      post({
        subject: "doctor",
        slug: "ana-petrovska",
        type: "correction",
        field: "office_hours",
        message: "Во петок работи до 14 часот.",
        contact: "pacient@example.test",
        altcha: "c29sdmVk",
        website: "",
        user_id: 7,
      }),
    );

    expect(response.status).toBe(201);
    const { url, init } = upstream();
    expect(url).toBe(
      "https://api.test/api/v1/doctors/ana-petrovska/corrections",
    );
    expect(new Headers(init.headers).get("authorization")).toBeNull();
    expect(JSON.parse(String(init.body))).toEqual({
      type: "correction",
      field: "office_hours",
      message: "Во петок работи до 14 часот.",
      contact: "pacient@example.test",
      altcha: "c29sdmVk",
      website: "",
    });
  });

  it("adds the session token when signed in and keeps a filled honeypot", async () => {
    session.token = "member-token";

    await POST(
      post({
        subject: "facility",
        slug: "klinika-centar",
        type: "objection",
        message: "Порака.",
        website: "http://spam.example",
      }),
    );

    const { url, init } = upstream();
    expect(url).toBe(
      "https://api.test/api/v1/facilities/klinika-centar/corrections",
    );
    expect(new Headers(init.headers).get("authorization")).toBe(
      "Bearer member-token",
    );
    expect(JSON.parse(String(init.body))).toMatchObject({
      type: "objection",
      website: "http://spam.example",
    });
  });

  it("refuses an unknown subject or a malformed slug without calling the API", async () => {
    expect(
      (await POST(post({ subject: "pharmacy", slug: "apteka" }))).status,
    ).toBe(404);
    expect(
      (await POST(post({ subject: "doctor", slug: "../me/export" }))).status,
    ).toBe(404);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("refuses a cross-site post", async () => {
    const response = await POST(
      post(
        { subject: "doctor", slug: "ana-petrovska", message: "x" },
        "https://evil.test",
      ),
    );

    expect(response.status).toBe(403);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
