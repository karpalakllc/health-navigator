import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const { POST } = await import("@/app/api/feedback/route");

const SITE = "https://zdravje.test";
const fetchMock = vi.fn();

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
  return new Request(`${SITE}/api/feedback`, {
    method: "POST",
    headers: { Origin: SITE, "Content-Type": "application/json", ...headers },
    body: typeof body === "string" ? body : JSON.stringify(body),
  });
}

describe("POST /api/feedback", () => {
  it("relays a vote rebuilt from the closed vocabulary", async () => {
    const response = await POST(
      post({
        kind: "vote",
        item: "guide:kako-do-uput",
        helpful: true,
        note: "одлично",
      }),
    );

    expect(response.status).toBe(204);
    expect(fetchMock).toHaveBeenCalledOnce();
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("https://api.test/api/v1/feedback");
    expect(JSON.parse(init.body)).toEqual({
      item: "guide:kako-do-uput",
      helpful: true,
    });
    expect(init.headers.Cookie).toBeUndefined();
  });

  it("relays reasons and steps to their endpoints", async () => {
    await POST(
      post({
        kind: "reasons",
        item: "urgent-care:bitola",
        helpful: false,
        reasons: ["outdated"],
      }),
    );
    await POST(
      post({
        kind: "step",
        funnel: "guidance:headache",
        step: "start",
        depth: 0,
      }),
    );

    expect(fetchMock.mock.calls.map(([url]) => url)).toEqual([
      "https://api.test/api/v1/feedback/reasons",
      "https://api.test/api/v1/feedback/steps",
    ]);
  });

  it("drops anything outside the vocabulary, without calling the API", async () => {
    for (const body of [
      { kind: "vote", item: "guide:Што ве боли", helpful: true },
      {
        kind: "reasons",
        item: "guide:x",
        helpful: true,
        reasons: ["моја причина"],
      },
      "not json",
    ]) {
      expect((await POST(post(body))).status).toBe(204);
    }
    expect(
      (
        await POST(
          post(
            { kind: "vote", item: "guide:x", helpful: true },
            { "Content-Type": "text/plain" },
          ),
        )
      ).status,
    ).toBe(204);

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("drops step counters with Global Privacy Control or Do Not Track, but not votes", async () => {
    await POST(
      post(
        { kind: "step", funnel: "guidance:x", step: "start", depth: 0 },
        { "Sec-GPC": "1" },
      ),
    );
    await POST(
      post(
        { kind: "step", funnel: "guidance:x", step: "start", depth: 0 },
        { DNT: "1" },
      ),
    );
    expect(fetchMock).not.toHaveBeenCalled();

    await POST(
      post(
        { kind: "vote", item: "guide:x", helpful: false },
        { "Sec-GPC": "1" },
      ),
    );
    expect(fetchMock).toHaveBeenCalledOnce();
  });

  it("refuses another site", async () => {
    const response = await POST(
      post(
        { kind: "vote", item: "guide:x", helpful: true },
        { Origin: "https://evil.test" },
      ),
    );

    expect(response.status).toBe(403);
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
