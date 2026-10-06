import { getSessionToken } from "@/lib/auth/session";
import type {
  PaginatedEnvelope,
  PublicReview,
  ReviewSummary,
} from "@/lib/api/types";
import {
  fetchDoctorReviews,
  fetchFacilityReviews,
  fetchPharmacyReviews,
  type ReviewListParams,
} from "@/lib/api/reviews";
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
 * How many reviews gave each star. When the unfiltered first page already
 * holds every review we count it; otherwise five one-row requests read each
 * rating's total. A failure only hides the bars, never the page.
 */
async function ratingDistribution(
  kind: ReviewSectionProps["kind"],
  slug: string,
  summary: ReviewSummary,
  firstPage: PaginatedEnvelope<PublicReview> | null,
): Promise<RatingDistribution | null> {
  if (summary.count === 0) {
    return null;
  }

  if (firstPage && firstPage.meta.total <= firstPage.data.length) {
    const counts: RatingDistribution = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
    for (const review of firstPage.data) {
      const stars = Math.round(review.rating) as keyof RatingDistribution;
      if (stars in counts) counts[stars]++;
    }
    return counts;
  }

  try {
    const totals = await Promise.all(
      ([5, 4, 3, 2, 1] as const).map((rating) =>
        FETCHERS[kind](slug, {
          rating,
          per_page: 1,
        } satisfies ReviewListParams),
      ),
    );
    const [five, four, three, two, one] = totals.map((r) => r.meta.total);
    return { 5: five, 4: four, 3: three, 2: two, 1: one };
  } catch {
    return null;
  }
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
  const unfilteredFirstPage = !reviewParams.rating && page === 1;
  const distribution = await ratingDistribution(
    kind,
    slug,
    summary,
    unfilteredFirstPage ? reviews : null,
  );
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
        canWrite={!viewerReview || viewerReview.status === "rejected"}
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
