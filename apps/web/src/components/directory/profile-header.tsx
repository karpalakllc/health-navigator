import type { ReactNode } from "react";
import { CoverImage, CoverLogo } from "@/components/directory/cover-media";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { formatAverage, hasRating } from "@/components/directory/rating-line";
import { Card } from "@/components/ui/card";
import { StarRating } from "@/components/ui/star-rating";
import type { ReviewSummary } from "@/lib/api/types";
import { tCount } from "@/i18n/t";

/**
 * The profile card (D2a).
 *
 * - A doctor: coral top edge, photo or monogram (80 → 120), the H1, a
 *   subtitle, the rating linked to the reviews, tags, and on desktop a ruled
 *   block of extra facts.
 * - A facility or pharmacy (`cover` given): the cover photo, or the soft
 *   placeholder, across the top with the logo slot overlapping its bottom
 *   edge, then the same content below.
 */
export function ProfileHeader({
  kind,
  avatarUrl,
  cover,
  name,
  subtitle,
  verification,
  reportAction,
  summary,
  reviewsAnchor = "reviews",
  tags,
  details,
}: {
  kind: "doctor" | "facility" | "pharmacy";
  avatarUrl: string | null;
  /** Facilities and pharmacies: their cover_url (null → placeholder). */
  cover?: { url: string | null | undefined };
  name: string;
  subtitle?: ReactNode;
  summary: ReviewSummary;
  reviewsAnchor?: string;
  /** The VerificationBadge, shown right after the name. */
  verification?: ReactNode;
  /**
   * Small actions about the profile itself, at the end of the title row
   * (the „Пријави профил“ flag button). Keep them icon-sized.
   */
  reportAction?: ReactNode;
  tags?: ReactNode;
  details?: ReactNode;
}) {
  const body = (
    <>
      <div>
        {/* The title row: the name, the verification badge right after it,
            and the profile's own actions (the report flag) at the end. */}
        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
          <h1 className="type-h1 min-w-0 text-ink">{name}</h1>
          {verification}
          {reportAction ? (
            <span data-slot="profile-actions" className="ml-auto shrink-0">
              {reportAction}
            </span>
          ) : null}
        </div>
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
    </>
  );

  if (cover && kind !== "doctor") {
    return (
      <Card padding="none" className="rounded-sheet">
        <div className="relative">
          <CoverImage
            kind={kind}
            coverUrl={cover.url}
            loading="eager"
            className="aspect-video rounded-t-sheet lg:aspect-[3/1]"
          />
          <CoverLogo
            kind={kind}
            avatarUrl={avatarUrl}
            name={name}
            size={80}
            loading="eager"
            className="absolute -bottom-10 left-5 lg:-bottom-14 lg:left-8"
          />
        </div>
        <div className="flex min-w-0 flex-col gap-3 px-5 pb-6 pt-16 lg:px-8 lg:pb-8 lg:pt-20">
          {body}
        </div>
      </Card>
    );
  }

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
        alt={kind === "doctor" ? name : ""}
        size={80}
        loading="eager"
        className="lg:size-30 lg:text-[2.5rem]"
      />
      <div className="mt-4 flex min-w-0 flex-1 flex-col gap-3 lg:mt-1">
        {body}
      </div>
    </Card>
  );
}
