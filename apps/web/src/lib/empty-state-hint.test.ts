import { afterEach, describe, expect, it, vi } from "vitest";
import { emptyStateHint } from "./empty-state-hint";

afterEach(() => vi.unstubAllEnvs());

describe("emptyStateHint", () => {
  it("suggests loosening filters when filters are active", () => {
    expect(emptyStateHint(true, "pharmacies")).toContain("филтер");
  });

  it("is a plain line outside development", () => {
    vi.stubEnv("NODE_ENV", "production");
    const text = emptyStateHint(false, "pharmacies");
    expect(text).toBe("Сè уште нема внесени аптеки.");
    expect(text).not.toContain("migrate");
  });

  it("keeps the developer hint in development", () => {
    vi.stubEnv("NODE_ENV", "development");
    expect(emptyStateHint(false, "doctors")).toContain("migrate:fresh");
  });
});
