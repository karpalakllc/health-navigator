"use client";

import { useRouter } from "next/navigation";
import { FilterInputWrap } from "@/components/directory/filter-input-wrap";
import { Pagination } from "@/components/directory/pagination";
import Link from "next/link";
import { ReviewForm } from "@/components/reviews/review-form";
import { ReviewList } from "@/components/reviews/review-list";
import { StarRating } from "@/components/ui/star-rating";
import type { PaginatedEnvelope, PublicReview, ViewerReview } from "@/lib/api/types";
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
  const basePath =
    kind === "doctor"
      ? `/doctors/${slug}`
      : kind === "pharmacy"
        ? `/pharmacies/${slug}`
        : `/facilities/${slug}`;

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

  return (
    <div id="reviews" className="scroll-mt-24 space-y-4">
      <div className="content-card grid gap-3.5 rounded-[1.625rem] p-[18px] sm:max-w-xl sm:grid-cols-2">
        <label className="grid gap-2 text-sm">
          <span className="font-bold text-[#36414b]">{t("reviews.sortLabel")}</span>
          <FilterInputWrap compact icon={<SortIcon className="h-5 w-5" aria-hidden />}>
            <select
              value={sort}
              onChange={(event) => router.push(buildHref({ sort: event.target.value, page: 1 }))}
            >
              <option value="newest">{t("reviews.sortNewest")}</option>
              <option value="oldest">{t("reviews.sortOldest")}</option>
              <option value="rating_high">{t("reviews.sortRatingHigh")}</option>
              <option value="rating_low">{t("reviews.sortRatingLow")}</option>
            </select>
          </FilterInputWrap>
        </label>
        <label className="grid gap-2 text-sm">
          <span className="font-bold text-[#36414b]">{t("reviews.filterRating")}</span>
          <FilterInputWrap compact icon={<StarGlyph className="h-5 w-5" aria-hidden />}>
            <select
              value={rating}
              onChange={(event) => router.push(buildHref({ rating: event.target.value, page: 1 }))}
            >
              <option value="">{t("reviews.filterAllRatings")}</option>
              {[5, 4, 3, 2, 1].map((stars) => (
                <option key={stars} value={String(stars)}>
                  {tFormat("reviews.filterStars", { stars: String(stars) })}
                </option>
              ))}
            </select>
          </FilterInputWrap>
        </label>
      </div>

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

      {viewerReview?.status === "pending" ? <PendingReviewCard review={viewerReview} /> : null}

      {isLoggedIn && (!viewerReview || viewerReview.status === "rejected") ? (
        <ReviewForm kind={kind} slug={slug} />
      ) : null}

      {!isLoggedIn ? (
        <div className="flex min-h-[54px] items-center rounded-full border border-border bg-white/80 px-[18px] text-[0.95rem] text-[#5a6773]">
          <Link href="/login" className="font-bold text-primary hover:underline">
            {t("nav.login")}
          </Link>
          <span className="ml-1">{t("reviews.loginToSubmit")}</span>
        </div>
      ) : null}
    </div>
  );
}

function PendingReviewCard({ review }: { review: ViewerReview }) {
  return (
    <article className="rounded-[1.625rem] border border-amber-200/80 bg-amber-50/60 p-5">
      <p className="text-sm font-semibold text-amber-900">{t("reviews.pendingTitle")}</p>
      <p className="mt-1 text-xs text-amber-800/90">{t("reviews.pendingBody")}</p>
      <div className="mt-3 flex items-center gap-2">
        <StarRating value={review.rating} />
        <span className="rounded-full bg-amber-200/80 px-2 py-0.5 text-xs font-medium text-amber-900">
          {t("reviews.pending")}
        </span>
      </div>
      {review.body ? <p className="mt-2 text-sm text-foreground">{review.body}</p> : null}
    </article>
  );
}

function SortIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M7 16V4M7 4L3 8M7 4l4 4M17 8v12M17 20l4-4M17 20l-4-4" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function StarGlyph({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path
        d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.56 5.82 22 7 14.14l-5-4.87 6.91-1.01L12 2z"
        strokeLinejoin="round"
      />
    </svg>
  );
}
