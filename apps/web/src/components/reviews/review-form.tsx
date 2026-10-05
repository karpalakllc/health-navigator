"use client";

import { useRouter } from "next/navigation";
import { useId, useState } from "react";
import { filterInputClassName } from "@/components/directory/filter-form";
import { StarRatingInput } from "@/components/reviews/star-rating-input";
import { t } from "@/i18n/t";
import { isValidReviewRating } from "@/lib/rating";
import { FormError, FormSuccess } from "@/components/ui/form-message";

type ReviewFormProps = {
  kind: "doctor" | "facility" | "pharmacy";
  slug: string;
};

export function ReviewForm({ kind, slug }: ReviewFormProps) {
  const router = useRouter();
  // No default: an untouched form must not submit a five-star review.
  const [rating, setRating] = useState<number | null>(null);
  const [ratingError, setRatingError] = useState(false);
  const ratingErrorId = useId();
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
      router.refresh();
    } catch {
      setError(t("reviews.submitErrorRetry"));
    } finally {
      setPending(false);
    }
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="grid gap-3 rounded-xl border border-border bg-card p-4"
    >
      <p className="text-sm font-semibold text-foreground">
        {t("reviews.submitTitle")}
      </p>
      <p className="text-xs text-muted-foreground">{t("reviews.pending")}</p>
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
        <FormError id={ratingErrorId}>{t("reviews.ratingRequired")}</FormError>
      ) : null}
      <label className="grid gap-1 text-sm">
        <span>{t("reviews.body")}</span>
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          rows={4}
          className={filterInputClassName}
        />
      </label>
      {error ? <FormError>{error}</FormError> : null}
      <FormSuccess>{success ? t("reviews.submitSuccess") : null}</FormSuccess>
      <button
        type="submit"
        disabled={pending}
        className="min-h-[44px] rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
      >
        {pending ? t("common.submitting") : t("reviews.submit")}
      </button>
    </form>
  );
}
