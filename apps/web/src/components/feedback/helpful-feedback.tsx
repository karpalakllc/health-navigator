"use client";

import { useId, useState } from "react";
import { Button } from "@/components/ui/button";
import { FilterChip } from "@/components/ui/chip";
import { cn } from "@/lib/cn";
import {
  FEEDBACK_MAX_REASONS,
  HELPFUL_REASONS,
  NOT_HELPFUL_REASONS,
  isFeedbackItem,
  sendFeedback,
  type FeedbackReason,
} from "@/lib/feedback";
import { t, type MessageKey } from "@/i18n/t";

const REASON_LABELS: Record<FeedbackReason, MessageKey> = {
  clear: "feedback.reasonClear",
  "found-place": "feedback.reasonFoundPlace",
  "next-step": "feedback.reasonNextStep",
  unclear: "feedback.reasonUnclear",
  "not-found": "feedback.reasonNotFound",
  "wrong-info": "feedback.reasonWrongInfo",
  outdated: "feedback.reasonOutdated",
  "not-relevant": "feedback.reasonNotRelevant",
};

type State =
  | { step: "ask" }
  | { step: "reasons"; helpful: boolean; chosen: FeedbackReason[] }
  | { step: "done" };

/**
 * „Дали ви помогна?“ — yes / no, then up to three optional reason chips.
 * No free text and no account: only the item key, the answer and the chip
 * codes are sent, as anonymous daily counters (docs/urgent-care.md
 * § Feedback). The vote is sent at once, so leaving the page after „Да“ /
 * „Не“ still counts it; the chips are a second, optional message.
 *
 * `item` is a key like `guide:kako-do-uput`, `urgent-care:bitola` or
 * `guidance:<flow>:outcome:<level>`; an invalid key renders nothing.
 */
export function HelpfulFeedback({
  item,
  className,
}: {
  item: string;
  className?: string;
}) {
  const uid = useId();
  const [state, setState] = useState<State>({ step: "ask" });

  if (!isFeedbackItem(item)) {
    return null;
  }

  function vote(helpful: boolean) {
    void sendFeedback({ kind: "vote", item, helpful });
    setState({ step: "reasons", helpful, chosen: [] });
  }

  function toggle(reason: FeedbackReason) {
    setState((current) => {
      if (current.step !== "reasons") return current;
      const chosen = current.chosen.includes(reason)
        ? current.chosen.filter((r) => r !== reason)
        : current.chosen.length < FEEDBACK_MAX_REASONS
          ? [...current.chosen, reason]
          : current.chosen;
      return { ...current, chosen };
    });
  }

  function sendReasons() {
    if (state.step !== "reasons") return;
    if (state.chosen.length > 0) {
      void sendFeedback({
        kind: "reasons",
        item,
        helpful: state.helpful,
        reasons: state.chosen,
      });
    }
    setState({ step: "done" });
  }

  const headingId = `${uid}-title`;

  return (
    <section
      aria-labelledby={headingId}
      className={cn("rounded-card bg-sand p-5 lg:p-6", className)}
    >
      <h2 id={headingId} className="type-label text-ink">
        {t("feedback.question")}
      </h2>

      {state.step === "ask" ? (
        <div className="mt-3 flex flex-wrap gap-2">
          <Button
            variant="secondary"
            size="md"
            leadingIcon="thumbs-up"
            onClick={() => vote(true)}
          >
            {t("feedback.yes")}
          </Button>
          <Button variant="secondary" size="md" onClick={() => vote(false)}>
            {t("feedback.no")}
          </Button>
        </div>
      ) : null}

      {state.step === "reasons" ? (
        <div className="mt-2 flex flex-col gap-3">
          <p role="status" className="type-body text-ink">
            {t("feedback.thanks")}
          </p>
          <fieldset className="flex flex-col gap-2">
            <legend className="type-meta text-ink-2">
              {state.helpful
                ? t("feedback.reasonsYes")
                : t("feedback.reasonsNo")}
            </legend>
            <div className="flex flex-wrap gap-2">
              {(state.helpful ? HELPFUL_REASONS : NOT_HELPFUL_REASONS).map(
                (reason) => (
                  <FilterChip
                    key={reason}
                    selected={state.chosen.includes(reason)}
                    onClick={() => toggle(reason)}
                  >
                    {t(REASON_LABELS[reason])}
                  </FilterChip>
                ),
              )}
            </div>
          </fieldset>
          {state.chosen.length > 0 ? (
            <div>
              <Button variant="primary" size="md" onClick={sendReasons}>
                {t("feedback.send")}
              </Button>
            </div>
          ) : null}
        </div>
      ) : null}

      {state.step === "done" ? (
        <p role="status" className="mt-2 type-body text-ink">
          {t("feedback.sent")}
        </p>
      ) : null}

      <p className="mt-3 type-meta text-ink-2">{t("feedback.anonymous")}</p>
    </section>
  );
}
