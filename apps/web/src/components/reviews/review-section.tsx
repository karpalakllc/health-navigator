import { cache } from "react";
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
import { ReviewShareButton } from "@/components/reviews/review-share-button";
import { SearchReturnRecorder } from "@/components/reviews/search-review-prompt";
import { fetchPublicSettings } from "@/lib/api/settings";
import { reviewShareUrl } from "@/lib/review-growth";
import { absoluteUrl } from "@/lib/site-url";
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

/*
 * One review-list read per request, shared by the section and the profile
 * page (the sticky „Напишете рецензија“ needs the same viewer state).
 */
const loadReviews = cache(
  (
    kind: ReviewSectionProps["kind"],
    slug: string,
    page: number,
    sort: ReturnType<typeof parseReviewQuery>["sort"],
    rating: number | undefined,
  ) => FETCHERS[kind](slug, { page, sort, rating }),
);

export type ReviewViewerState = {
  isLoggedIn: boolean;
  /** Signed in and already reviewed it (any status). */
  reviewed: boolean;
  /** The form is offered: no review yet, or a refused one that may be resent. */
  canWrite: boolean;
  /** The doctor viewing their own managed profile. */
  ownProfile: boolean;
  mustChooseUsername: boolean;
};

/** Who is looking at a profile's reviews, as far as the invitations care. */
export async function reviewViewerState(
  kind: ReviewSectionProps["kind"],
  slug: string,
  searchParams: ReviewQueryInput = {},
): Promise<
  ReviewViewerState & { reviews: Awaited<ReturnType<typeof loadReviews>> }
> {
  const token = await getSessionToken();
  const { page, sort, rating } = parseReviewQuery(searchParams);
  const reviews = await loadReviews(kind, slug, page, sort, rating);
  const isLoggedIn = Boolean(token);
  const viewerReview = reviews.meta.viewer_review;
  // Shared with the header (one /me per request).
  const viewer = isLoggedIn ? (await getShellSession()).user : null;

  return {
    reviews,
    isLoggedIn,
    reviewed: Boolean(viewerReview),
    canWrite: !viewerReview || viewerReview.can_resubmit === true,
    ownProfile: kind === "doctor" && viewer?.managed_doctor?.slug === slug,
    mustChooseUsername: viewer?.must_choose_username === true,
  };
}

export async function ReviewSection({
  kind,
  slug,
  profileName,
  summary,
  searchParams = {},
}: ReviewSectionProps) {
  // Whitelisted: a hand-edited ?review_rating=9 must not 422 the whole page.
  const reviewParams = parseReviewQuery(searchParams);
  const { page, sort } = reviewParams;
  const rating = reviewParams.rating ? String(reviewParams.rating) : "";

  const [state, settings] = await Promise.all([
    reviewViewerState(kind, slug, searchParams),
    fetchPublicSettings(),
  ]);
  const { reviews, isLoggedIn, ownProfile, mustChooseUsername } = state;
  const distribution =
    summary.count > 0 ? ratingDistribution(reviews.meta.rating_counts) : null;
  const viewerReview = reviews.meta.viewer_review;
  // W8-B prompt: never on the viewer's own (managed) profile, never once
  // they reviewed it, and not while their username is still temporary.
  const invitable = !viewerReview && !ownProfile && !mustChooseUsername;
  const showPrompt =
    settings.reviews_prompt_on_profile && Boolean(profileName) && invitable;
  // „Препорачајте рецензија“: doctors only, never the doctor's own account
  // (no tool to solicit reviews for oneself).
  const showShare =
    kind === "doctor" &&
    settings.reviews_share_link &&
    Boolean(profileName) &&
    !ownProfile;

  return (
    <section
      id="reviews"
      aria-labelledby="reviews-title"
      className="flex flex-col gap-4 pt-4 lg:gap-5 lg:pt-6"
    >
      {kind === "doctor" &&
      settings.reviews_prompt_after_search &&
      profileName ? (
        <SearchReturnRecorder
          slug={slug}
          name={profileName}
          eligible={invitable}
          reviewed={Boolean(viewerReview)}
        />
      ) : null}
      {showPrompt && profileName ? (
        <ReviewPrompt
          kind={kind}
          slug={slug}
          name={profileName}
          isLoggedIn={isLoggedIn}
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
        canWrite={state.canWrite}
      />
      {showShare && profileName ? (
        <ReviewShareButton
          name={profileName}
          url={reviewShareUrl(absoluteUrl(reviewsBasePath(kind, slug)))}
        />
      ) : null}
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
        profileName={profileName}
        page={page}
        sort={sort}
        rating={rating}
      />
    </section>
  );
}
