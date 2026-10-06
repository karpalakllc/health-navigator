import { describe, expect, it } from "vitest";
import { formatRelativeDay } from "@/lib/relative-day";

// 2026-10-06 10:00 in Skopje (UTC+2 in October).
const NOW = new Date("2026-10-06T08:00:00Z");

describe("formatRelativeDay", () => {
  it.each([
    ["2026-10-06T06:00:00Z", "денес"],
    ["2026-10-05T21:50:00Z", "вчера"], // 23:50 Skopje the day before
    ["2026-10-05T22:10:00Z", "денес"], // 00:10 Skopje the same day
    ["2026-10-03T08:00:00Z", "пред 3 дена"],
    ["2026-09-29T08:00:00Z", "пред 1 недела"],
    ["2026-09-15T08:00:00Z", "пред 3 недели"],
    ["2026-08-31T08:00:00Z", "пред 1 месец"],
    ["2026-04-06T08:00:00Z", "пред 6 месеци"],
    ["2025-10-01T08:00:00Z", "пред 1 година"],
    ["2023-10-01T08:00:00Z", "пред 3 години"],
    ["2026-10-07T08:00:00Z", "денес"], // clock skew: never „пред -1 ден“
  ])("%s → %s", (iso, expected) => {
    expect(formatRelativeDay(iso, NOW)).toBe(expected);
  });

  it("returns null for a missing or unreadable date", () => {
    expect(formatRelativeDay(null, NOW)).toBeNull();
    expect(formatRelativeDay("not a date", NOW)).toBeNull();
  });
});
