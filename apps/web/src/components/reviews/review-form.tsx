"use client";

import { useRouter } from "next/navigation";
import { useId, useState } from "react";
import {
  AspectRatingInput,
  type AspectRatings,
} from "@/components/reviews/aspect-rating-input";
import { StarRatingInput } from "@/components/reviews/star-rating-input";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Textarea } from "@/components/ui/field";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import { Notice } from "@/components/ui/notice";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";
import type { ViewerReview } from "@/lib/api/types";
import { isValidReviewRating } from "@/lib/rating";

type ReviewFormProps = {
  kind: "doctor" | "facility" | "pharmacy";
  slug: string;
  /** Called once the API accepted the review, before the page refreshes. */
  onSubmitted?: () => void;
  /**
   * The member's own review, refused before publication, that they may edit
   * and send once more: the form starts from it and says why it was refused.
   */
  previous?: ViewerReview | null;
};

/** Shortest comment the API accepts (StoreReviewRequest: min:10). */
const MIN_BODY = 10;

/**
 * Stars first (W8-B): the form opens on one question and five stars, so a
 * rating alone takes seconds. Choosing a star reveals the optional extras —
 * aspect ratings (folded) and a comment — and the send button works at every
 * step. Nothing is published without moderation: every review is sent as
 * pending, whatever it carries.
 */
export function ReviewForm({
  kind,
  slug,
  onSubmitted,
  previous,
}: ReviewFormProps) {
  const router = useRouter();
  const isResubmit = Boolean(previous?.can_resubmit);
  // No default: an untouched form must not submit a five-star review.
  const [rating, setRating] = useState<number | null>(
    isResubmit ? (previous?.rating ?? null) : null,
  );
  const [ratingError, setRatingError] = useState(false);
  const ratingErrorId = useId();
  const titleId = useId();
  const [body, setBody] = useState(isResubmit ? (previous?.body ?? "") : "");
  // Optional sub-ratings, folded away by default (W5-I).
  const [aspects, setAspects] = useState<AspectRatings>(
    isResubmit ? ((previous?.aspects as AspectRatings | undefined) ?? {}) : {},
  );
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [pending, setPending] = useState(false);
  // The extras appear once a star is chosen (and stay, even if the form
  // later shows an error), or at once when editing a refused review.
  const [expanded, setExpanded] = useState(isResubmit);

  const steps = [
    {
      label: t("reviewFlow.stepRating"),
      optional: false,
      done: rating !== null,
    },
    {
      label: t("reviewFlow.stepDetails"),
      optional: true,
      done: Object.keys(aspects).length > 0,
    },
    {
      label: t("reviewFlow.stepComment"),
      optional: true,
      done: body.trim().length >= MIN_BODY,
    },
  ];

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setSuccess(false);

    if (!isValidReviewRating(rating)) {
      setRatingError(true);
      return;
    }

    setPending(true);

    try {
      const response = await fetch("/api/reviews", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          kind,
          slug,
          rating,
          body: body.trim() === "" ? null : body.trim(),
          ...(Object.keys(aspects).length > 0 ? { aspects } : {}),
        }),
      });

      const payload = await response.json();

      if (!response.ok) {
        const message =
          payload.errors?.review?.[0] ??
          payload.errors?.aspects?.[0] ??
          payload.errors?.body?.[0] ??
          payload.message ??
          t("reviews.submitError");
        setError(message);
        return;
      }

      setSuccess(true);
      setBody("");
      setRating(null);
      setAspects({});
      onSubmitted?.();
      router.refresh();
    } catch {
      setError(t("reviews.submitErrorRetry"));
    } finally {
      setPending(false);
    }
  }

  return (
    <Card
      as="form"
      id="review-form"
      aria-labelledby={titleId}
      onSubmit={handleSubmit}
      className="flex flex-col gap-5"
    >
      <div className="flex flex-col gap-1">
        <h3 id={titleId} className="type-h3 text-ink">
          {isResubmit ? t("reviews.resubmitTitle") : t("reviews.submitTitle")}
        </h3>
        <p className="type-meta text-ink-2">
          {isResubmit ? t("reviews.resubmitLastChance") : t("reviews.pending")}
        </p>
      </div>
      {isResubmit && previous?.rejection_note ? (
        <Notice tone="info" title={t("reviews.resubmitReasonTitle")}>
          <p className="whitespace-pre-line">{previous.rejection_note}</p>
        </Notice>
      ) : null}

      <ol
        aria-label={t("reviewFlow.stepsLabel")}
        className="m-0 flex list-none flex-wrap gap-2 p-0"
      >
        {steps.map((step, index) => (
          <li
            key={step.label}
            aria-current={
              !step.done && steps.slice(0, index).every((s) => s.done)
                ? "step"
                : undefined
            }
            className={cn(
              "inline-flex min-h-9 items-center gap-1.5 rounded-pill px-3 type-meta",
              step.done
                ? "bg-ink text-white"
                : index > 0 && !expanded
                  ? "bg-sand text-ink-2"
                  : "bg-chip-tint text-ink",
            )}
          >
            {step.done ? (
              <Icon name="check" size={16} />
            ) : (
              <span aria-hidden="true" className="font-semibold">
                {index + 1}
              </span>
            )}
            <span>
              {step.label}
              {step.optional ? ` (${t("reviewFlow.stepOptional")})` : null}
            </span>
            {step.done ? (
              <span className="sr-only">{t("reviewFlow.stepDone")}</span>
            ) : null}
          </li>
        ))}
      </ol>

      <div className="flex flex-col gap-2">
        <p className="type-h3 text-ink" aria-hidden="true">
          {t("reviewFlow.ratingQuestion")}
        </p>
        <StarRatingInput
          value={rating}
          onChange={(value) => {
            setRating(value);
            setRatingError(false);
            setExpanded(true);
          }}
          disabled={pending}
          invalid={ratingError}
          errorId={ratingErrorId}
        />
        {ratingError ? (
          <FormError id={ratingErrorId}>
            {t("reviews.ratingRequired")}
          </FormError>
        ) : (
          <p className="type-meta text-ink-2">
            {expanded
              ? t("reviewFlow.afterRating")
              : t("reviewFlow.ratingHint")}
          </p>
        )}
      </div>

      {expanded ? (
        <div className="motion-fade-in flex flex-col gap-5">
          {/* Step 2, folded: the optional aspect ratings. */}
          <AspectRatingInput
            kind={kind}
            value={aspects}
            onChange={setAspects}
            disabled={pending}
            defaultOpen={Object.keys(aspects).length > 0}
          />
          {/* Step 3: the optional comment. */}
          <Textarea
            label={t("reviews.body")}
            value={body}
            onChange={(e) => setBody(e.target.value)}
            rows={4}
          />
        </div>
      ) : null}

      {error ? <FormError>{error}</FormError> : null}
      <FormSuccess>{success ? t("reviews.submitSuccess") : null}</FormSuccess>
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
        <Button
          type="submit"
          size="lg"
          loading={pending}
          disabled={pending}
          className="self-stretch sm:self-start"
        >
          {pending
            ? t("common.submitting")
            : isResubmit
              ? t("reviews.resubmitSubmit")
              : t("reviews.submit")}
        </Button>
        <p className="flex items-center gap-2 type-meta text-ink-2">
          <Icon name="lock" size={16} className="shrink-0" />
          {t("reviewFlow.safety")}
        </p>
      </div>
    </Card>
  );
}
