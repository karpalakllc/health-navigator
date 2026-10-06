"use client";

import Link from "next/link";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { OpenStatusLine } from "@/components/directory/open-status";
import { RatingLine } from "@/components/directory/rating-line";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { FeaturedTag, Tag } from "@/components/ui/tag";
import type { FacilityListItem } from "@/lib/api/types";
import { facilityKindLabel } from "@/lib/facility-labels";
import { telHref } from "@/lib/phone";
import { t, tCount, tFormat } from "@/i18n/t";

/** A clinic / hospital / laboratory result (D2a card). */
export function FacilityCard({ facility }: { facility: FacilityListItem }) {
  const href = `/facilities/${facility.slug}`;
  const tel = telHref(facility.phone);
  const hasTags =
    facility.has_emergency_services ||
    facility.departments_count > 0 ||
    facility.is_featured;

  return (
    <article className="card flex h-full flex-col p-5">
      <div className="flex items-start gap-3 lg:gap-4">
        <DirectoryAvatar
          kind="facility"
          avatarUrl={facility.avatar_url}
          name={facility.name}
          size={56}
        />
        <div className="min-w-0 flex-1 pt-1">
          <h2 className="type-h3 text-ink">
            <Link href={href} className="hover:underline">
              {facility.name}
            </Link>
          </h2>
          <p className="type-meta mt-0.5 text-ink-2">
            {facilityKindLabel(facility.type)}
          </p>
        </div>
      </div>

      <div className="mt-4 flex flex-col gap-2">
        {facility.city ? (
          <p className="flex items-start gap-2 type-body text-ink">
            <Icon name="map-pin" size={20} className="mt-0.5 text-ink-2" />
            {facility.city}
          </p>
        ) : null}
        <RatingLine summary={facility.review_summary} showStars={false} />
        {facility.office_hours ? (
          <OpenStatusLine hours={facility.office_hours} />
        ) : null}
      </div>

      {hasTags ? (
        <div className="mt-3 flex flex-wrap gap-2">
          {facility.has_emergency_services ? (
            <Tag tone="care" icon="shield-check">
              {t("facilities.emergencyAvailable")}
            </Tag>
          ) : null}
          {facility.departments_count > 0 ? (
            <Tag>
              {tCount("facilities.departmentCount", facility.departments_count)}
            </Tag>
          ) : null}
          {facility.is_featured ? <FeaturedTag /> : null}
        </div>
      ) : null}

      <div className="mt-auto flex gap-2 pt-4">
        {tel ? (
          <Button
            href={tel}
            variant="soft"
            leadingIcon="phone"
            className="flex-1"
            aria-label={tFormat("directory.callName", { name: facility.name })}
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
    </article>
  );
}
