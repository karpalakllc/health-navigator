"use client";

import { useRouter } from "next/navigation";
import { useEffect, useId, useState } from "react";
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
import { loginHref } from "@/lib/auth/login-href";
import {
  REVIEW_ANCHOR,
  cameFromSharedLink,
  clearReviewDraft,
  forgetSharedLanding,
  readReviewDraft,
  saveReviewDraft,
  skipSearchPrompt,
} from "@/lib/review-growth";
import { reviewsBasePath } from "@/components/reviews/review-paths";
import { t, tFormat } from "@/i18n/t";
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
  /**
   * Signed out: the form works the same, but „Продолжете кон најава“ keeps
   * what was filled in on this device (a day at most) and goes to sign-in,
   * which returns here with it restored.
   */
  guest?: boolean;
  /** The profile's name, kept with a guest's draft („Продолжете ја…“). */
  profileName?: string;
};

/** Longest comment the API accepts (StoreReviewRequest: max:2000). */
const MAX_BODY = 2000;

/** Shortest comment the API accepts (StoreReviewRequest: min:10). */
const MIN_BODY = 10;

/** Empty, or at least MIN_BODY characters once trimmed. */
function validBodyLength(value: string): boolean {
  const length = value.trim().length;

  return length === 0 || length >= MIN_BODY;
}

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
  guest = false,
  profileName,
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
  const bodyFieldId = useId();
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
  const [bodyError, setBodyError] = useState(false);
  // A draft kept across sign-in (or registration) was put back.
  const [restored, setRestored] = useState(false);
  const basePath = reviewsBasePath(kind, slug);

  // After hydration only: the draft lives in this browser's storage.
  useEffect(() => {
    if (isResubmit) {
      return;
    }

    const draft = readReviewDraft(kind, slug);

    if (!draft || (draft.rating === null && draft.body === "")) {
      return;
    }

    const frame = requestAnimationFrame(() => {
      setRating(draft.rating);
      setBody(draft.body);
      setAspects(draft.aspects as AspectRatings);
      setExpanded(true);
      setRestored(!guest);
    });

    return () => cancelAnimationFrame(frame);
  }, [kind, slug, guest, isResubmit]);

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
    ...(guest
      ? [{ label: t("reviewGrowth.stepLogin"), optional: false, done: false }]
      : []),
  ];

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setSuccess(false);

    const trimmed = body.trim();
    const tooShort = trimmed.length > 0 && trimmed.length < MIN_BODY;
    setBodyError(tooShort);

    if (!isValidReviewRating(rating)) {
      setRatingError(true);
      focusFirstStar();
      return;
    }

    if (tooShort) {
      document.getElementById(bodyFieldId)?.focus();
      return;
    }

    if (guest) {
      saveReviewDraft({
        kind,
        slug,
        name: profileName ?? null,
        path: basePath,
        rating,
        body: trimmed,
        aspects,
      });
      router.push(loginHref(`${basePath}#${REVIEW_ANCHOR}`));
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
          // Staff-only, and only with statistics consent (lib/review-growth).
          ...(cameFromSharedLink(kind, slug) ? { via_shared_link: true } : {}),
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
      setRestored(false);
      clearReviewDraft();
      forgetSharedLanding();
      skipSearchPrompt(kind, slug);
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

  function focusFirstStar() {
    document
      .getElementById("review-form")
      ?.querySelector<HTMLElement>('[role="radio"]')
      ?.focus();
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
      {restored ? (
        <Notice tone="success">{t("reviewGrowth.draftRestored")}</Notice>
      ) : null}
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
            id={bodyFieldId}
            label={t("reviews.body")}
            value={body}
            onChange={(e) => {
              setBody(e.target.value);
              if (bodyError && validBodyLength(e.target.value)) {
                setBodyError(false);
              }
            }}
            onBlur={() => setBodyError(!validBodyLength(body))}
            error={bodyError ? t("reviewGrowth.bodyTooShort") : undefined}
            maxLength={MAX_BODY}
            autoCapitalize="sentences"
            enterKeyHint="done"
            counter={
              body.length > 0
                ? tFormat("reviewGrowth.bodyCounter", { count: body.length })
                : null
            }
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
            : guest
              ? t("reviewGrowth.guestSubmit")
              : isResubmit
                ? t("reviews.resubmitSubmit")
                : t("reviews.submit")}
        </Button>
        <p className="flex items-center gap-2 type-meta text-ink-2">
          <Icon name="lock" size={16} className="shrink-0" />
          {t("reviewFlow.safety")}
        </p>
      </div>
      {guest ? (
        <p className="flex gap-2 type-meta text-ink-2">
          <Icon name="user" size={16} className="mt-0.5 shrink-0" />
          {t("reviewGrowth.guestNote")}
        </p>
      ) : null}
    </Card>
  );
}
