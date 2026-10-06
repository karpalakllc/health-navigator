import Link from "next/link";
import { Icon, type IconName } from "@/components/ui/icons";
import { SectionHeader } from "@/components/ui/section-header";
import { StarRating } from "@/components/ui/star-rating";
import { Monogram } from "@/components/ui/user-avatar";
import {
  homeReviewTargetPath,
  type HomeReview,
  type HomeReviewTarget,
} from "@/lib/api/home";
import { cn } from "@/lib/cn";
import { fitToColumns } from "@/lib/grid-fit";
import { formatMkDate } from "@/lib/mk-date";
import { formatRelativeDay } from "@/lib/relative-day";
import { t } from "@/i18n/t";

const TARGET_ICON: Record<HomeReviewTarget["kind"], IconName> = {
  doctor: "stethoscope",
  facility: "building",
  pharmacy: "pill",
};

/** Phones (one column) get at most three cards. */
const PHONE_MAX = 3;

/**
 * „Најнови рецензии“: the latest approved reviews (GET /home/highlights).
 * Each card is one link — to the reviewed profile's review section — named by
 * the profile; the author shows by public display name only.
 */
export function HomeRecentReviews({
  reviews,
  now,
  className,
}: {
  reviews: HomeReview[];
  /** For the relative dates; defaults to the render time. */
  now?: Date;
  className?: string;
}) {
  const shown = reviews.filter(
    (review): review is HomeReview & { target: HomeReviewTarget } =>
      review.target !== null,
  );

  if (shown.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="home-reviews-title" className={className}>
      <SectionHeader
        id="home-reviews-title"
        title={t("homeSections.reviewsTitle")}
        description={
          <span className="inline-flex items-center gap-2">
            {/* A quiet, still „live“ dot (no endless ping: calm UI): these
                are the newest approved reviews. */}
            <span
              aria-hidden="true"
              className="size-2.5 shrink-0 rounded-full bg-care"
            />
            {t("homeSearch.reviewsLead")}
          </span>
        }
      />
      <ul
        className={cn(
          "mt-3 grid gap-3 md:grid-cols-2 lg:mt-6 lg:gap-6",
          shown.length >= 4 ? "lg:grid-cols-4" : "lg:grid-cols-3",
        )}
      >
        {shown.map((review, index) => {
          // Whole rows only: three stacked on phones, an even number in the
          // two-column grid from md, all of them in one row from lg.
          const relative = formatRelativeDay(review.published_at, now);
          const absolute = formatMkDate(review.published_at);

          return (
            <li
              key={review.id}
              className={cn(
                "flex",
                index >= PHONE_MAX && "max-md:hidden",
                index >= fitToColumns(shown.length, 2, 4) && "md:max-lg:hidden",
              )}
            >
              <article className="card hover-lift flex w-full flex-col gap-3 p-4 lg:p-5">
                <header className="flex items-center gap-3">
                  <Monogram name={review.author_name} size={40} />
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-ui text-base font-semibold leading-5 text-ink">
                      {review.author_name}
                    </p>
                    {relative && review.published_at ? (
                      <time
                        dateTime={review.published_at}
                        title={absolute ?? undefined}
                        className="type-meta text-ink-2"
                      >
                        {relative}
                      </time>
                    ) : null}
                  </div>
                </header>
                <StarRating value={review.rating} size="md" />
                <p className="flex-1 font-reading text-[1.0625rem] leading-[1.625rem] text-ink">
                  {review.excerpt}
                </p>
                <p className="flex items-center gap-2 border-t border-line pt-3 text-ink">
                  <Icon
                    name={TARGET_ICON[review.target.kind]}
                    size={20}
                    className="text-ink-2"
                  />
                  <span className="sr-only">
                    {t("homeSections.reviewFor")}{" "}
                  </span>
                  {/* Stretched over the card: one link per review, named by
                      the profile it is about. */}
                  <Link
                    href={`${homeReviewTargetPath(review.target)}#reviews`}
                    className="link-grow min-w-0 truncate font-ui text-base font-semibold leading-6 after:absolute after:inset-0 after:rounded-card"
                  >
                    {review.target.name}
                  </Link>
                </p>
              </article>
            </li>
          );
        })}
      </ul>
    </section>
  );
}
