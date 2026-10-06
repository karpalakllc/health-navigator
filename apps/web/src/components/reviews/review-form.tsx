"use client";

import { useRouter } from "next/navigation";
import { useId, useState } from "react";
import { StarRatingInput } from "@/components/reviews/star-rating-input";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Textarea } from "@/components/ui/field";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { t } from "@/i18n/t";
import { isValidReviewRating } from "@/lib/rating";

type ReviewFormProps = {
  kind: "doctor" | "facility" | "pharmacy";
  slug: string;
  /** Called once the API accepted the review, before the page refreshes. */
  onSubmitted?: () => void;
};

export function ReviewForm({ kind, slug, onSubmitted }: ReviewFormProps) {
  const router = useRouter();
  // No default: an untouched form must not submit a five-star review.
  const [rating, setRating] = useState<number | null>(null);
  const [ratingError, setRatingError] = useState(false);
  const ratingErrorId = useId();
  const titleId = useId();
  const [body, setBody] = useState("");
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
        }),
      });

      const payload = await response.json();

      if (!response.ok) {
        const message =
          payload.errors?.review?.[0] ??
          payload.message ??
          t("reviews.submitError");
        setError(message);
        return;
      }

      setSuccess(true);
      setBody("");
      setRating(null);
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
      noValidate
      onSubmit={handleSubmit}
      className="flex flex-col gap-5"
    >
      <div className="flex flex-col gap-1">
        <h3 id={titleId} className="type-h3 text-ink">
          {t("reviews.submitTitle")}
        </h3>
        <p className="type-meta text-ink-2">{t("reviews.pending")}</p>
      </div>
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
      {error ? <FormError>{error}</FormError> : null}
      <FormSuccess>{success ? t("reviews.submitSuccess") : null}</FormSuccess>
      <Button
        type="submit"
        size="lg"
        loading={pending}
        disabled={pending}
        className="self-stretch sm:self-start"
      >
        {pending ? t("common.submitting") : t("reviews.submit")}
      </Button>
    </Card>
  );
}
