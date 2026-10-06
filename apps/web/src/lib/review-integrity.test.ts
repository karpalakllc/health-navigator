import { describe, expect, it } from "vitest";
import {
  aspectLabel,
  aspectsForUpstream,
  monthLabel,
  periodLabel,
  removalCategoryLabel,
} from "@/lib/review-integrity";

describe("review integrity helpers", () => {
  it("labels removal categories, with „друго“ for anything unknown", () => {
    expect(removalCategoryLabel("false_information")).toBe("лажни информации");
    expect(removalCategoryLabel("illegal")).toBe("незаконска содржина");
    expect(removalCategoryLabel(null)).toBe("друго");
    expect(removalCategoryLabel("toString")).toBe("друго");
  });

  it("labels aspects and refuses unknown codes", () => {
    expect(aspectLabel("waiting_time")).toBe("Време на чекање");
    expect(aspectLabel("staff")).toBe("Персонал");
    expect(aspectLabel("price")).toBeNull();
  });

  it("names three-month periods without time-zone drift", () => {
    expect(periodLabel("2025-11-01", "2026-01-31")).toBe("ное 2025 – јан 2026");
    expect(periodLabel("2026-02-01", "2026-04-30")).toBe("фев – апр 2026");
    expect(monthLabel("2026-10")).toBe("октомври 2026");
  });

  it("passes only a plain map of whole-number ratings upstream", () => {
    expect(aspectsForUpstream({ communication: 5, respect: 2 })).toEqual({
      communication: 5,
      respect: 2,
    });
    expect(
      aspectsForUpstream({ communication: "5", "bad key": 3, staff: 4.5 }),
    ).toBeUndefined();
    expect(aspectsForUpstream([5, 4])).toBeUndefined();
    expect(aspectsForUpstream(null)).toBeUndefined();
    expect(aspectsForUpstream({})).toBeUndefined();
  });
});
