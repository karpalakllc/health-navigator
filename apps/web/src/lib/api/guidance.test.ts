import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  GUIDANCE_TOKEN_HEADER,
  GuidanceApiError,
  completeGuidanceEmergency,
  isStaleGuidanceSession,
  completeGuidanceSession,
  parseStoredGuidanceSession,
  saveGuidanceAnswers,
  startGuidanceSession,
} from "@/lib/api/guidance";

/**
 * Guidance sessions are not linked to accounts, so the secret issued at
 * creation is the only thing proving this tab started the session. It has to
 * survive a reload (sessionStorage) and go out on every later call.
 */
describe("parseStoredGuidanceSession", () => {
  it("reads back a stored handle", () => {
    expect(
      parseStoredGuidanceSession(JSON.stringify({ id: "abc", token: "t0k" })),
    ).toEqual({ id: "abc", token: "t0k" });
  });

  it("ignores the bare id stored before tokens existed", () => {
    expect(
      parseStoredGuidanceSession("5f0c7c1e-6a3b-4b8e-9a1f-2f3d4e5a6b7c"),
    ).toBeNull();
  });

  it("ignores missing or malformed values", () => {
    expect(parseStoredGuidanceSession(null)).toBeNull();
    expect(parseStoredGuidanceSession("")).toBeNull();
    expect(parseStoredGuidanceSession("null")).toBeNull();
    expect(
      parseStoredGuidanceSession(JSON.stringify({ id: "abc" })),
    ).toBeNull();
    expect(
      parseStoredGuidanceSession(JSON.stringify({ id: "abc", token: "" })),
    ).toBeNull();
  });
});

describe("guidance session calls", () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    vi.stubEnv("NEXT_PUBLIC_API_URL", "http://api.test");
    vi.stubGlobal("fetch", fetchMock);
  });

  afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
    fetchMock.mockReset();
  });

  function respond(data: unknown, status = 200) {
    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ data }), { status }),
    );
  }

  it("rejects with the status, so the wizard can tell a stale handle apart", async () => {
    const handle = { id: "abc", token: "t0k" };

    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: "Not found." }), { status: 404 }),
    );
    const stale = await completeGuidanceEmergency(handle).catch((e) => e);
    expect(stale).toBeInstanceOf(GuidanceApiError);
    expect(stale).toMatchObject({ status: 404, message: "Not found." });
    expect(isStaleGuidanceSession(stale)).toBe(true);

    fetchMock.mockResolvedValueOnce(new Response("<html>", { status: 429 }));
    const limited = await startGuidanceSession().catch((e) => e);
    expect(limited).toMatchObject({ status: 429 });
    expect(isStaleGuidanceSession(limited)).toBe(false);
    expect(isStaleGuidanceSession(new TypeError("Failed to fetch"))).toBe(
      false,
    );
  });

  it("returns the id and token issued at creation", async () => {
    respond({ session_id: "s1", session_token: "secret" }, 201);

    await expect(startGuidanceSession()).resolves.toEqual({
      id: "s1",
      token: "secret",
    });
  });

  it("sends the token on answers, completion and the emergency exit", async () => {
    const session = { id: "s1", token: "secret" };

    respond({ emergency_stopped: false });
    await saveGuidanceAnswers(session, [{ step_key: "red_flags", values: [] }]);

    respond({ outcome: { outcome_code: "x" } });
    await completeGuidanceSession(session);

    respond({ outcome: { outcome_code: "emergency" } });
    await completeGuidanceEmergency(session);

    expect(fetchMock).toHaveBeenCalledTimes(3);

    for (const [url, init] of fetchMock.mock.calls) {
      expect(String(url)).toContain("/triage/sessions/s1/");
      expect(
        (init as RequestInit).headers as Record<string, string>,
      ).toMatchObject({ [GUIDANCE_TOKEN_HEADER]: "secret" });
    }
  });
});
