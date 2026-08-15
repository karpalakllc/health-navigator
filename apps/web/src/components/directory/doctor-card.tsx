"use client";

import Link from "next/link";
import { useSitePlaceholders } from "@/components/layout/site-placeholders-provider";
import { StarRating } from "@/components/ui/star-rating";
import { cn } from "@/lib/cn";
import type { DoctorListItem } from "@/lib/api/types";
import { t, tFormat } from "@/i18n/t";

export function DoctorCard({
  doctor,
  layout = "grid",
}: {
  doctor: DoctorListItem;
  layout?: "grid" | "list";
}) {
  const placeholders = useSitePlaceholders();
  const avatarSrc = doctor.avatar_url ?? placeholders.doctor;
  const specialtyLine = [doctor.primary_specialty?.name, doctor.subspecialty]
    .filter(Boolean)
    .join(" · ");

  if (layout === "list") {
    return (
      <article
        className={cn(
          "directory-card card-lift flex h-full flex-col gap-4 rounded-[1.75rem] p-[22px] sm:flex-row sm:items-start",
          doctor.is_featured &&
            "border border-primary/15 shadow-[0_18px_52px_rgb(255_87_87_/_0.08)]",
        )}
      >
        <DoctorCardBody
          doctor={doctor}
          avatarSrc={avatarSrc}
          specialtyLine={specialtyLine}
          className="sm:flex-1"
        />
        <Link
          href={`/doctors/${doctor.slug}`}
          className="btn-gradient-teal inline-flex min-h-[54px] w-full shrink-0 items-center justify-center rounded-[1.125rem] px-8 text-sm font-extrabold text-white transition hover:brightness-105 sm:w-auto sm:self-center"
        >
          {t("home.viewProfile")}
        </Link>
      </article>
    );
  }

  return (
    <article
      className={cn(
        "directory-card card-lift flex h-full flex-col rounded-[1.75rem] p-[22px]",
        doctor.is_featured &&
          "border border-primary/15 shadow-[0_18px_52px_rgb(255_87_87_/_0.08)]",
      )}
    >
      <div className="flex min-h-0 flex-1 flex-col">
        <DoctorCardBody
          doctor={doctor}
          avatarSrc={avatarSrc}
          specialtyLine={specialtyLine}
        />
      </div>
      <Link
        href={`/doctors/${doctor.slug}`}
        className="btn-gradient-teal mt-[18px] inline-flex min-h-[54px] w-full shrink-0 items-center justify-center rounded-[1.125rem] text-sm font-extrabold text-white transition hover:brightness-105"
      >
        {t("home.viewProfile")}
      </Link>
    </article>
  );
}

function DoctorCardBody({
  doctor,
  avatarSrc,
  specialtyLine,
  className,
}: {
  doctor: DoctorListItem;
  avatarSrc: string | null;
  specialtyLine: string;
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
              {doctor.full_name.charAt(0)}
            </div>
          )}
          <div className="min-w-0">
            <h2 className="text-lg font-extrabold tracking-tight text-foreground">
              {doctor.full_name}
            </h2>
            {specialtyLine ? (
              <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                {specialtyLine}
              </p>
            ) : null}
            {doctor.city ? (
              <p className="mt-1 text-sm text-[#7c8893]">{doctor.city}</p>
            ) : null}
          </div>
        </div>
        {doctor.is_featured ? (
          <span className="shrink-0 rounded-full bg-[#fff1f1] px-2.5 py-1 text-xs font-extrabold text-primary">
            {t("doctors.featured")}
          </span>
        ) : null}
      </div>

      {doctor.city ? (
        <p className="mt-3.5 inline-flex items-center gap-2 text-sm text-[#5d6772]">
          <PinIcon className="h-4 w-4 shrink-0" aria-hidden />
          {doctor.city}
        </p>
      ) : null}

      <div className="mt-4 flex flex-wrap items-center gap-2.5">
        {doctor.review_summary.count > 0 &&
        doctor.review_summary.average_rating !== null ? (
          <>
            <StarRating value={doctor.review_summary.average_rating} />
            <span className="text-sm font-bold text-[#485460]">
              {doctor.review_summary.average_rating} (
              {doctor.review_summary.count})
            </span>
          </>
        ) : null}
        {doctor.accepts_new_patients ? (
          <span className="inline-flex min-h-[34px] items-center rounded-full bg-[#eef8f8] px-3 text-xs font-bold text-accent">
            {t("doctors.acceptingPatients")}
          </span>
        ) : null}
      </div>

      {doctor.years_experience ? (
        <div className="mt-3">
          <span className="inline-flex min-h-[34px] items-center rounded-full bg-[#f1f4f6] px-3 text-xs font-bold text-[#5d6771]">
            {tFormat("doctors.yearsExperience", {
              years: String(doctor.years_experience),
            })}
          </span>
        </div>
      ) : null}
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
