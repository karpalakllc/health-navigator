import { getShellSession } from "@/lib/auth/header-session";
import { getSessionToken } from "@/lib/auth/session";
import type { ReviewRatingCounts, ReviewSummary } from "@/lib/api/types";
import {
  fetchDoctorReviews,
  fetchFacilityReviews,
  fetchPharmacyReviews,
} from "@/lib/api/reviews";
import { ReviewInsights } from "@/components/reviews/review-insights";
import { reviewsBasePath } from "@/components/reviews/review-paths";
import { ReviewPrompt } from "@/components/reviews/review-prompt";
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
  /** The profile's public name, for the „Дали сте биле кај…?“ prompt. */
  profileName?: string;
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
  profileName,
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
  // Shared with the header (one /me per request).
  const viewer = isLoggedIn ? (await getShellSession()).user : null;
  const mustChooseUsername = viewer?.must_choose_username === true;
  // W8-B prompt: never on the viewer's own (managed) profile, never once
  // they reviewed it, and not while their username is still temporary.
  const ownProfile = kind === "doctor" && viewer?.managed_doctor?.slug === slug;
  const showPrompt =
    Boolean(profileName) && !viewerReview && !ownProfile && !mustChooseUsername;

  return (
    <section
      id="reviews"
      aria-labelledby="reviews-title"
      className="flex flex-col gap-4 pt-4 lg:gap-5 lg:pt-6"
    >
      {showPrompt && profileName ? (
        <ReviewPrompt
          kind={kind}
          slug={slug}
          name={profileName}
          isLoggedIn={isLoggedIn}
          basePath={reviewsBasePath(kind, slug)}
        />
      ) : null}
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
        mustChooseUsername={mustChooseUsername}
        page={page}
        sort={sort}
        rating={rating}
      />
    </section>
  );
}
