"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef } from "react";
import { SortSelect } from "@/components/directory/filter-controls";
import { Pagination } from "@/components/directory/pagination";
import { reviewsBasePath } from "@/components/reviews/review-paths";
import { ReviewForm } from "@/components/reviews/review-form";
import { ReviewList } from "@/components/reviews/review-list";
import { ReviewViewTracker } from "@/components/reviews/review-view-tracker";
import { ChooseUsernameNotice } from "@/components/usernames/choose-username-notice";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import { Notice } from "@/components/ui/notice";
import { StarRating } from "@/components/ui/star-rating";
import { Tag } from "@/components/ui/tag";
import { loginHref } from "@/lib/auth/login-href";
import type {
  PaginatedEnvelope,
  ReviewListItem,
  ViewerReview,
} from "@/lib/api/types";
import { t, tCount, tFormat } from "@/i18n/t";

type ReviewsPanelProps = {
  kind: "doctor" | "facility" | "pharmacy";
  slug: string;
  initial: PaginatedEnvelope<ReviewListItem>;
  viewerReview: ViewerReview | null | undefined;
  isLoggedIn: boolean;
  /** Still a temporary „clen-…“ name: the API refuses reviews and votes. */
  mustChooseUsername?: boolean;
  page: number;
  sort: string;
  rating: string;
};

export function ReviewsPanel({
  kind,
  slug,
  initial,
  viewerReview,
  isLoggedIn,
  mustChooseUsername = false,
  page,
  sort,
  rating,
}: ReviewsPanelProps) {
  const router = useRouter();
  const basePath = reviewsBasePath(kind, slug);
  // Set when the form posts; the refresh that follows swaps the form for the
  // pending card, and the viewport (and focus) must follow it there.
  const justSubmitted = useRef(false);
  const viewerStatus = viewerReview?.status ?? null;

  useEffect(() => {
    if (!justSubmitted.current) {
      return;
    }

    const target =
      viewerStatus === "pending"
        ? document.getElementById(PENDING_REVIEW_ID)
        : viewerStatus === "approved"
          ? document.getElementById("reviews-title")
          : null;

    if (!target) {
      return;
    }

    justSubmitted.current = false;
    if (!target.hasAttribute("tabindex")) {
      target.setAttribute("tabindex", "-1");
    }
    target.scrollIntoView?.({ block: "center" });
    target.focus({ preventScroll: true });
  }, [viewerStatus]);

  // A profile opened at #reviews / #review-form: the section can arrive after
  // the browser's own jump to the fragment (streaming), so jump again once.
  useEffect(() => {
    const hash = window.location.hash;

    if (hash !== "#reviews" && hash !== "#review-form") {
      return;
    }

    document.getElementById(hash.slice(1))?.scrollIntoView?.();
  }, []);

  function buildHref(next: { page?: number; sort?: string; rating?: string }) {
    const params = new URLSearchParams();
    const nextSort = next.sort ?? sort;
    const nextRating = next.rating ?? rating;
    const nextPage = next.page ?? page;

    if (nextSort && nextSort !== "newest") {
      params.set("review_sort", nextSort);
    }
    if (nextRating) {
      params.set("review_rating", nextRating);
    }
    if (nextPage > 1) {
      params.set("review_page", String(nextPage));
    }

    const query = params.toString();

    return query ? `${basePath}?${query}#reviews` : `${basePath}#reviews`;
  }

  const hasAny = initial.meta.total > 0 || rating !== "";

  return (
    <div className="flex flex-col gap-4">
      {hasAny ? (
        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="type-meta text-ink-2">
            {tFormat("reviews.shownOf", {
              shown: initial.data.length,
              total: initial.meta.total,
            })}
          </p>
          <div className="flex flex-wrap gap-2">
            <SortSelect
              label={t("reviews.filterRating")}
              value={rating}
              onChange={(value) =>
                router.push(buildHref({ rating: value, page: 1 }))
              }
              options={[
                { value: "", label: t("reviews.filterAllRatings") },
                ...[5, 4, 3, 2, 1].map((stars) => ({
                  value: String(stars),
                  label: tCount("reviews.filterStars", stars, {
                    stars: String(stars),
                  }),
                })),
              ]}
            />
            <SortSelect
              label={t("reviews.sortLabel")}
              value={sort}
              onChange={(value) =>
                router.push(buildHref({ sort: value, page: 1 }))
              }
              options={[
                { value: "newest", label: t("reviews.sortNewest") },
                { value: "oldest", label: t("reviews.sortOldest") },
                { value: "rating_high", label: t("reviews.sortRatingHigh") },
                { value: "rating_low", label: t("reviews.sortRatingLow") },
                { value: "helpful", label: t("reviews.sortHelpful") },
              ]}
            />
          </div>
        </div>
      ) : null}

      <ReviewViewTracker excludeId={viewerReview?.id ?? null}>
        <ReviewList
          reviews={initial.data}
          isLoggedIn={isLoggedIn}
          mustChooseUsername={mustChooseUsername}
          viewerReviewId={viewerReview?.id ?? null}
          returnTo={`${basePath}#reviews`}
        />
      </ReviewViewTracker>

      {initial.meta.last_page > 1 ? (
        <Pagination
          basePath={basePath}
          currentPage={initial.meta.current_page}
          lastPage={initial.meta.last_page}
          total={initial.meta.total}
          pageParam="review_page"
          searchParams={{
            review_sort: sort !== "newest" ? sort : undefined,
            review_rating: rating || undefined,
          }}
        />
      ) : null}

      {viewerReview?.status === "pending" ? (
        <PendingReviewCard review={viewerReview} />
      ) : null}

      {isLoggedIn &&
      (!viewerReview || viewerReview.can_resubmit) &&
      mustChooseUsername ? (
        // „Напиши рецензија“ jumps to #review-form: land on the note.
        <ChooseUsernameNotice
          id="review-form"
          returnTo={`${basePath}#reviews`}
        />
      ) : isLoggedIn && (!viewerReview || viewerReview.can_resubmit) ? (
        <ReviewForm
          kind={kind}
          slug={slug}
          previous={viewerReview}
          onSubmitted={() => {
            justSubmitted.current = true;
          }}
        />
      ) : null}

      {viewerReview?.status === "rejected" &&
      !viewerReview.can_resubmit &&
      !viewerReview.removed ? (
        <FinalRejectionCard review={viewerReview} />
      ) : null}

      {!isLoggedIn ? (
        <Card
          tone="sand"
          padding="md"
          className="flex items-start gap-3 type-body text-ink"
        >
          <Icon name="user" size={22} className="mt-0.5 shrink-0" />
          {/* One sentence: the link stays inline so the rest of it does not
              wrap onto a line of its own on a phone. */}
          <p>
            <Link
              href={loginHref(`${basePath}#reviews`)}
              className="link-underline font-semibold text-ink"
            >
              {t("nav.login")}
            </Link>{" "}
            {t("reviews.loginToSubmit")}
          </p>
        </Card>
      ) : null}
    </div>
  );
}

/**
 * The member's review was refused a second time (after the one edit allowed):
 * say so once, with the reason, instead of offering a form the API refuses.
 */
function FinalRejectionCard({ review }: { review: ViewerReview }) {
  return (
    <Notice tone="info" title={t("reviews.finalTitle")}>
      <p>{t("reviews.finalBody")}</p>
      {review.rejection_note ? (
        <p className="mt-2 whitespace-pre-line">{review.rejection_note}</p>
      ) : null}
    </Notice>
  );
}

/** The pending card's id: focus lands here after a review is sent. */
const PENDING_REVIEW_ID = "review-pending";

function PendingReviewCard({ review }: { review: ViewerReview }) {
  return (
    <Card
      as="article"
      id={PENDING_REVIEW_ID}
      tabIndex={-1}
      aria-labelledby={`${PENDING_REVIEW_ID}-title`}
      tone="sand"
      padding="md"
      className="flex flex-col gap-2"
    >
      <p
        id={`${PENDING_REVIEW_ID}-title`}
        className="type-body font-semibold text-ink"
      >
        {t("reviews.pendingTitle")}
      </p>
      <p className="type-meta text-ink-2">{t("reviews.pendingBody")}</p>
      <div className="flex flex-wrap items-center gap-2">
        <StarRating value={review.rating} size="md" />
        <Tag tone="white" icon="clock">
          {t("reviews.pending")}
        </Tag>
      </div>
      {review.body ? (
        <p className="type-reading text-ink">{review.body}</p>
      ) : null}
    </Card>
  );
}
