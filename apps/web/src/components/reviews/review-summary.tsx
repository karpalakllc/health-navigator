import type { ReviewSummary } from "@/lib/api/types";
import { StarRating } from "@/components/ui/star-rating";
import { t } from "@/i18n/t";

export function ReviewSummaryBlock({ summary }: { summary: ReviewSummary }) {
  if (summary.count === 0) {
    return <p className="text-sm text-muted-foreground">{t("reviews.noReviews")}</p>;
  }

  const countLabel = summary.count === 1 ? t("reviews.countOne") : t("reviews.count");
  const average = summary.average_rating ?? 0;

  return (
    <div className="content-card flex flex-col gap-5 rounded-[1.625rem] p-[22px] sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-center gap-[18px]">
        <p className="text-5xl font-black tabular-nums tracking-tight text-foreground">
          {summary.average_rating ?? "—"}
        </p>
        <div>
          <StarRating value={average} size="md" />
          <p className="mt-2 text-sm text-muted-foreground">
            {summary.count} {countLabel}
          </p>
        </div>
      </div>
      <p className="max-w-[360px] text-sm leading-relaxed text-muted-foreground">{t("reviews.moderationNote")}</p>
    </div>
  );
}
