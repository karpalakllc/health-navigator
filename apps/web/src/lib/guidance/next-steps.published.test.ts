import { describe, expect, it, vi } from "vitest";

// The heart-attack guide signed off and published, as after clinician review.
vi.mock("@/content/first-aid/guides/sudden", async (importOriginal) => {
  const actual =
    await importOriginal<typeof import("@/content/first-aid/guides/sudden")>();
  return {
    ...actual,
    heartAttack: {
      ...actual.heartAttack,
      published: true,
      review: { status: "reviewed", reviewedOn: "2026-11-01" },
    },
  };
});

import type { GuidanceOutcomeV2 } from "@/lib/api/guidance-v2";
import { firstAidLinksForResult } from "@/lib/guidance/next-steps";

const outcome = (
  level: GuidanceOutcomeV2["level"],
  crisis = false,
): GuidanceOutcomeV2 => ({
  id: "o",
  level,
  crisis,
  title: "Наслов",
  summary: "Опис",
  reasons: [],
  do_now: [],
  watch_for: [],
  call: [],
  care: {
    setting: "emergency_department",
    specialties: [],
    facility_types: [],
  },
});

const chest = { key: "chest-pain", title: "Болка во градите" };

describe("first aid on emergency results once a guide is published", () => {
  it("links the published guide for the flow's emergency, once", () => {
    expect(
      firstAidLinksForResult([
        { flow: chest, outcome: outcome("emergency_now") },
        {
          flow: { key: "palpitations", title: "Палпитации" },
          outcome: outcome("emergency_now"),
        },
      ]),
    ).toEqual([
      {
        slug: "srcev-udar",
        title: expect.any(String),
        href: "/prva-pomos/srcev-udar#pomos",
      },
    ]);
  });

  it("links nothing for a less urgent outcome, a crisis or a global outcome", () => {
    expect(
      firstAidLinksForResult([
        { flow: chest, outcome: outcome("urgent_same_day") },
      ]),
    ).toEqual([]);
    expect(
      firstAidLinksForResult([
        { flow: chest, outcome: outcome("emergency_now", true) },
      ]),
    ).toEqual([]);
    expect(
      firstAidLinksForResult([
        { flow: null, outcome: outcome("emergency_now") },
      ]),
    ).toEqual([]);
  });
});
