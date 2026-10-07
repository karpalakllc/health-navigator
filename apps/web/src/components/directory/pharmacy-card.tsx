"use client";

import Link from "next/link";
import {
  CoverImage,
  CoverLogo,
  FeaturedMark,
} from "@/components/directory/cover-media";
import { LiveOpenStatusLine } from "@/components/directory/open-status-live";
import { RatingLine } from "@/components/directory/rating-line";
import { VerificationBadge } from "@/components/directory/verification-badge";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import type { PharmacyListItem } from "@/lib/api/types";
import { telHref } from "@/lib/phone";
import { t, tFormat } from "@/i18n/t";

/**
 * A pharmacy result (D2a card): cover photo or soft placeholder on top,
 * the logo slot over its corner, then name, place, rating, open status and
 * the two actions.
 */
export function PharmacyCard({ pharmacy }: { pharmacy: PharmacyListItem }) {
  const href = `/pharmacies/${pharmacy.slug}`;
  const tel = telHref(pharmacy.phone);

  return (
    <article
      data-track="pharmacy-card"
      className="card hover-lift flex h-full flex-col"
    >
      <div className="relative">
        <CoverImage
          kind="pharmacy"
          coverUrl={pharmacy.cover_url}
          className="aspect-video rounded-t-card md:aspect-[2/1]"
        />
        {pharmacy.is_featured ? (
          <FeaturedMark className="absolute left-3 top-3 shadow-card" />
        ) : null}
        <CoverLogo
          kind="pharmacy"
          avatarUrl={pharmacy.avatar_url}
          name={pharmacy.name}
          className="absolute -bottom-7 left-4"
        />
      </div>

      <div className="flex flex-1 flex-col px-5 pb-5 pt-10">
        <h2 className="type-h3 text-ink">
          <Link href={href} className="link-grow">
            {pharmacy.name}
          </Link>
        </h2>
        <p className="type-meta mt-0.5 text-ink-2">{t("pharmacies.kind")}</p>
        <VerificationBadge
          verification={pharmacy.verification}
          kind="pharmacy"
          className="mt-2 self-start"
        />

        <div className="mt-4 flex flex-col gap-2">
          {pharmacy.city ? (
            <p className="flex items-start gap-2 type-body text-ink">
              <Icon name="map-pin" size={20} className="mt-0.5 text-ink-2" />
              {pharmacy.city}
            </p>
          ) : null}
          <RatingLine summary={pharmacy.review_summary} showStars={false} />
          {pharmacy.office_hours ? (
            <LiveOpenStatusLine hours={pharmacy.office_hours} />
          ) : null}
        </div>

        <div className="mt-auto flex gap-2 pt-4">
          {tel ? (
            <Button
              href={tel}
              variant="soft"
              leadingIcon="phone"
              className="flex-1"
              aria-label={tFormat("directory.callName", {
                name: pharmacy.name,
              })}
            >
              {t("directory.call")}
            </Button>
          ) : null}
          <Button
            href={href}
            className="flex-1"
            aria-label={tFormat("directory.viewProfileOf", {
              name: pharmacy.name,
            })}
          >
            {t("doctors.viewProfile")}
          </Button>
        </div>
      </div>
    </article>
  );
}
