import type { CSSProperties, ReactNode } from "react";
import { CoverImage, CoverLogo } from "@/components/directory/cover-media";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { formatAverage, hasRating } from "@/components/directory/rating-line";
import { Card } from "@/components/ui/card";
import { Icon, type IconName } from "@/components/ui/icons";
import { StarRating } from "@/components/ui/star-rating";
import type { ReviewSummary } from "@/lib/api/types";
import { tCount } from "@/i18n/t";

/**
 * The profile card (D2a), compact on a phone.
 *
 * A two-column grid: the photo or monogram (56 → 120) beside the H1, with
 * the subtitle (specialty, kind) and the muted `meta` line (city) under the
 * name. Below that, full width on a phone and in the right column on
 * desktop: the rating linked to the reviews, ONE wrapping row of small chips
 * (verification first), and on desktop a ruled block of extra facts.
 *
 * A facility or pharmacy (`cover` given) gets its cover photo, or the soft
 * placeholder, as a band across the top, with the logo overlapping its
 * bottom edge beside the name.
 *
 * The report flag sits in the card's top-right corner (a 44 px target),
 * after the title in reading order.
 */
export function ProfileHeader({
  kind,
  avatarUrl,
  cover,
  name,
  subtitle,
  meta,
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
  /** A muted line under the subtitle: ProfileMetaItems (the city…). */
  meta?: ReactNode;
  summary: ReviewSummary;
  reviewsAnchor?: string;
  /** The VerificationBadge, first in the chip row. */
  verification?: ReactNode;
  /**
   * Small actions about the profile itself, in the card's top-right corner
   * (the „Пријави профил“ flag button). Keep them icon-sized.
   */
  reportAction?: ReactNode;
  tags?: ReactNode;
  details?: ReactNode;
}) {
  const withCover = Boolean(cover) && kind !== "doctor";
  // Below the avatar on a phone (both columns), beside it on desktop.
  const rest = "col-span-2 lg:col-span-1 lg:col-start-2";
  // On desktop the avatar spans exactly the rows that are there: a span
  // past them would add empty rows (and their gaps) under the content.
  const rows =
    1 +
    (hasRating(summary) ? 1 : 0) +
    (verification || tags ? 1 : 0) +
    (details ? 1 : 0);
  const rowsStyle = { "--hero-rows": rows } as CSSProperties;

  const body = (
    <div
      style={rowsStyle}
      className="relative grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-3 px-4 pb-5 pt-5 lg:gap-x-8 lg:gap-y-4 lg:px-8 lg:pb-8 lg:pt-8"
    >
      {withCover && cover ? (
        <CoverLogo
          kind={kind as "facility" | "pharmacy"}
          avatarUrl={avatarUrl}
          name={name}
          size={56}
          grow
          loading="eager"
          className="self-start lg:row-span-(--hero-rows) lg:-mt-20"
        />
      ) : (
        <DirectoryAvatar
          kind={kind}
          avatarUrl={avatarUrl}
          name={name}
          alt={kind === "doctor" ? name : ""}
          size={56}
          loading="eager"
          className="self-start lg:row-span-(--hero-rows) lg:size-30 lg:text-[2.5rem]"
        />
      )}
      <div
        data-slot="profile-title"
        className={
          reportAction
            ? "min-w-0 self-center pr-9 lg:self-start lg:pr-10"
            : "min-w-0 self-center lg:self-start"
        }
      >
        <h1 className="type-profile-name break-words text-ink">{name}</h1>
        {subtitle ? (
          <p className="mt-0.5 type-meta text-ink-2 lg:mt-1 lg:type-body">
            {subtitle}
          </p>
        ) : null}
        {meta ? (
          <p className="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 type-meta text-ink-2">
            {meta}
          </p>
        ) : null}
      </div>
      {reportAction ? (
        <span
          data-slot="profile-actions"
          className="absolute right-1 top-1 lg:right-4 lg:top-4"
        >
          {reportAction}
        </span>
      ) : null}
      {hasRating(summary) ? (
        <p className={`${rest} flex flex-wrap items-center gap-x-2 gap-y-1`}>
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
      {verification || tags ? (
        <div
          data-slot="profile-chips"
          className={`${rest} profile-chips flex flex-wrap gap-1.5 lg:gap-2`}
        >
          {verification}
          {tags}
        </div>
      ) : null}
      {details ? (
        <div
          className={`${rest} hidden flex-col gap-2 border-t border-line pt-4 lg:flex`}
        >
          {details}
        </div>
      ) : null}
    </div>
  );

  if (withCover && cover) {
    return (
      <Card padding="none" className="rounded-sheet">
        <CoverImage
          kind={kind as "facility" | "pharmacy"}
          coverUrl={cover.url}
          loading="eager"
          className="aspect-[3/1] rounded-t-sheet"
        />
        {body}
      </Card>
    );
  }

  return (
    // The coral top edge drawn in its own clipped strip, not Card's `edge`:
    // that clips the whole card (overflow: hidden), and the verification
    // toggletip opens downwards past the card's bottom from the chip row.
    <Card padding="none" className="rounded-sheet">
      <span
        aria-hidden="true"
        data-slot="profile-edge"
        className="pointer-events-none absolute inset-x-0 top-0 h-8 overflow-hidden rounded-t-sheet"
      >
        <span className="block h-1 bg-coral" />
      </span>
      {body}
    </Card>
  );
}

/** One fact on the header's muted `meta` line: a small icon and the text. */
export function ProfileMetaItem({
  icon,
  children,
}: {
  icon: IconName;
  children: ReactNode;
}) {
  return (
    <span className="inline-flex min-w-0 items-center gap-1">
      <Icon name={icon} size={16} className="shrink-0" />
      <span className="min-w-0">{children}</span>
    </span>
  );
}
