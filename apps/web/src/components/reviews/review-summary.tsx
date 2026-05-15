import type { ReviewSummary } from "@/lib/api/types";
import { StarRating } from "@/components/ui/star-rating";
import { t, tFormat } from "@/i18n/t";

export function ReviewSummaryBlock({ summary }: { summary: ReviewSummary }) {
  if (summary.count === 0) {
    return <p className="text-sm text-zinc-500">{t("reviews.noReviews")}</p>;
  }

  const countLabel = summary.count === 1 ? t("reviews.countOne") : t("reviews.count");
  const average = summary.average_rating ?? 0;

  return (
    <div className="flex flex-wrap items-center gap-3 text-sm text-zinc-600">
      <StarRating value={average} size="md" />
      <span>
        {tFormat("reviews.summaryLine", {
          average: summary.average_rating ?? "—",
          count: summary.count,
          countLabel,
        })}
      </span>
    </div>
  );
}
