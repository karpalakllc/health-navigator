import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  GUIDANCE_TOKEN_HEADER,
  GuidanceApiError,
  isStaleGuidanceSession,
  parseStoredGuidanceSession,
} from "@/lib/api/guidance";
import {
  answerQuestion,
  answerScreen,
  chooseSymptoms,
  emergencyShortcut,
  fetchState,
  noMatch,
  saveDemographics,
  startSession,
} from "@/lib/api/guidance-v2";

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

  it("rejects with the status, so the guide can tell a stale handle apart", async () => {
    const handle = { id: "abc", token: "t0k" };

    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: "Not found." }), { status: 404 }),
    );
    const stale = await emergencyShortcut(handle).catch((e: unknown) => e);
    expect(stale).toBeInstanceOf(GuidanceApiError);
    expect(stale).toMatchObject({ status: 404, message: "Not found." });
    expect(isStaleGuidanceSession(stale)).toBe(true);

    fetchMock.mockResolvedValueOnce(new Response("<html>", { status: 429 }));
    const limited = await startSession().catch((e: unknown) => e);
    expect(limited).toMatchObject({ status: 429 });
    expect(isStaleGuidanceSession(limited)).toBe(false);
    expect(isStaleGuidanceSession(new TypeError("Failed to fetch"))).toBe(
      false,
    );
  });

  it("returns the id and token issued at creation, with the first state", async () => {
    respond(
      {
        session_id: "s1",
        session_token: "secret",
        state: { session_id: "s1", stage: "demographics" },
      },
      201,
    );

    await expect(startSession()).resolves.toEqual({
      handle: { id: "s1", token: "secret" },
      state: { session_id: "s1", stage: "demographics" },
    });
    expect(fetchMock.mock.calls[0][1]).not.toHaveProperty([
      "headers",
      GUIDANCE_TOKEN_HEADER,
    ]);
  });

  it("sends the token on every later call", async () => {
    const session = { id: "s1", token: "secret" };
    const state = {
      session_id: "s1",
      stage: "symptoms",
      age_band: "adult_18_64",
    };

    respond(state);
    await fetchState(session);
    respond(state);
    await saveDemographics(session, {
      age_value: 30,
      age_unit: "years",
      sex: "male",
      pregnancy: null,
      conditions: [],
    });
    respond(state);
    await chooseSymptoms(session, ["general"]);
    respond(state);
    await answerScreen(session, []);
    respond(state);
    await answerQuestion(session, "general", "concern", ["general"]);
    respond(state);
    await emergencyShortcut(session);
    respond({ suggested: ["general"] });
    await noMatch(session, null);

    expect(fetchMock).toHaveBeenCalledTimes(7);

    for (const [url, init] of fetchMock.mock.calls) {
      expect(String(url)).toContain("/api/v1/triage/v2/sessions/s1");
      expect(
        (init as RequestInit).headers as Record<string, string>,
      ).toMatchObject({ [GUIDANCE_TOKEN_HEADER]: "secret" });
    }
  });

  it("sends answers as codes and numbers only", async () => {
    respond({ session_id: "s1", stage: "result" });
    await answerQuestion({ id: "s1", token: "x" }, "fever", "q_temp", ["38.5"]);

    const [, init] = fetchMock.mock.calls[0];
    expect(JSON.parse(String((init as RequestInit).body))).toEqual({
      flow: "fever",
      node: "q_temp",
      values: ["38.5"],
    });
  });
});
