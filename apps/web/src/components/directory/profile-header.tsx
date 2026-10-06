import type { ReactNode } from "react";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { formatAverage, hasRating } from "@/components/directory/rating-line";
import { Card } from "@/components/ui/card";
import { StarRating } from "@/components/ui/star-rating";
import type { ReviewSummary } from "@/lib/api/types";
import { tCount } from "@/i18n/t";

/**
 * The profile card (D2a): coral top edge, monogram or photo (80 → 120),
 * the H1, a subtitle, the rating linked to the reviews, tags, and on
 * desktop a ruled block of extra facts.
 */
export function ProfileHeader({
  kind,
  avatarUrl,
  name,
  subtitle,
  summary,
  reviewsAnchor = "reviews",
  tags,
  details,
}: {
  kind: "doctor" | "facility" | "pharmacy";
  avatarUrl: string | null;
  name: string;
  subtitle?: ReactNode;
  summary: ReviewSummary;
  reviewsAnchor?: string;
  tags?: ReactNode;
  details?: ReactNode;
}) {
  return (
    <Card
      edge
      padding="none"
      className="rounded-sheet px-5 pb-6 pt-7 lg:flex lg:gap-8 lg:p-8"
    >
      <DirectoryAvatar
        kind={kind}
        avatarUrl={avatarUrl}
        name={name}
        size={80}
        loading="eager"
        className="lg:size-30 lg:text-[2.5rem]"
      />
      <div className="mt-4 flex min-w-0 flex-1 flex-col gap-3 lg:mt-1">
        <div>
          <h1 className="type-h1 text-ink">{name}</h1>
          {subtitle ? (
            <p className="mt-1 type-body text-ink-2">{subtitle}</p>
          ) : null}
        </div>
        {hasRating(summary) ? (
          <p className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <StarRating value={summary.average_rating} size="md" />
            <span className="font-bold text-ink" aria-hidden="true">
              {formatAverage(summary.average_rating)}
            </span>
            <span aria-hidden="true" className="text-ink-2">
              ·
            </span>
            <a
              href={`#${reviewsAnchor}`}
              className="link-underline type-body text-ink"
            >
              {tCount("directory.reviewsCount", summary.count)}
            </a>
          </p>
        ) : null}
        {tags ? <div className="flex flex-wrap gap-2">{tags}</div> : null}
        {details ? (
          <div className="mt-2 hidden flex-col gap-2 border-t border-line pt-4 lg:flex">
            {details}
          </div>
        ) : null}
      </div>
    </Card>
  );
}
