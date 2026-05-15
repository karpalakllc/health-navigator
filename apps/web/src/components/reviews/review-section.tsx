import { getSessionToken } from "@/lib/auth/session";
import type { PublicReview, ReviewSummary } from "@/lib/api/types";
import { LoginPrompt } from "@/components/ui/login-prompt";
import { PageSection } from "@/components/ui/page-section";
import { ReviewForm } from "@/components/reviews/review-form";
import { ReviewList } from "@/components/reviews/review-list";
import { ReviewSummaryBlock } from "@/components/reviews/review-summary";
import { t } from "@/i18n/t";

type ReviewSectionProps = {
  kind: "doctor" | "facility";
  slug: string;
  summary: ReviewSummary;
  reviews: PublicReview[];
};

export async function ReviewSection({
  kind,
  slug,
  summary,
  reviews,
}: ReviewSectionProps) {
  const token = await getSessionToken();

  return (
    <PageSection title={t("reviews.summary")}>
      <div className="space-y-4">
        <ReviewSummaryBlock summary={summary} />
        <ReviewList reviews={reviews} />
        {token ? (
          <ReviewForm kind={kind} slug={slug} />
        ) : (
          <LoginPrompt suffix={t("reviews.loginToSubmit")} />
        )}
      </div>
    </PageSection>
  );
}
