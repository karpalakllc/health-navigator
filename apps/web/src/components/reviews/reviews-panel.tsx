"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { SortSelect } from "@/components/directory/filter-controls";
import { Pagination } from "@/components/directory/pagination";
import { reviewsBasePath } from "@/components/reviews/review-paths";
import { ReviewForm } from "@/components/reviews/review-form";
import { ReviewList } from "@/components/reviews/review-list";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import { StarRating } from "@/components/ui/star-rating";
import { Tag } from "@/components/ui/tag";
import { loginHref } from "@/lib/auth/login-href";
import type {
  PaginatedEnvelope,
  PublicReview,
  ViewerReview,
} from "@/lib/api/types";
import { t, tFormat } from "@/i18n/t";

type ReviewsPanelProps = {
  kind: "doctor" | "facility" | "pharmacy";
  slug: string;
  initial: PaginatedEnvelope<PublicReview>;
  viewerReview: ViewerReview | null | undefined;
  isLoggedIn: boolean;
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
  page,
  sort,
  rating,
}: ReviewsPanelProps) {
  const router = useRouter();
  const basePath = reviewsBasePath(kind, slug);

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
                  label: tFormat("reviews.filterStars", {
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
              ]}
            />
          </div>
        </div>
      ) : null}

      <ReviewList reviews={initial.data} />

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

      {isLoggedIn && (!viewerReview || viewerReview.status === "rejected") ? (
        <ReviewForm kind={kind} slug={slug} />
      ) : null}

      {!isLoggedIn ? (
        <Card
          tone="sand"
          padding="md"
          className="flex flex-wrap items-center gap-x-1 gap-y-1 type-body text-ink"
        >
          <Icon name="user" size={22} className="mr-2" />
          <Link
            href={loginHref(`${basePath}#reviews`)}
            className="link-underline inline-flex min-h-12 items-center font-semibold text-ink"
          >
            {t("nav.login")}
          </Link>
          <span>{t("reviews.loginToSubmit")}</span>
        </Card>
      ) : null}
    </div>
  );
}

function PendingReviewCard({ review }: { review: ViewerReview }) {
  return (
    <Card as="article" tone="sand" padding="md" className="flex flex-col gap-2">
      <p className="type-body font-semibold text-ink">
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
