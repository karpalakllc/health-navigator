import { formatAverage } from "@/components/directory/rating-line";
import { reviewsBasePath } from "@/components/reviews/review-paths";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon, STAR_PATH } from "@/components/ui/icons";
import { StarRating } from "@/components/ui/star-rating";
import type { ReviewSummary } from "@/lib/api/types";
import { loginHref } from "@/lib/auth/login-href";
import { t, tCount, tFormat } from "@/i18n/t";

export type RatingDistribution = Record<1 | 2 | 3 | 4 | 5, number>;

function DistributionBars({
  distribution,
  total,
}: {
  distribution: RatingDistribution;
  total: number;
}) {
  return (
    <ul
      aria-label={t("reviews.distribution")}
      className="m-0 flex min-w-0 flex-1 list-none flex-col gap-1.5 p-0"
    >
      {([5, 4, 3, 2, 1] as const).map((stars) => {
        const count = distribution[stars];
        const share = total > 0 ? Math.round((count / total) * 100) : 0;

        return (
          <li key={stars} className="flex items-center gap-2 type-meta">
            <span className="sr-only">
              {tFormat(
                stars === 1
                  ? "reviews.distributionRowOne"
                  : "reviews.distributionRow",
                { stars, count },
              )}
            </span>
            <span
              aria-hidden="true"
              className="flex w-8 items-center gap-1 font-semibold text-ink"
            >
              {stars}
              <svg
                width={14}
                height={14}
                viewBox="0 0 24 24"
                className="text-star"
              >
                <path d={STAR_PATH} fill="currentColor" />
              </svg>
            </span>
            <span
              aria-hidden="true"
              className="relative h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-sand"
            >
              <span
                className="absolute inset-y-0 left-0 rounded-full bg-star"
                style={{ width: `${share}%` }}
              />
            </span>
            <span
              aria-hidden="true"
              className="w-7 text-right tabular-nums text-ink-2"
            >
              {count}
            </span>
          </li>
        );
      })}
    </ul>
  );
}

/**
 * The ratings summary card: the big average with fractional stars and the
 * count, the per-star distribution, and „Напишете рецензија“ with the
 * moderation note (to login, returning here, when signed out).
 */
export function ReviewSummaryBlock({
  summary,
  distribution = null,
  kind,
  slug,
  isLoggedIn = false,
  canWrite = true,
}: {
  summary: ReviewSummary;
  distribution?: RatingDistribution | null;
  kind?: "doctor" | "facility" | "pharmacy";
  slug?: string;
  isLoggedIn?: boolean;
  canWrite?: boolean;
}) {
  const basePath = kind && slug ? reviewsBasePath(kind, slug) : null;
  const cta =
    basePath && canWrite ? (
      <div className="flex flex-col gap-3 lg:w-64 lg:shrink-0">
        <Button
          // The full path, not a bare "#review-form": on a page already
          // opened at #reviews, next/link appended it ("#reviews#review-form").
          href={
            isLoggedIn
              ? `${basePath}#review-form`
              : loginHref(`${basePath}#reviews`)
          }
          size="lg"
          fullWidth
          leadingIcon="message-circle"
        >
          {t("reviews.write")}
        </Button>
        <p className="flex gap-2 type-meta text-ink-2">
          <Icon name="shield-check" size={20} className="mt-0.5 shrink-0" />
          {t("reviews.moderationNote")}
        </p>
      </div>
    ) : null;

  if (summary.count === 0 || summary.average_rating === null) {
    return (
      <Card className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <p className="type-body text-ink-2">{t("reviews.noReviews")}</p>
        {cta}
      </Card>
    );
  }

  return (
    <Card className="flex flex-col gap-6 lg:flex-row lg:items-center lg:gap-8">
      <div className="flex items-center gap-6 lg:flex-1">
        <div className="flex shrink-0 flex-col gap-1">
          <p
            className="text-[2.75rem] font-bold leading-none tabular-nums text-ink"
            aria-hidden="true"
          >
            {formatAverage(summary.average_rating)}
          </p>
          <StarRating value={summary.average_rating} size="md" />
          <p className="type-meta text-ink-2">
            {tCount("directory.reviewsCount", summary.count)}
          </p>
        </div>
        {distribution ? (
          <DistributionBars
            distribution={distribution}
            total={Math.max(
              summary.count,
              Object.values(distribution).reduce((a, b) => a + b, 0),
            )}
          />
        ) : null}
      </div>
      {cta}
    </Card>
  );
}
