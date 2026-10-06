import { afterEach, describe, expect, it, vi } from "vitest";
import { sessionCookieOptions } from "@/lib/auth/session";
import { resetCookieOptions } from "@/lib/auth/reset-token";

/**
 * `Secure` used to follow NODE_ENV, so a development or test build served over
 * https sent the API token cookie without it. It now follows the public origin.
 */
describe.each([
  ["session", () => sessionCookieOptions(60)],
  ["password reset", () => resetCookieOptions()],
])("%s cookie Secure flag", (_name, options) => {
  afterEach(() => {
    vi.unstubAllEnvs();
  });

  it("is set for an https site even outside a production build", () => {
    vi.stubEnv("NODE_ENV", "development");
    vi.stubEnv("NEXT_PUBLIC_SITE_URL", "https://staging.zdravje360.mk");

    expect(options().secure).toBe(true);
  });

  it("is not set for a plain-http site, even in a production build", () => {
    vi.stubEnv("NODE_ENV", "production");
    vi.stubEnv("NEXT_PUBLIC_SITE_URL", "http://127.0.0.1:3010");

    expect(options().secure).toBe(false);
  });

  it("falls back to the build mode when no origin is configured", () => {
    vi.stubEnv("NEXT_PUBLIC_SITE_URL", "");

    vi.stubEnv("NODE_ENV", "production");
    expect(options().secure).toBe(true);

    vi.stubEnv("NODE_ENV", "development");
    expect(options().secure).toBe(false);
  });
});
