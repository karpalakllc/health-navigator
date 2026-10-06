import { Icon, STAR_PATH } from "@/components/ui/icons";
import { StarRating } from "@/components/ui/star-rating";
import type { ReviewSummary } from "@/lib/api/types";
import { cn } from "@/lib/cn";
import { formatRating } from "@/lib/rating";
import { t, tCount } from "@/i18n/t";

/** „4,7“ / „5,0“: one decimal with the Macedonian comma (formatRating). */
export const formatAverage = formatRating;

export function hasRating(
  summary: ReviewSummary,
): summary is { count: number; average_rating: number } {
  return summary.count > 0 && summary.average_rating !== null;
}

/**
 * ★★★★½ 4,7 · 3 рецензии — fractional amber stars, the ink number and the
 * pluralised count; „Сè уште нема рецензии“ when there are none.
 */
export function RatingLine({
  summary,
  size = "sm",
  showStars = true,
  className,
}: {
  summary: ReviewSummary;
  size?: "sm" | "md";
  /** Cards show one star glyph; profiles show the five. */
  showStars?: boolean;
  className?: string;
}) {
  if (!hasRating(summary)) {
    return (
      <p
        className={cn(
          "flex items-center gap-2 type-meta text-ink-2",
          className,
        )}
      >
        <Icon name="message-circle" size={20} />
        {t("directory.noReviewsYet")}
      </p>
    );
  }

  return (
    <p className={cn("flex flex-wrap items-center gap-x-2 gap-y-1", className)}>
      {showStars ? (
        <>
          <StarRating value={summary.average_rating} size={size} />
          <span className="font-semibold text-ink" aria-hidden="true">
            {formatAverage(summary.average_rating)}
          </span>
        </>
      ) : (
        <>
          <svg
            width={20}
            height={20}
            viewBox="0 0 24 24"
            aria-hidden="true"
            focusable="false"
            className="shrink-0 text-star"
          >
            <path d={STAR_PATH} fill="currentColor" />
          </svg>
          <span className="font-semibold text-ink">
            {formatAverage(summary.average_rating)}
            <span className="sr-only"> {t("common.ratingOutOf")}</span>
          </span>
        </>
      )}
      <span className="type-meta text-ink-2">
        <span aria-hidden="true">· </span>
        {tCount("directory.reviewsCount", summary.count)}
      </span>
    </p>
  );
}
