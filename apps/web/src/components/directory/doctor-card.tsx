"use client";

import Link from "next/link";
import { FeaturedMark } from "@/components/directory/cover-media";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { LiveOpenStatusLine } from "@/components/directory/open-status-live";
import { RatingLine } from "@/components/directory/rating-line";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { SponsoredBadge } from "@/components/ui/sponsored-badge";
import { Tag } from "@/components/ui/tag";
import type { DoctorListItem } from "@/lib/api/types";
import { cn } from "@/lib/cn";
import { telHref } from "@/lib/phone";
import { t, tFormat } from "@/i18n/t";

/**
 * A doctor result (D2a card): photo or monogram, name, the specialty line
 * plus any other specialty a filter or search may have matched, place,
 * rating with count, „Прима нови пациенти“, and the two actions — a soft
 * tap-to-call when the number is known, and the ink „Види профил“.
 *
 * A featured doctor (the API lists them first; the order is kept) gets an
 * apricot band across the top carrying „Истакнат“ (and „Спонзорирано“ when
 * the placement is paid) and a slightly larger photo; the rest of the card
 * is the same as every other result.
 */
export function DoctorCard({ doctor }: { doctor: DoctorListItem }) {
  const href = `/doctors/${doctor.slug}`;
  const specialtyLine = [doctor.primary_specialty?.name, doctor.subspecialty]
    .filter(Boolean)
    .join(" · ");
  const otherSpecialties = (doctor.specialties ?? [])
    .filter((specialty) => specialty.slug !== doctor.primary_specialty?.slug)
    .map((specialty) => specialty.name)
    .join(", ");
  const place = doctor.primary_facility
    ? [
        doctor.primary_facility.name,
        doctor.primary_facility.city ?? doctor.city,
      ]
        .filter(Boolean)
        .join(", ")
    : doctor.city;
  const tel = telHref(doctor.phone);
  const featured = doctor.is_featured;

  return (
    <article
      data-featured={featured || undefined}
      className="card hover-lift flex h-full flex-col"
    >
      {featured ? (
        <div className="flex min-h-12 flex-wrap items-center gap-2 rounded-t-card bg-apricot px-5 py-2">
          <FeaturedMark />
          {doctor.is_sponsored ? <SponsoredBadge /> : null}
        </div>
      ) : null}
      <div className="flex flex-1 flex-col p-5">
        <div className="flex items-start gap-3 lg:gap-4">
          <DirectoryAvatar
            kind="doctor"
            avatarUrl={doctor.avatar_url}
            name={doctor.full_name}
            alt={doctor.full_name}
            size={featured ? 64 : 56}
          />
          <div className={cn("min-w-0 flex-1", featured ? "pt-2" : "pt-1")}>
            <h2 className="type-h3 text-ink">
              <Link href={href} className="link-grow">
                {doctor.full_name}
              </Link>
            </h2>
            {specialtyLine ? (
              <p className="type-meta mt-0.5 text-ink-2">{specialtyLine}</p>
            ) : null}
            {otherSpecialties ? (
              <p className="type-meta text-ink-2">
                {tFormat("doctors.alsoSpecialties", {
                  names: otherSpecialties,
                })}
              </p>
            ) : null}
          </div>
        </div>

        <div className="mt-4 flex flex-col gap-2">
          {place ? (
            <p className="flex items-start gap-2 type-body text-ink">
              <Icon name="map-pin" size={20} className="mt-0.5 text-ink-2" />
              {place}
            </p>
          ) : null}
          <RatingLine summary={doctor.review_summary} showStars={false} />
          {doctor.office_hours ? (
            <LiveOpenStatusLine hours={doctor.office_hours} />
          ) : null}
        </div>

        {doctor.accepts_new_patients ||
        doctor.years_experience ||
        (doctor.is_sponsored && !featured) ? (
          <div className="mt-3 flex flex-wrap gap-2">
            {doctor.accepts_new_patients ? (
              <Tag tone="care" icon="check">
                {t("doctors.acceptingPatients")}
              </Tag>
            ) : null}
            {doctor.years_experience ? (
              <Tag>
                {tFormat("doctors.yearsExperience", {
                  years: String(doctor.years_experience),
                })}
              </Tag>
            ) : null}
            {doctor.is_sponsored && !featured ? <SponsoredBadge /> : null}
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
                name: doctor.full_name,
              })}
            >
              {t("directory.call")}
            </Button>
          ) : null}
          <Button
            href={href}
            className="flex-1"
            aria-label={tFormat("directory.viewProfileOf", {
              name: doctor.full_name,
            })}
          >
            {t("doctors.viewProfile")}
          </Button>
        </div>
      </div>
    </article>
  );
}
