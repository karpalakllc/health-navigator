import { afterEach, describe, expect, it, vi } from "vitest";
import {
  authorInitials,
  formatForumAuthorStats,
  formatForumDateTime,
  formatForumLastActivity,
  formatForumReplyCount,
} from "@/lib/format";

afterEach(() => {
  vi.useRealTimers();
});

describe("formatForumReplyCount", () => {
  it("uses the singular for Macedonian 'one' counts", () => {
    expect(formatForumReplyCount(1)).toBe("1 одговор");
    expect(formatForumReplyCount(21)).toBe("21 одговор");
  });

  it("uses the plural otherwise", () => {
    expect(formatForumReplyCount(0)).toBe("0 одговори");
    expect(formatForumReplyCount(5)).toBe("5 одговори");
    expect(formatForumReplyCount(11)).toBe("11 одговори");
  });
});

describe("formatForumAuthorStats", () => {
  it("joins the member year and the combined post count", () => {
    expect(
      formatForumAuthorStats({
        member_since: "2025-03-01T10:00:00Z",
        topics_count: 2,
        posts_count: 10,
      }),
    ).toBe("Член од 2025 · 12 објави");
  });

  it("reads the year in Europe/Skopje, not UTC", () => {
    // 23:30 UTC on 31 December is already 1 January in Skopje.
    expect(
      formatForumAuthorStats({
        member_since: "2024-12-31T23:30:00Z",
        topics_count: 0,
        posts_count: 1,
      }),
    ).toBe("Член од 2025 · 1 објава");
  });

  it("leaves out what it does not know", () => {
    expect(
      formatForumAuthorStats({
        member_since: null,
        topics_count: 0,
        posts_count: 3,
      }),
    ).toBe("3 објави");
    expect(
      formatForumAuthorStats({
        member_since: "not a date",
        topics_count: 0,
        posts_count: 0,
      }),
    ).toBe("");
  });
});

describe("formatForumDateTime", () => {
  it("is empty for a missing or invalid value", () => {
    expect(formatForumDateTime(null)).toBe("");
    expect(formatForumDateTime(undefined)).toBe("");
    expect(formatForumDateTime("")).toBe("");
    expect(formatForumDateTime("yesterday")).toBe("");
  });

  it("formats a valid timestamp with the year", () => {
    expect(formatForumDateTime("2025-06-15T12:00:00Z")).toContain("2025");
  });
});

describe("formatForumLastActivity", () => {
  const now = new Date("2026-10-06T12:00:00Z");
  const ago = (ms: number) => new Date(now.getTime() - ms).toISOString();
  const MINUTE = 60_000;

  function at(iso: string | null): string {
    vi.useFakeTimers();
    vi.setSystemTime(now);

    return formatForumLastActivity(iso);
  }

  it("says there is no activity yet without a timestamp", () => {
    expect(at(null)).toBe("Нема активност");
  });

  it("is empty for an invalid timestamp", () => {
    expect(at("soon")).toBe("");
  });

  it("walks from just now through minutes, hours and days", () => {
    expect(at(ago(20_000))).toBe("пред малку");
    expect(at(ago(5 * MINUTE))).toBe("пред 5 мин.");
    expect(at(ago(60 * MINUTE))).toBe("пред 1 час");
    expect(at(ago(3 * 60 * MINUTE))).toBe("пред 3 часа");
    expect(at(ago(24 * 60 * MINUTE))).toBe("пред 1 ден");
    expect(at(ago(6 * 24 * 60 * MINUTE))).toBe("пред 6 дена");
  });

  it("falls back to the full date after a week", () => {
    const iso = ago(10 * 24 * 60 * MINUTE);

    expect(at(iso)).toBe(formatForumDateTime(iso));
  });
});

describe("authorInitials", () => {
  it("takes the first letter of the first two words", () => {
    expect(authorInitials("марија костовска")).toBe("МК");
    expect(authorInitials("  Ана   Марија Петровска ")).toBe("АМ");
  });

  it("takes two letters of a single word", () => {
    expect(authorInitials("бојан")).toBe("БО");
  });

  it("falls back to a question mark for an empty name", () => {
    expect(authorInitials("   ")).toBe("?");
  });
});
