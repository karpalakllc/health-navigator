import { describe, expect, it } from "vitest";
import {
  DISPLAY_NAME_MAX_LENGTH,
  isValidDisplayName,
  normalizeDisplayName,
  suggestDisplayName,
} from "@/lib/display-name";

describe("suggestDisplayName", () => {
  it("uses the first name and the initial of the last name", () => {
    expect(suggestDisplayName("Марија Костовска")).toBe("Марија К.");
    expect(suggestDisplayName("Ана Марија Петровска")).toBe("Ана П.");
  });

  it("upper-cases the initial and ignores extra whitespace", () => {
    expect(suggestDisplayName("  ана   стојанова ")).toBe("ана С.");
  });

  it("keeps a single name as it is", () => {
    expect(suggestDisplayName("Бојан")).toBe("Бојан");
  });

  it("suggests nothing for an empty name", () => {
    expect(suggestDisplayName("")).toBe("");
    expect(suggestDisplayName("   ")).toBe("");
  });

  it("stays within the limit and keeps the initial", () => {
    const suggestion = suggestDisplayName(`${"а".repeat(60)} Петровска`);

    expect([...suggestion].length).toBe(DISPLAY_NAME_MAX_LENGTH);
    expect(suggestion.endsWith(" П.")).toBe(true);
  });

  it("produces a name the rules accept", () => {
    expect(isValidDisplayName(suggestDisplayName("Марија Костовска"))).toBe(
      true,
    );
  });
});

describe("isValidDisplayName", () => {
  it("accepts letters, spaces and name punctuation", () => {
    for (const valid of ["Марија К.", "O'Neil", "Ана-Марија", "Zoë M."]) {
      expect(isValidDisplayName(valid)).toBe(true);
    }
  });

  it("rejects digits, markup, symbols, a leading mark and blanks", () => {
    for (const invalid of [
      "Марија2",
      "<b>Марија</b>",
      ".Марија",
      "@marija",
      "ана_м",
      "   ",
      "а".repeat(DISPLAY_NAME_MAX_LENGTH + 1),
    ]) {
      expect(isValidDisplayName(invalid)).toBe(false);
    }
  });
});

describe("normalizeDisplayName", () => {
  it("trims and collapses whitespace", () => {
    expect(normalizeDisplayName("  Мара \t К. ")).toBe("Мара К.");
  });
});
