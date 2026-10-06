import { readFileSync } from "node:fs";
import { join } from "node:path";
import { afterEach, describe, expect, it, vi } from "vitest";

const init = vi.hoisted(() => vi.fn());
const captureException = vi.hoisted(() => vi.fn());

vi.mock("@sentry/nextjs", () => ({ init, captureException }));

afterEach(() => {
  vi.unstubAllEnvs();
  vi.resetModules();
  vi.clearAllMocks();
});

describe("lazy Sentry in the browser", () => {
  it("keeps the SDK out of the shared first-load code", () => {
    // A static import anywhere in these puts the whole SDK into every page.
    for (const file of [
      "src/instrumentation-client.ts",
      "src/app/error.tsx",
      "src/app/global-error.tsx",
    ]) {
      const source = readFileSync(join(process.cwd(), file), "utf8");

      expect(source, file).not.toMatch(/from ["']@sentry\/nextjs["']/);
    }
  });

  it("does nothing without a DSN", async () => {
    vi.stubEnv("NEXT_PUBLIC_SENTRY_DSN", "");
    const { loadSentry, reportException } = await import("@/lib/sentry-client");

    expect(loadSentry()).toBeNull();
    reportException(new Error("x"));
    await Promise.resolve();
    expect(init).not.toHaveBeenCalled();
  });

  it("initialises once with the scrubber and reports through it", async () => {
    vi.stubEnv("NEXT_PUBLIC_SENTRY_DSN", "https://key@o1.ingest.sentry.io/1");
    const { loadSentry, reportException } = await import("@/lib/sentry-client");
    const error = new Error("boom");

    reportException(error);
    await loadSentry();
    await loadSentry();
    await vi.waitFor(() =>
      expect(captureException).toHaveBeenCalledWith(error),
    );

    expect(init).toHaveBeenCalledTimes(1);
    expect(init.mock.calls[0][0]).toMatchObject({
      dsn: "https://key@o1.ingest.sentry.io/1",
      sendDefaultPii: false,
      tracesSampleRate: 0,
    });
    expect(typeof init.mock.calls[0][0].beforeSend).toBe("function");
  });
});
