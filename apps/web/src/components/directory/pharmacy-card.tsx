"use client";

import Link from "next/link";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { StarRating } from "@/components/ui/star-rating";
import { cn } from "@/lib/cn";
import type { PharmacyListItem } from "@/lib/api/types";
import { t } from "@/i18n/t";

export function PharmacyCard({
  pharmacy,
  layout = "grid",
}: {
  pharmacy: PharmacyListItem;
  layout?: "grid" | "list";
}) {
  if (layout === "list") {
    return (
      <article className="directory-card card-lift flex h-full flex-col gap-4 rounded-[1.75rem] p-[22px] sm:flex-row sm:items-start">
        <PharmacyCardBody pharmacy={pharmacy} className="sm:flex-1" />
        <Link
          href={`/pharmacies/${pharmacy.slug}`}
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
        <PharmacyCardBody pharmacy={pharmacy} />
      </div>
      <Link
        href={`/pharmacies/${pharmacy.slug}`}
        className="btn-gradient-teal mt-[18px] inline-flex min-h-[54px] w-full shrink-0 items-center justify-center rounded-[1.125rem] text-sm font-extrabold text-white transition hover:brightness-105"
      >
        {t("home.viewProfile")}
      </Link>
    </article>
  );
}

function PharmacyCardBody({
  pharmacy,
  className,
}: {
  pharmacy: PharmacyListItem;
  className?: string;
}) {
  return (
    <div className={cn("flex min-h-0 flex-1 flex-col", className)}>
      <div className="flex items-start gap-3.5">
        <DirectoryAvatar
          kind="pharmacy"
          avatarUrl={pharmacy.avatar_url}
          name={pharmacy.name}
          className="h-[62px] w-[62px] shrink-0 rounded-[1.25rem] border border-border"
          imageClassName="h-full w-full rounded-[1.25rem] object-cover"
          fallbackClassName="h-full w-full rounded-[1.25rem] text-lg"
        />
        <div className="min-w-0">
          <h2 className="text-lg font-extrabold tracking-tight text-foreground">{pharmacy.name}</h2>
          {pharmacy.city ? (
            <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">{pharmacy.city}</p>
          ) : null}
        </div>
      </div>

      {pharmacy.city ? (
        <p className="mt-3.5 inline-flex items-center gap-2 text-sm text-[#5d6772]">
          <PinIcon className="h-4 w-4 shrink-0" aria-hidden />
          {pharmacy.city}
        </p>
      ) : null}

      <div className="mt-4 flex flex-wrap items-center gap-2.5">
        {pharmacy.review_summary.count > 0 && pharmacy.review_summary.average_rating !== null ? (
          <>
            <StarRating value={pharmacy.review_summary.average_rating} />
            <span className="text-sm font-bold text-[#485460]">
              {pharmacy.review_summary.average_rating} ({pharmacy.review_summary.count})
            </span>
          </>
        ) : (
          <span className="directory-tag directory-tag-teal">{t("nav.pharmacies")}</span>
        )}
      </div>
    </div>
  );
}

function PinIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M12 21s7-4.35 7-11a7 7 0 10-14 0c0 6.65 7 11 7 11z" strokeLinecap="round" />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  );
}
