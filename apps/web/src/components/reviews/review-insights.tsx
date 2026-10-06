import { Card } from "@/components/ui/card";
import type { ReviewAspectSummary, ReviewTrendPeriod } from "@/lib/api/types";
import {
  aspectLabel,
  formatDecimal,
  periodLabel,
} from "@/lib/review-integrity";
import { t, tCount, tFormat } from "@/i18n/t";

/** Aspects with a published average (the API withholds it below 3 ratings). */
export function visibleAspects(
  aspects: ReviewAspectSummary[] | undefined,
): Array<ReviewAspectSummary & { average: number; label: string }> {
  return (aspects ?? []).flatMap((aspect) => {
    const label = aspectLabel(aspect.key);

    return aspect.average !== null && aspect.count >= 3 && label
      ? [{ ...aspect, average: aspect.average, label }]
      : [];
  });
}

/**
 * Per-aspect averages as horizontal bars. Each row is a sentence for screen
 * readers („Комуникација: 4,3 од 5, 12 оценки“); the bar is decoration.
 */
export function AspectBars({ aspects }: { aspects: ReviewAspectSummary[] }) {
  const rows = visibleAspects(aspects);

  if (rows.length === 0) {
    return null;
  }

  return (
    <section
      aria-labelledby="review-aspects-title"
      className="flex flex-col gap-3"
    >
      <h3 id="review-aspects-title" className="type-h3 text-ink">
        {t("integrity.aspectsTitle")}
      </h3>
      <ul className="m-0 flex list-none flex-col gap-3 p-0">
        {rows.map((aspect) => (
          <li key={aspect.key} className="flex flex-col gap-1">
            <span className="sr-only">
              {tCount("integrity.aspectAverage", aspect.count, {
                aspect: aspect.label,
                average: formatDecimal(aspect.average),
              })}
            </span>
            <span
              aria-hidden="true"
              className="flex items-baseline justify-between gap-3 type-body text-ink"
            >
              <span>{aspect.label}</span>
              <span className="tabular-nums font-semibold">
                {formatDecimal(aspect.average)}
              </span>
            </span>
            <span
              aria-hidden="true"
              className="relative h-2 overflow-hidden rounded-full bg-sand"
            >
              <span
                className="absolute inset-y-0 left-0 rounded-full bg-star"
                style={{
                  width: `${Math.min(100, (aspect.average / 5) * 100)}%`,
                }}
              />
            </span>
            <span aria-hidden="true" className="type-meta text-ink-2">
              {tCount("integrity.aspectCount", aspect.count)}
            </span>
          </li>
        ))}
      </ul>
      <p className="type-meta text-ink-2">{t("integrity.aspectsNote")}</p>
    </section>
  );
}

/**
 * The twelve-month trend: four three-month columns whose height is the
 * average. The API sends it only with at least 5 reviews in that time. Each
 * column carries its own sentence („ное 2025 – јан 2026: просечна оценка 4,3
 * од 5, 6 рецензии“) as the text alternative; the drawing is hidden from
 * screen readers.
 */
export function RatingTrend({ trend }: { trend: ReviewTrendPeriod[] }) {
  if (trend.length === 0) {
    return null;
  }

  return (
    <section
      aria-labelledby="review-trend-title"
      className="flex flex-col gap-3"
    >
      <div className="flex flex-col gap-1">
        <h3 id="review-trend-title" className="type-h3 text-ink">
          {t("integrity.trendTitle")}
        </h3>
        <p className="type-meta text-ink-2">{t("integrity.trendLead")}</p>
      </div>
      <ol className="m-0 grid list-none grid-cols-4 gap-2 p-0 sm:gap-3">
        {trend.map((period) => {
          const label = periodLabel(period.start, period.end);
          const share =
            period.average === null
              ? 0
              : Math.min(100, (period.average / 5) * 100);

          return (
            <li
              key={period.start}
              className="flex min-w-0 flex-col items-center gap-1.5"
            >
              <span className="sr-only">
                {period.average === null
                  ? tFormat("integrity.trendPeriodEmpty", { period: label })
                  : tCount("integrity.trendPeriod", period.count, {
                      period: label,
                      average: formatDecimal(period.average),
                    })}
              </span>
              <span
                aria-hidden="true"
                className="type-meta font-semibold tabular-nums text-ink"
              >
                {period.average === null
                  ? t("integrity.noData")
                  : formatDecimal(period.average)}
              </span>
              <span
                aria-hidden="true"
                className="relative flex h-24 w-full max-w-14 items-end overflow-hidden rounded-xl bg-sand"
              >
                <span
                  className="w-full rounded-xl bg-star"
                  style={{ height: `${share}%` }}
                />
              </span>
              <span
                aria-hidden="true"
                className="text-center text-[0.875rem] leading-[1.125rem] text-ink-2"
              >
                {label}
              </span>
            </li>
          );
        })}
      </ol>
    </section>
  );
}

/**
 * The aspect averages and the trend under the ratings summary, side by side
 * on wide screens; nothing at all when neither has enough reviews.
 */
export function ReviewInsights({
  aspects,
  trend,
}: {
  aspects: ReviewAspectSummary[] | undefined;
  trend: ReviewTrendPeriod[] | null | undefined;
}) {
  const hasAspects = visibleAspects(aspects).length > 0;
  const hasTrend = Boolean(trend && trend.length > 0);

  if (!hasAspects && !hasTrend) {
    return null;
  }

  return (
    <Card className="grid gap-8 lg:grid-cols-2 lg:gap-10">
      {hasAspects ? <AspectBars aspects={aspects ?? []} /> : null}
      {hasTrend && trend ? <RatingTrend trend={trend} /> : null}
    </Card>
  );
}
