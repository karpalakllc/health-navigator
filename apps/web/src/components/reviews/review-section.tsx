import { getSessionToken } from "@/lib/auth/session";
import type { ReviewRatingCounts, ReviewSummary } from "@/lib/api/types";
import {
  fetchDoctorReviews,
  fetchFacilityReviews,
  fetchPharmacyReviews,
} from "@/lib/api/reviews";
import { ReviewInsights } from "@/components/reviews/review-insights";
import { ReviewsPanel } from "@/components/reviews/reviews-panel";
import {
  ReviewSummaryBlock,
  type RatingDistribution,
} from "@/components/reviews/review-summary";
import { parseReviewQuery, type ReviewQueryInput } from "@/lib/review-query";
import { t } from "@/i18n/t";

type ReviewSectionProps = {
  kind: "doctor" | "facility" | "pharmacy";
  slug: string;
  summary: ReviewSummary;
  searchParams?: ReviewQueryInput;
};

const FETCHERS = {
  doctor: fetchDoctorReviews,
  facility: fetchFacilityReviews,
  pharmacy: fetchPharmacyReviews,
} as const;

/**
 * How many reviews gave each star, from the list's own `meta.rating_counts`
 * (approved reviews, whatever the filter or page). An API without the field
 * just hides the bars; it no longer costs five extra requests.
 */
export function ratingDistribution(
  counts: ReviewRatingCounts | undefined,
): RatingDistribution | null {
  if (!counts) {
    return null;
  }

  return {
    1: counts["1"] ?? 0,
    2: counts["2"] ?? 0,
    3: counts["3"] ?? 0,
    4: counts["4"] ?? 0,
    5: counts["5"] ?? 0,
  };
}

export async function ReviewSection({
  kind,
  slug,
  summary,
  searchParams = {},
}: ReviewSectionProps) {
  const token = await getSessionToken();
  // Whitelisted: a hand-edited ?review_rating=9 must not 422 the whole page.
  const reviewParams = parseReviewQuery(searchParams);
  const { page, sort } = reviewParams;
  const rating = reviewParams.rating ? String(reviewParams.rating) : "";

  const reviews = await FETCHERS[kind](slug, reviewParams);
  const distribution =
    summary.count > 0 ? ratingDistribution(reviews.meta.rating_counts) : null;
  const isLoggedIn = Boolean(token);
  const viewerReview = reviews.meta.viewer_review;

  return (
    <section
      id="reviews"
      aria-labelledby="reviews-title"
      className="flex flex-col gap-4 pt-4 lg:gap-5 lg:pt-6"
    >
      <h2 id="reviews-title" className="type-h2 text-ink">
        {t("reviews.title")}
      </h2>
      <ReviewSummaryBlock
        summary={summary}
        distribution={distribution}
        kind={kind}
        slug={slug}
        isLoggedIn={isLoggedIn}
        canWrite={!viewerReview || viewerReview.can_resubmit === true}
      />
      <ReviewInsights
        aspects={reviews.meta.aspects}
        trend={reviews.meta.trend}
      />
      <ReviewsPanel
        kind={kind}
        slug={slug}
        initial={reviews}
        viewerReview={viewerReview}
        isLoggedIn={isLoggedIn}
        page={page}
        sort={sort}
        rating={rating}
      />
    </section>
  );
}
