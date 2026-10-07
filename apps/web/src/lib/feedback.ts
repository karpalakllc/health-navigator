import { privacySignalOn } from "@/lib/ux/privacy-signals";

/*
 * „Дали ви помогна?“ and step drop-off (docs/urgent-care.md § Feedback).
 * The closed vocabularies below must match apps/api/config/feedback.php
 * (FeedbackVocabularyParityTest reads this file).
 */

export const FEEDBACK_ITEM_PATTERN =
  /^(guide|urgent-care|guidance|page):[a-z0-9][a-z0-9-]{0,47}(:[a-z0-9][a-z0-9_-]{0,47}){0,2}$/;

export const FEEDBACK_FUNNEL_PATTERN =
  /^(guidance|urgent-care|page):[a-z0-9][a-z0-9-]{0,47}(:[a-z0-9][a-z0-9_-]{0,47})?$/;

export const FEEDBACK_STEP_PATTERN = /^[a-z0-9][a-z0-9_.:-]{0,63}$/;

export const FEEDBACK_MAX_LENGTH = 96;
export const FEEDBACK_MAX_DEPTH = 200;
export const FEEDBACK_MAX_REASONS = 3;

// feedback-helpful:start
export const HELPFUL_REASONS = ["clear", "found-place", "next-step"] as const;
// feedback-helpful:end
// feedback-not-helpful:start
export const NOT_HELPFUL_REASONS = [
  "unclear",
  "not-found",
  "wrong-info",
  "outdated",
  "not-relevant",
] as const;
// feedback-not-helpful:end

export type HelpfulReason = (typeof HELPFUL_REASONS)[number];
export type NotHelpfulReason = (typeof NOT_HELPFUL_REASONS)[number];
export type FeedbackReason = HelpfulReason | NotHelpfulReason;

export function isFeedbackItem(value: unknown): value is string {
  return (
    typeof value === "string" &&
    value.length <= FEEDBACK_MAX_LENGTH &&
    FEEDBACK_ITEM_PATTERN.test(value)
  );
}

/** One of the three messages the same-origin relay accepts. */
export type FeedbackMessage =
  | { kind: "vote"; item: string; helpful: boolean }
  | {
      kind: "reasons";
      item: string;
      helpful: boolean;
      reasons: FeedbackReason[];
    }
  | { kind: "step"; funnel: string; step: string; depth: number };

/**
 * Rebuilds a relay body field by field; anything outside the vocabulary
 * gives null (the relay then drops it).
 */
export function parseFeedbackMessage(input: unknown): FeedbackMessage | null {
  if (!input || typeof input !== "object") return null;
  const body = input as Record<string, unknown>;

  if (body.kind === "vote") {
    if (!isFeedbackItem(body.item) || typeof body.helpful !== "boolean") {
      return null;
    }
    return { kind: "vote", item: body.item, helpful: body.helpful };
  }

  if (body.kind === "reasons") {
    if (!isFeedbackItem(body.item) || typeof body.helpful !== "boolean") {
      return null;
    }
    const allowed: readonly string[] = body.helpful
      ? HELPFUL_REASONS
      : NOT_HELPFUL_REASONS;
    if (
      !Array.isArray(body.reasons) ||
      body.reasons.length === 0 ||
      body.reasons.length > FEEDBACK_MAX_REASONS ||
      new Set(body.reasons).size !== body.reasons.length ||
      !body.reasons.every((r) => typeof r === "string" && allowed.includes(r))
    ) {
      return null;
    }
    return {
      kind: "reasons",
      item: body.item,
      helpful: body.helpful,
      reasons: body.reasons as FeedbackReason[],
    };
  }

  if (body.kind === "step") {
    const { funnel, step, depth } = body;
    if (
      typeof funnel !== "string" ||
      funnel.length > FEEDBACK_MAX_LENGTH ||
      !FEEDBACK_FUNNEL_PATTERN.test(funnel) ||
      typeof step !== "string" ||
      !FEEDBACK_STEP_PATTERN.test(step) ||
      typeof depth !== "number" ||
      !Number.isInteger(depth) ||
      depth < 0 ||
      depth > FEEDBACK_MAX_DEPTH
    ) {
      return null;
    }
    return { kind: "step", funnel, step, depth };
  }

  return null;
}

/** The API path a relayed message goes to. */
export function feedbackApiPath(message: FeedbackMessage): string {
  switch (message.kind) {
    case "vote":
      return "/feedback";
    case "reasons":
      return "/feedback/reasons";
    case "step":
      return "/feedback/steps";
  }
}

/** The body the API expects (no `kind`). */
export function feedbackApiBody(
  message: FeedbackMessage,
): Record<string, unknown> {
  const { kind: _kind, ...rest } = message;
  void _kind;
  return rest;
}

/**
 * Sends one message to the same-origin relay. Best effort: errors are
 * swallowed, nothing is retried or stored.
 */
export async function sendFeedback(message: FeedbackMessage): Promise<void> {
  try {
    await fetch("/api/feedback", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(message),
      keepalive: true,
      credentials: "omit",
    });
  } catch {
    // Statistics are best effort.
  }
}

/**
 * For the guidance engine: one call per step the visitor reaches
 * (`guidance:<flow-slug>`, a node key or `outcome:<level>`, its depth).
 * Sends nothing with Global Privacy Control or Do Not Track on.
 */
export function recordFunnelStep(
  funnel: string,
  step: string,
  depth: number,
): void {
  // Passive statistics: Global Privacy Control / Do Not Track switch them off.
  if (typeof window === "undefined" || privacySignalOn(window)) return;
  const message = parseFeedbackMessage({ kind: "step", funnel, step, depth });
  if (message) void sendFeedback(message);
}
