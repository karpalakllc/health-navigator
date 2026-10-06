import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

/**
 * The account route handlers (export, delete, devices) and the device label
 * the login handler sends. The session lookup reads next/headers, which needs
 * a live request, so it is replaced with a stub that pretends someone is
 * signed in.
 */
vi.mock("@/lib/auth/session", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/auth/session")>()),
  getSessionToken: async () => "member-token",
}));

const { GET: exportData } = await import("@/app/api/account/export/route");
const { POST: deleteAccount } = await import("@/app/api/account/delete/route");
const { DELETE: revokeOthers } =
  await import("@/app/api/account/devices/route");
const { DELETE: revokeOne } =
  await import("@/app/api/account/devices/[id]/route");
const { POST: login } = await import("@/app/api/session/login/route");
const { SESSION_COOKIE } = await import("@/lib/auth/session");

const SITE = "https://zdravje.test";
const fetchMock = vi.fn();

beforeEach(() => {
  vi.stubEnv("NEXT_PUBLIC_SITE_URL", SITE);
  vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.test");
  vi.stubEnv("NODE_ENV", "production");
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

function upstreamCall(index = 0): { url: string; init: RequestInit } {
  const [url, init] = fetchMock.mock.calls[index] as [string, RequestInit];
  return { url, init };
}

describe("GET /api/account/export", () => {
  function get(headers: Record<string, string> = {}): Promise<Response> {
    return exportData(
      new Request(`${SITE}/api/account/export`, {
        headers: { "sec-fetch-site": "same-origin", ...headers },
      }),
    );
  }

  it("streams the API's JSON as an attachment, token kept server-side", async () => {
    fetchMock.mockResolvedValue(
      new Response('{"format":"zdravje360.account-export"}', {
        status: 200,
        headers: {
          "Content-Type": "application/json",
          "Content-Disposition":
            'attachment; filename="zdravje360-2026-10-06.json"',
        },
      }),
    );

    const response = await get();

    expect(response.status).toBe(200);
    expect(response.headers.get("content-disposition")).toBe(
      'attachment; filename="zdravje360-2026-10-06.json"',
    );
    expect(response.headers.get("cache-control")).toContain("no-store");
    expect(await response.text()).toBe(
      '{"format":"zdravje360.account-export"}',
    );

    const { url, init } = upstreamCall();
    expect(url).toBe("https://api.test/api/v1/me/export");
    expect(new Headers(init.headers).get("authorization")).toBe(
      "Bearer member-token",
    );
    expect(response.headers.get("set-cookie")).toBeNull();
  });

  it("refuses a download started from another site", async () => {
    const response = await get({ "sec-fetch-site": "cross-site" });

    expect(response.status).toBe(303);
    expect(response.headers.get("location")).toBe("/account/data");
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it.each([
    [429, "/account/data?export=throttled"],
    [500, "/account/data?export=error"],
    [401, "/login?redirect=%2Faccount%2Fdata"],
  ])("sends the browser back on %i", async (status, location) => {
    fetchMock.mockResolvedValue(json({ message: "x" }, status));

    const response = await get();

    expect(response.status).toBe(303);
    expect(response.headers.get("location")).toBe(location);
  });

  it("sends the browser back when the API is unreachable", async () => {
    fetchMock.mockRejectedValue(new TypeError("fetch failed"));

    const response = await get();

    expect(response.headers.get("location")).toBe("/account/data?export=error");
  });
});

describe("POST /api/account/delete", () => {
  function post(
    body: unknown,
    headers: Record<string, string> = {},
  ): Promise<Response> {
    return deleteAccount(
      new Request(`${SITE}/api/account/delete`, {
        method: "POST",
        headers: {
          origin: SITE,
          "content-type": "application/json",
          ...headers,
        },
        body: JSON.stringify(body),
      }),
    );
  }

  it("forwards only the password and clears the session cookie on success", async () => {
    fetchMock.mockResolvedValue(json({ data: { message: "ok" } }));

    const response = await post({ password: "secret1long", extra: "x" });

    expect(response.status).toBe(200);
    const { url, init } = upstreamCall();
    expect(url).toBe("https://api.test/api/v1/me");
    expect(init.method).toBe("DELETE");
    expect(JSON.parse(String(init.body))).toEqual({ password: "secret1long" });
    expect(response.headers.get("set-cookie")).toContain(`${SESSION_COOKIE}=;`);
  });

  it("relays a wrong password and keeps the session", async () => {
    fetchMock.mockResolvedValue(
      json({ errors: { password: ["Лозинката не е точна."] } }, 422),
    );

    const response = await post({ password: "wrong" });

    expect(response.status).toBe(422);
    expect(await response.json()).toEqual({
      errors: { password: ["Лозинката не е точна."] },
    });
    expect(response.headers.get("set-cookie")).toBeNull();
  });

  it("refuses a cross-site request without calling the API", async () => {
    const response = await post(
      { password: "secret1long" },
      { origin: "https://evil.example" },
    );

    expect(response.status).toBe(403);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe("DELETE /api/account/devices", () => {
  function del(path: string, origin = SITE): Request {
    return new Request(`${SITE}${path}`, {
      method: "DELETE",
      headers: { origin },
    });
  }

  it("revokes one device by id", async () => {
    fetchMock.mockResolvedValue(json({ data: { message: "ok" } }));

    const response = await revokeOne(del("/api/account/devices/5"), {
      params: Promise.resolve({ id: "5" }),
    });

    expect(response.status).toBe(200);
    const { url, init } = upstreamCall();
    expect(url).toBe("https://api.test/api/v1/me/tokens/5");
    expect(init.method).toBe("DELETE");
  });

  it("rejects a malformed id without calling the API", async () => {
    const response = await revokeOne(del("/api/account/devices/..%2Fexport"), {
      params: Promise.resolve({ id: "../export" }),
    });

    expect(response.status).toBe(404);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("relays the API's 404 for someone else's device", async () => {
    fetchMock.mockResolvedValue(json({ code: "errors.not_found" }, 404));

    const response = await revokeOne(del("/api/account/devices/9"), {
      params: Promise.resolve({ id: "9" }),
    });

    expect(response.status).toBe(404);
  });

  it("revokes all other devices", async () => {
    fetchMock.mockResolvedValue(json({ data: { revoked: 2 } }));

    const response = await revokeOthers(del("/api/account/devices"));

    expect(response.status).toBe(200);
    expect(upstreamCall().url).toBe("https://api.test/api/v1/me/tokens");
  });

  it("refuses another site", async () => {
    const response = await revokeOthers(
      del("/api/account/devices", "https://evil.example"),
    );

    expect(response.status).toBe(403);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe("login device name", () => {
  it("names the token after the browser, never the raw user agent", async () => {
    fetchMock.mockResolvedValue(json({ data: { token: "t", user: {} } }));
    const ua =
      "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36";

    await login(
      new Request(`${SITE}/api/session/login`, {
        method: "POST",
        headers: {
          origin: SITE,
          "content-type": "application/json",
          "user-agent": ua,
        },
        body: JSON.stringify({
          email: "a@b.mk",
          password: "x",
          device_name: "spoofed",
        }),
      }),
    );

    expect(JSON.parse(String(upstreamCall().init.body)).device_name).toBe(
      "Chrome · macOS",
    );
  });
});
