import { getSessionToken } from "@/lib/auth/session";
import type { ReviewSummary } from "@/lib/api/types";
import {
  fetchDoctorReviews,
  fetchFacilityReviews,
  fetchPharmacyReviews,
} from "@/lib/api/reviews";
import { ReviewsPanel } from "@/components/reviews/reviews-panel";
import { ReviewSummaryBlock } from "@/components/reviews/review-summary";
import { t } from "@/i18n/t";

type ReviewSectionProps = {
  kind: "doctor" | "facility" | "pharmacy";
  slug: string;
  summary: ReviewSummary;
  searchParams?: {
    review_page?: string;
    review_sort?: string;
    review_rating?: string;
  };
};

export async function ReviewSection({
  kind,
  slug,
  summary,
  searchParams = {},
}: ReviewSectionProps) {
  const token = await getSessionToken();
  const page =
    Number(searchParams.review_page) > 0 ? Number(searchParams.review_page) : 1;
  const sort = searchParams.review_sort ?? "newest";
  const rating = searchParams.review_rating ?? "";

  const reviewParams = {
    page,
    sort: sort as "newest" | "oldest" | "rating_high" | "rating_low",
    rating: rating ? Number(rating) : undefined,
  };

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
