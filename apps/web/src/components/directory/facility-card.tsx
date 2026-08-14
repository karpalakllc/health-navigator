"use client";

import Link from "next/link";
import { useSitePlaceholders } from "@/components/layout/site-placeholders-provider";
import { StarRating } from "@/components/ui/star-rating";
import { cn } from "@/lib/cn";
import type { FacilityListItem } from "@/lib/api/types";
import { facilityKindLabel } from "@/lib/facility-labels";
import { t } from "@/i18n/t";

export function FacilityCard({
  facility,
  layout = "grid",
}: {
  facility: FacilityListItem;
  layout?: "grid" | "list";
}) {
  const placeholders = useSitePlaceholders();
  const avatarSrc = facility.avatar_url ?? placeholders.facility;
  const subtitle = [facilityKindLabel(facility.type), facility.city]
    .filter(Boolean)
    .join(" · ");

  if (layout === "list") {
    return (
      <article className="directory-card card-lift flex h-full flex-col gap-4 rounded-[1.75rem] p-[22px] sm:flex-row sm:items-start">
        <FacilityCardBody
          facility={facility}
          avatarSrc={avatarSrc}
          subtitle={subtitle}
          className="sm:flex-1"
        />
        <Link
          href={`/facilities/${facility.slug}`}
          className="btn-gradient-teal inline-flex min-h-[54px] w-full shrink-0 items-center justify-center rounded-[1.125rem] px-8 text-sm font-extrabold text-white transition hover:brightness-105 sm:w-auto sm:self-center"
        >
          {t("home.viewProfile")}
        </Link>
      </article>
    );
  }

  return (
    <article className="directory-card card-lift flex h-full flex-col rounded-[1.75rem] p-[22px]">
      <div className="flex min-h-0 flex-1 flex-col">
        <FacilityCardBody
          facility={facility}
          avatarSrc={avatarSrc}
          subtitle={subtitle}
        />
      </div>
      <Link
        href={`/facilities/${facility.slug}`}
        className="btn-gradient-teal mt-[18px] inline-flex min-h-[54px] w-full shrink-0 items-center justify-center rounded-[1.125rem] text-sm font-extrabold text-white transition hover:brightness-105"
      >
        {t("home.viewProfile")}
      </Link>
    </article>
  );
}

function FacilityCardBody({
  facility,
  avatarSrc,
  subtitle,
  className,
}: {
  facility: FacilityListItem;
  avatarSrc: string | null;
  subtitle: string;
  className?: string;
}) {
  return (
    <div className={cn("flex min-h-0 flex-1 flex-col", className)}>
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 gap-3.5">
          {avatarSrc ? (
            // Remote admin-uploaded avatar; next/image would 400 in production because
            // images.remotePatterns cannot read NEXT_PUBLIC_API_URL. See next.config.ts.
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={avatarSrc}
              alt=""
              className="h-[62px] w-[62px] shrink-0 rounded-[1.25rem] border border-border object-cover"
            />
          ) : (
            <div className="flex h-[62px] w-[62px] shrink-0 items-center justify-center rounded-[1.25rem] border border-[#e7edf0] bg-gradient-to-b from-[#f2f5f7] to-[#eef2f4] text-lg font-bold text-[#7a8691]">
              {facility.name.charAt(0)}
            </div>
          )}
          <div className="min-w-0">
            <h2 className="text-lg font-extrabold tracking-tight text-foreground">
              {facility.name}
            </h2>
            {subtitle ? (
              <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                {subtitle}
              </p>
            ) : null}
          </div>
        </div>
        {facility.has_emergency_services ? (
          <span className="shrink-0 rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-extrabold text-amber-800">
            {t("facilities.emergencyBadge")}
          </span>
        ) : null}
      </div>

      {facility.city ? (
        <p className="mt-3.5 inline-flex items-center gap-2 text-sm text-[#5d6772]">
          <PinIcon className="h-4 w-4 shrink-0" aria-hidden />
          {facility.city}
        </p>
      ) : null}

      <div className="mt-4 flex flex-wrap items-center gap-2.5">
        {facility.review_summary.count > 0 &&
        facility.review_summary.average_rating !== null ? (
          <>
            <StarRating value={facility.review_summary.average_rating} />
            <span className="text-sm font-bold text-[#485460]">
              {facility.review_summary.average_rating} (
              {facility.review_summary.count})
            </span>
          </>
        ) : (
          <span className="directory-tag">
            {facilityKindLabel(facility.type)}
          </span>
        )}
      </div>
    </div>
  );
}

function PinIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
      <path
        d="M12 21s7-4.35 7-11a7 7 0 10-14 0c0 6.65 7 11 7 11z"
        strokeLinecap="round"
      />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  );
}
