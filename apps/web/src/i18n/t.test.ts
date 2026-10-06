import { describe, expect, it } from "vitest";
import { formatForumReplyCount } from "@/lib/format";
import { isMacedonianOne, tCount } from "@/i18n/t";

describe("tCount", () => {
  it.each([
    [1, "1 тема"],
    [21, "21 тема"],
    [101, "101 тема"],
    [0, "0 теми"],
    [2, "2 теми"],
    [11, "11 теми"],
    [111, "111 теми"],
  ])("uses the right number for %i", (count, expected) => {
    expect(tCount("forum.topicsCount", count)).toBe(expected);
  });

  it.each([
    ["facilities.resultsCount", "1 установа", "3 установи"],
    ["doctors.resultsCount", "1 профил", "3 профили"],
    ["home.specialtyDoctorCount", "1 лекар", "3 лекари"],
    ["pharmacies.resultsCount", "1 аптека", "3 аптеки"],
    [
      "search.resultsTotalLine",
      "1 запис во категоријата.",
      "3 записи во категоријата.",
    ],
  ] as const)("%s has a singular", (key, one, other) => {
    expect(tCount(key, 1)).toBe(one);
    expect(tCount(key, 3)).toBe(other);
  });

  it("falls back to the plural string when there is no singular key", () => {
    expect(tCount("forum.activityMinutesAgo", 1)).toBe("пред 1 мин.");
  });

  it("formats forum reply counts by the same rule", () => {
    expect(formatForumReplyCount(1)).toBe("1 одговор");
    expect(formatForumReplyCount(21)).toBe("21 одговор");
    expect(formatForumReplyCount(5)).toBe("5 одговори");
  });

  it("treats only whole numbers as singular", () => {
    expect(isMacedonianOne(1.5)).toBe(false);
  });
});
