import {
  firstAidLinksForTopic,
  type FirstAidLink,
  type FirstAidTopic,
} from "@/content/first-aid";
import type {
  FlowRef,
  GuidanceOutcomeV2,
  GuidanceState,
  OutcomeLevel,
} from "@/lib/api/guidance-v2";
import { isFeedbackItem } from "@/lib/feedback";
import { urgentCareHref } from "@/lib/urgent-care";

/*
 * What a guidance result links to besides the directory: first-aid guides
 * while waiting for 194 (docs/first-aid.md link contract), „Каде веднаш“
 * (docs/urgent-care.md § 1) and the anonymous „Дали ви помогна?“ / drop-off
 * counters (docs/urgent-care.md § 4).
 */

/**
 * Flow key → the first-aid topics that help while waiting for 194, most
 * specific first. Only flows whose emergencies have a matching guide; the
 * rest show none.
 */
export const FLOW_FIRST_AID_TOPICS: Readonly<
  Record<string, readonly FirstAidTopic[]>
> = {
  "chest-pain": ["chest-pain"],
  palpitations: ["chest-pain", "fainting"],
  "high-blood-pressure": ["stroke", "chest-pain"],
  headache: ["stroke", "seizure"],
  "dizziness-fainting": ["fainting", "stroke"],
  "confusion-older": ["stroke", "low-blood-sugar"],
  "diabetes-blood-sugar": ["low-blood-sugar"],
  "allergic-reaction": ["anaphylaxis"],
  "asthma-attack": ["not-breathing"],
  "shortness-of-breath": ["not-breathing"],
  "child-breathing": ["choking", "not-breathing"],
  "head-injury-adult": ["head-injury"],
  "head-injury-child": ["head-injury"],
  "falls-older": ["head-injury", "bleeding"],
  burns: ["burn"],
  "wounds-bleeding": ["bleeding"],
  "limb-injury": ["bleeding"],
  "fever-infant-child": ["seizure"],
  "pregnancy-concerns": ["seizure"],
};

type ResultOutcome = { flow: FlowRef | null; outcome: GuidanceOutcomeV2 };

/**
 * Published first-aid guides for the emergency outcomes of a result (none
 * while every guide is still a draft). A crisis outcome links none.
 */
export function firstAidLinksForResult(
  outcomes: readonly ResultOutcome[],
): FirstAidLink[] {
  const links: FirstAidLink[] = [];
  const seen = new Set<string>();

  for (const { flow, outcome } of outcomes) {
    if (outcome.level !== "emergency_now" || outcome.crisis || !flow) continue;

    for (const topic of FLOW_FIRST_AID_TOPICS[flow.key] ?? []) {
      for (const link of firstAidLinksForTopic(topic)) {
        if (seen.has(link.slug)) continue;
        seen.add(link.slug);
        links.push(link);
      }
    }
  }

  return links;
}

/**
 * „Каде веднаш“ for an outcome that needs care today: emergency departments
 * after a call to 194, every urgent service (or dental emergency) for the
 * same day. Null for less urgent levels.
 */
export function urgentCareLinkFor(
  outcome: Pick<GuidanceOutcomeV2, "level" | "care">,
  city: string | null | undefined,
): string | null {
  if (outcome.level === "emergency_now") {
    return urgentCareHref({ city, type: "ed" });
  }

  if (outcome.level === "urgent_same_day") {
    return urgentCareHref({
      city,
      type: outcome.care.setting === "dentist" ? "dental" : null,
    });
  }

  return null;
}

/** `guidance:<flow>:outcome:<level>`, or null when it would not be accepted. */
export function guidanceFeedbackItem(
  flowKey: string | null | undefined,
  level: OutcomeLevel,
): string | null {
  const item = `guidance:${flowKey ?? "global"}:outcome:${level}`;

  return isFeedbackItem(item) ? item : null;
}

export type FunnelStep = { funnel: string; step: string; depth: number };

/**
 * The drop-off steps a state means: a flow's start (depth 0) with its first
 * question, each question at its position on the flow's path, and each
 * flow's outcome after its last answer. `answeredIn(flowKey)` counts the
 * questions this tab answered in a flow (the result state carries no path).
 */
export function funnelStepsFor(
  state: GuidanceState,
  answeredIn: (flowKey: string) => number,
): FunnelStep[] {
  if (state.stage === "question") {
    const funnel = `guidance:${state.flow.key}`;
    const depth = state.path.length + 1;
    const steps: FunnelStep[] = [];
    if (state.path.length === 0)
      steps.push({ funnel, step: "start", depth: 0 });
    steps.push({ funnel, step: state.node.id, depth });

    return steps;
  }

  if (state.stage === "result") {
    return state.outcomes.flatMap(({ flow, outcome }) =>
      flow
        ? [
            {
              funnel: `guidance:${flow.key}`,
              step: `outcome:${outcome.level}`,
              depth: answeredIn(flow.key) + 1,
            },
          ]
        : [],
    );
  }

  return [];
}
