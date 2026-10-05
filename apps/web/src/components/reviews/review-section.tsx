import { getSessionToken } from "@/lib/auth/session";
import type { ReviewSummary } from "@/lib/api/types";
import {
  fetchDoctorReviews,
  fetchFacilityReviews,
  fetchPharmacyReviews,
} from "@/lib/api/reviews";
import { ReviewsPanel } from "@/components/reviews/reviews-panel";
import { ReviewSummaryBlock } from "@/components/reviews/review-summary";
import { parseReviewQuery, type ReviewQueryInput } from "@/lib/review-query";
import { t } from "@/i18n/t";

type ReviewSectionProps = {
  kind: "doctor" | "facility" | "pharmacy";
  slug: string;
  summary: ReviewSummary;
  searchParams?: ReviewQueryInput;
};

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

  const reviews =
    kind === "doctor"
      ? await fetchDoctorReviews(slug, reviewParams)
      : kind === "pharmacy"
        ? await fetchPharmacyReviews(slug, reviewParams)
        : await fetchFacilityReviews(slug, reviewParams);

  return (
    <section className="scroll-mt-24 space-y-4">
      <h2 className="text-[1.45rem] font-black tracking-tight text-foreground">
        {t("reviews.summary")}
      </h2>
      <ReviewSummaryBlock summary={summary} />
      <ReviewsPanel
        kind={kind}
        slug={slug}
        initial={reviews}
        viewerReview={reviews.meta.viewer_review}
        isLoggedIn={Boolean(token)}
        page={page}
        sort={sort}
        rating={rating}
      />
    </section>
  );
}
