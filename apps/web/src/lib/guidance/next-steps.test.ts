import { existsSync, readdirSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import type { GuidanceOutcomeV2, GuidanceState } from "@/lib/api/guidance-v2";
import {
  FLOW_FIRST_AID_TOPICS,
  firstAidLinksForResult,
  funnelStepsFor,
  guidanceFeedbackItem,
  urgentCareLinkFor,
} from "@/lib/guidance/next-steps";

const outcome = (
  level: GuidanceOutcomeV2["level"],
  setting = "on_call",
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
  care: { setting, specialties: [], facility_types: [] },
});

describe("urgentCareLinkFor", () => {
  it("sends an emergency to emergency departments in the session's city", () => {
    expect(urgentCareLinkFor(outcome("emergency_now"), "Битола")).toBe(
      "/urgent-care/bitola?type=ed",
    );
    expect(urgentCareLinkFor(outcome("emergency_now"), "")).toBe(
      "/urgent-care?type=ed",
    );
  });

  it("sends a same-day outcome to every urgent service, a dental one to dental emergency", () => {
    expect(urgentCareLinkFor(outcome("urgent_same_day"), "Струмица")).toBe(
      "/urgent-care/strumica",
    );
    expect(urgentCareLinkFor(outcome("urgent_same_day"), null)).toBe(
      "/urgent-care",
    );
    expect(
      urgentCareLinkFor(outcome("urgent_same_day", "dentist"), "Bitola"),
    ).toBe("/urgent-care/bitola?type=dental");
  });

  it("links nothing for less urgent levels", () => {
    for (const level of [
      "see_doctor_24_48h",
      "see_gp_this_week",
      "pharmacy_advice",
      "self_care_with_safety_net",
    ] as const) {
      expect(urgentCareLinkFor(outcome(level), "Битола")).toBeNull();
    }
  });
});

describe("guidanceFeedbackItem", () => {
  it("names the flow and the level", () => {
    expect(guidanceFeedbackItem("chest-pain", "urgent_same_day")).toBe(
      "guidance:chest-pain:outcome:urgent_same_day",
    );
    expect(guidanceFeedbackItem(null, "emergency_now")).toBe(
      "guidance:global:outcome:emergency_now",
    );
  });

  it("refuses a key the API would not accept", () => {
    expect(guidanceFeedbackItem("Глава", "emergency_now")).toBeNull();
  });
});

describe("funnelStepsFor", () => {
  const question = (pathLength: number): GuidanceState => ({
    session_id: "s",
    stage: "question",
    flows: [{ key: "headache", title: "Главоболка" }],
    flow: { key: "headache", title: "Главоболка", position: 0 },
    node: { id: "q_fever", type: "question", kind: "yes_no", text: "?" },
    progress: { answered: pathLength, remaining_max: 1 },
    path: Array.from({ length: pathLength }, (_, i) => ({
      node: { id: `q_${i}`, type: "question" as const },
      values: ["yes"],
    })),
  });

  it("counts the flow's start with its first question", () => {
    expect(funnelStepsFor(question(0), () => 0)).toEqual([
      { funnel: "guidance:headache", step: "start", depth: 0 },
      { funnel: "guidance:headache", step: "q_fever", depth: 1 },
    ]);
  });

  it("counts a later question at its position on the path", () => {
    expect(funnelStepsFor(question(2), () => 0)).toEqual([
      { funnel: "guidance:headache", step: "q_fever", depth: 3 },
    ]);
  });

  it("counts each flow's outcome after its answers, never a global one", () => {
    const result: GuidanceState = {
      session_id: "s",
      stage: "result",
      emergency_stopped: false,
      level: "urgent_same_day",
      reason: "answers",
      flows: [],
      outcomes: [
        {
          flow: { key: "headache", title: "Главоболка" },
          outcome: outcome("urgent_same_day"),
        },
        { flow: null, outcome: outcome("emergency_now") },
      ],
    };

    expect(
      funnelStepsFor(result, (key) => (key === "headache" ? 2 : 0)),
    ).toEqual([
      {
        funnel: "guidance:headache",
        step: "outcome:urgent_same_day",
        depth: 3,
      },
    ]);
  });

  it("counts nothing before the questions", () => {
    expect(
      funnelStepsFor({ session_id: "s", stage: "demographics" }, () => 0),
    ).toEqual([]);
  });
});

describe("first aid on emergency results", () => {
  it("links nothing while every guide is a draft", () => {
    expect(
      firstAidLinksForResult([
        {
          flow: { key: "chest-pain", title: "Болка во градите" },
          outcome: outcome("emergency_now"),
        },
      ]),
    ).toEqual([]);
  });

  it("maps only flows that exist", () => {
    const dir = resolve(
      __dirname,
      "../../../../api/database/data/triage/flows",
    );
    if (!existsSync(dir)) return;
    const keys = readdirSync(dir).map((file) => file.replace(/\.json$/, ""));

    for (const key of Object.keys(FLOW_FIRST_AID_TOPICS)) {
      expect(keys).toContain(key);
    }
  });
});
