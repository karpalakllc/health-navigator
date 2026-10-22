import { describe, expect, it } from "vitest";
import { filenameFromDisposition } from "./download-filename";

describe("filenameFromDisposition", () => {
  it("reads the unquoted token Laravel sends", () => {
    expect(
      filenameFromDisposition(
        "attachment; filename=zdravje360-2026-10-22.json",
      ),
    ).toBe("zdravje360-2026-10-22.json");
  });

  it("reads a quoted name", () => {
    expect(
      filenameFromDisposition('attachment; filename="zdravje360-2026.json"'),
    ).toBe("zdravje360-2026.json");
  });

  it("prefers the extended filename*", () => {
    expect(
      filenameFromDisposition(
        "attachment; filename=fallback.json; filename*=utf-8''zdravje360-%D0%B0.json",
      ),
    ).toBe("zdravje360-а.json");
  });

  it("falls back when missing", () => {
    expect(filenameFromDisposition(null)).toBe("zdravje360.json");
    expect(filenameFromDisposition("attachment")).toBe("zdravje360.json");
  });
});
