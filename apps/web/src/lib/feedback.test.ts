import { describe, expect, it } from "vitest";
import {
  feedbackApiBody,
  feedbackApiPath,
  isFeedbackItem,
  parseFeedbackMessage,
} from "@/lib/feedback";

describe("feedback vocabulary", () => {
  it("accepts slug keys in the known namespaces only", () => {
    for (const ok of [
      "guide:kako-do-uput",
      "urgent-care:bitola",
      "guidance:headache:outcome:see_gp_this_week",
      "page:about",
    ]) {
      expect(isFeedbackItem(ok)).toBe(true);
    }
    for (const bad of [
      "",
      "guide",
      "other:x",
      "Guide:x",
      "guide:главоболка",
      "guide:x y",
      "guide:a:b:c:d",
      `guide:${"a".repeat(96)}`,
      42,
    ]) {
      expect(isFeedbackItem(bad)).toBe(false);
    }
  });

  it("rebuilds messages field by field and drops anything else", () => {
    expect(
      parseFeedbackMessage({
        kind: "vote",
        item: "guide:x",
        helpful: true,
        text: "Супер",
      }),
    ).toEqual({ kind: "vote", item: "guide:x", helpful: true });
    expect(
      parseFeedbackMessage({ kind: "vote", item: "guide:x", helpful: "yes" }),
    ).toBeNull();
    expect(
      parseFeedbackMessage({ kind: "comment", item: "guide:x" }),
    ).toBeNull();
    expect(parseFeedbackMessage(null)).toBeNull();
  });

  it("takes reasons from the list that matches the answer", () => {
    expect(
      parseFeedbackMessage({
        kind: "reasons",
        item: "urgent-care:bitola",
        helpful: false,
        reasons: ["not-found", "outdated"],
      }),
    ).toEqual({
      kind: "reasons",
      item: "urgent-care:bitola",
      helpful: false,
      reasons: ["not-found", "outdated"],
    });

    const reasons = (list: unknown[], helpful = false) =>
      parseFeedbackMessage({
        kind: "reasons",
        item: "guide:x",
        helpful,
        reasons: list,
      });

    expect(reasons(["clear"])).toBeNull();
    expect(reasons(["unclear"], true)).toBeNull();
    expect(reasons([])).toBeNull();
    expect(reasons(["unclear", "unclear"])).toBeNull();
    expect(
      reasons(["unclear", "not-found", "outdated", "wrong-info"]),
    ).toBeNull();
    expect(reasons(["Не најдов болница"])).toBeNull();
  });

  it("checks steps: funnel, step id and depth", () => {
    const step = (funnel: unknown, id: unknown, depth: unknown) =>
      parseFeedbackMessage({ kind: "step", funnel, step: id, depth });

    expect(step("guidance:headache", "outcome:emergency_now", 3)).toEqual({
      kind: "step",
      funnel: "guidance:headache",
      step: "outcome:emergency_now",
      depth: 3,
    });
    expect(step("guide:x", "start", 0)).toBeNull();
    expect(step("guidance:headache", "Што ве боли?", 1)).toBeNull();
    expect(step("guidance:headache", "start", -1)).toBeNull();
    expect(step("guidance:headache", "start", 1.5)).toBeNull();
    expect(step("guidance:headache", "start", 201)).toBeNull();
  });

  it("maps each message to its API endpoint and body", () => {
    const vote = { kind: "vote", item: "guide:x", helpful: false } as const;
    expect(feedbackApiPath(vote)).toBe("/feedback");
    expect(feedbackApiBody(vote)).toEqual({ item: "guide:x", helpful: false });
    expect(
      feedbackApiPath({
        kind: "reasons",
        item: "guide:x",
        helpful: true,
        reasons: ["clear"],
      }),
    ).toBe("/feedback/reasons");
    expect(
      feedbackApiPath({
        kind: "step",
        funnel: "guidance:x",
        step: "start",
        depth: 0,
      }),
    ).toBe("/feedback/steps");
  });
});
