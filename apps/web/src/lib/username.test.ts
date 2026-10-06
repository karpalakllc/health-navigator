import { describe, expect, it } from "vitest";
import {
  isTemporaryUsername,
  normalizeUsername,
  usernameFormatError,
} from "@/lib/username";

describe("username format", () => {
  it("accepts Latin or Macedonian Cyrillic names with digits and . _ -", () => {
    for (const ok of [
      "ana",
      "Ана",
      "marko_s",
      "Марко.С",
      "ana-marija",
      "jovan1990",
      "Çelë",
      "Ѓорѓи",
      "Ѕвездан",
    ]) {
      expect(usernameFormatError(ok)).toBeNull();
    }
  });

  it("names the rule a username breaks", () => {
    expect(usernameFormatError("ab")).toBe("usernames.errorLength");
    expect(usernameFormatError("a".repeat(31))).toBe("usernames.errorLength");
    expect(usernameFormatError("ana marija")).toBe("usernames.errorAlphabet");
    expect(usernameFormatError("Наталя")).toBe("usernames.errorAlphabet");
    expect(usernameFormatError("1marko")).toBe("usernames.errorStart");
    expect(usernameFormatError("ana__m")).toBe("usernames.errorSeparators");
    expect(usernameFormatError("Аdmin")).toBe("usernames.errorMixedScript");
  });

  it("normalises like the API (NFKC, trimmed)", () => {
    expect(normalizeUsername("  ｍａｒｋｏ ")).toBe("marko");
  });

  it("recognises the temporary placeholder", () => {
    expect(isTemporaryUsername("clen-k3x9p2")).toBe(true);
    expect(isTemporaryUsername("ana")).toBe(false);
  });
});
