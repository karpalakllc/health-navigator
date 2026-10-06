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
import { Notice } from "@/components/ui/notice";
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
      <div className="flex flex-col gap-1">
        <p className="type-label text-ink" aria-hidden="true">
          {t("reviews.rating")}
        </p>
        <StarRatingInput
          value={rating}
          onChange={(value) => {
            setRating(value);
            setRatingError(false);
          }}
          disabled={pending}
          invalid={ratingError}
          errorId={ratingErrorId}
        />
        {ratingError ? (
          <FormError id={ratingErrorId}>
            {t("reviews.ratingRequired")}
          </FormError>
        ) : null}
      </div>
      <Textarea
        label={t("reviews.body")}
        value={body}
        onChange={(e) => setBody(e.target.value)}
        rows={5}
      />
      {/* The optional extras come after the essentials (stars, comment). */}
      <AspectRatingInput
        kind={kind}
        value={aspects}
        onChange={setAspects}
        disabled={pending}
        defaultOpen={Object.keys(aspects).length > 0}
      />
      {error ? <FormError>{error}</FormError> : null}
      <FormSuccess>{success ? t("reviews.submitSuccess") : null}</FormSuccess>
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
    </Card>
  );
}
