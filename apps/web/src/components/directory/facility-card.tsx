"use client";

import Link from "next/link";
import {
  CoverImage,
  CoverLogo,
  FeaturedMark,
} from "@/components/directory/cover-media";
import { LiveOpenStatusLine } from "@/components/directory/open-status-live";
import { RatingLine } from "@/components/directory/rating-line";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import type { FacilityListItem } from "@/lib/api/types";
import { facilityKindLabel } from "@/lib/facility-labels";
import { telHref } from "@/lib/phone";
import { t, tCount, tFormat } from "@/i18n/t";

/**
 * A clinic / hospital / laboratory result (D2a card): the cover photo (or a
 * soft placeholder) across the top with the logo slot over its bottom-left
 * corner and „Истакнат“ over its top-left, then the name, kind, place,
 * rating, open status, tags and the two actions.
 */
export function FacilityCard({ facility }: { facility: FacilityListItem }) {
  const href = `/facilities/${facility.slug}`;
  const tel = telHref(facility.phone);
  const hasTags =
    facility.has_emergency_services || facility.departments_count > 0;

  return (
    <article
      data-track="facility-card"
      className="card hover-lift flex h-full flex-col"
    >
      <div className="relative">
        <CoverImage
          kind="facility"
          coverUrl={facility.cover_url}
          className="aspect-video rounded-t-card md:aspect-[2/1]"
        />
        {facility.is_featured ? (
          <FeaturedMark className="absolute left-3 top-3 shadow-card" />
        ) : null}
        <CoverLogo
          kind="facility"
          avatarUrl={facility.avatar_url}
          name={facility.name}
          className="absolute -bottom-7 left-4"
        />
      </div>

      <div className="flex flex-1 flex-col px-5 pb-5 pt-10">
        <h2 className="type-h3 text-ink">
          <Link href={href} className="link-grow">
            {facility.name}
          </Link>
        </h2>
        <p className="type-meta mt-0.5 text-ink-2">
          {facilityKindLabel(facility.type)}
        </p>

        <div className="mt-4 flex flex-col gap-2">
          {facility.city ? (
            <p className="flex items-start gap-2 type-body text-ink">
              <Icon name="map-pin" size={20} className="mt-0.5 text-ink-2" />
              {facility.city}
            </p>
          ) : null}
          <RatingLine summary={facility.review_summary} showStars={false} />
          {facility.office_hours ? (
            <LiveOpenStatusLine hours={facility.office_hours} />
          ) : null}
        </div>

        {hasTags ? (
          <div className="mt-3 flex flex-wrap gap-2">
            {facility.has_emergency_services ? (
              <Tag icon="building">{t("facilities.emergencyAvailable")}</Tag>
            ) : null}
            {facility.departments_count > 0 ? (
              <Tag>
                {tCount(
                  "facilities.departmentCount",
                  facility.departments_count,
                )}
              </Tag>
            ) : null}
          </div>
        ) : null}

        <div className="mt-auto flex gap-2 pt-4">
          {tel ? (
            <Button
              href={tel}
              variant="soft"
              leadingIcon="phone"
              className="flex-1"
              aria-label={tFormat("directory.callName", {
                name: facility.name,
              })}
            >
              {t("directory.call")}
            </Button>
          ) : null}
          <Button
            href={href}
            className="flex-1"
            aria-label={tFormat("directory.viewProfileOf", {
              name: facility.name,
            })}
          >
            {t("doctors.viewProfile")}
          </Button>
        </div>
      </div>
    </article>
  );
}
