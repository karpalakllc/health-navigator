import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const captureMessage = vi.hoisted(() => vi.fn());

vi.mock("@sentry/nextjs", () => ({ captureMessage }));

beforeEach(() => {
  vi.resetModules();
  captureMessage.mockClear();
  vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.zdravje.test");
  vi.stubGlobal(
    "fetch",
    vi.fn(
      async () => new Response("<html>bad gateway</html>", { status: 502 }),
    ),
  );
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});

describe("loadPublicSettings during an outage", () => {
  it("reports to Sentry once a minute per instance, not once per view", async () => {
    vi.useFakeTimers();
    vi.setSystemTime(0);
    const { loadPublicSettings } = await import("@/lib/api/settings");

    for (let view = 0; view < 25; view += 1) {
      const settings = await loadPublicSettings();
      expect(settings.degraded).toBe(true);
    }

    expect(captureMessage).toHaveBeenCalledTimes(1);

    vi.setSystemTime(60_000);
    await loadPublicSettings();

    expect(captureMessage).toHaveBeenCalledTimes(2);
  });
});
