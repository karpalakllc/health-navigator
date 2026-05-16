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
    <div className="flex flex-col gap-6 rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-secondary/20 p-6 shadow-[0_16px_44px_-32px_rgb(15_23_42/0.35)] sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-center gap-5">
        <p className="text-4xl font-bold tabular-nums tracking-tight text-foreground">
          {summary.average_rating ?? "—"}
        </p>
        <div className="space-y-1">
          <StarRating value={average} size="md" tone="amber" />
          <p className="text-sm text-muted-foreground">
            {summary.count} {countLabel}
          </p>
        </div>
      </div>
    </div>
  );
}
