import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { SponsoredBadge } from "@/components/ui/sponsored-badge";
import { StarRating } from "@/components/ui/star-rating";
import { cn } from "@/lib/cn";
import type { DoctorDetail } from "@/lib/api/types";
import { facilityPublicPath } from "@/lib/facility-labels";
import { t, tFormat } from "@/i18n/t";

export function DoctorProfileHero({ doctor }: { doctor: DoctorDetail }) {
  const primarySpecialty =
    doctor.specialties.find((s) => s.is_primary)?.name ?? doctor.specialties[0]?.name;

  const specialtyLine = [primarySpecialty, doctor.subspecialty].filter(Boolean).join(" · ");

  const workplace = doctor.facilities.find((f) => f.is_primary) ?? doctor.facilities[0];

  return (
    <Card
      className={cn(
        "relative overflow-hidden p-0 shadow-[0_24px_60px_-36px_rgb(15_23_42/0.45)]",
        doctor.is_featured && "border-primary/20 ring-1 ring-primary/10",
      )}
    >
      <div className="h-1.5 bg-gradient-to-r from-primary via-[color-mix(in_srgb,var(--color-primary)_70%,var(--color-accent))] to-accent" />

      <div className="flex flex-col gap-6 p-6 sm:flex-row sm:gap-8 sm:p-8">
        {doctor.avatar_url ? (
          <img
            src={doctor.avatar_url}
            alt=""
            className="mx-auto h-28 w-28 shrink-0 rounded-full border-4 border-card object-cover shadow-lg ring-2 ring-primary/10 sm:mx-0 sm:h-32 sm:w-32"
          />
        ) : (
          <div className="mx-auto flex h-28 w-28 shrink-0 items-center justify-center rounded-full border-4 border-card bg-gradient-to-br from-primary/15 to-accent/15 text-3xl font-bold text-primary shadow-inner ring-2 ring-primary/10 sm:mx-0 sm:h-32 sm:w-32">
            {doctor.full_name.charAt(0)}
          </div>
        )}

        <div className="min-w-0 flex-1 space-y-4 text-center sm:text-left">
          <div className="space-y-2">
            <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-2 sm:justify-start">
              <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                {doctor.full_name}
              </h1>
              {doctor.is_featured ? <SponsoredBadge size="md" /> : null}
            </div>
            {specialtyLine ? (
              <p className="text-base text-muted-foreground">{specialtyLine}</p>
            ) : null}

            {workplace ? (
              <p className="flex flex-wrap items-center justify-center gap-2 text-sm text-muted-foreground sm:justify-start">
                <PinGlyph className="h-4 w-4 shrink-0 text-primary/80" aria-hidden />
                <Link
                  href={facilityPublicPath(workplace.type, workplace.slug)}
                  className="font-medium text-foreground underline-offset-4 hover:text-primary hover:underline"
                >
                  {workplace.name}
                  {workplace.city ? ` · ${workplace.city}` : ""}
                </Link>
              </p>
            ) : doctor.city ? (
              <p className="flex items-center justify-center gap-2 text-sm text-muted-foreground sm:justify-start">
                <PinGlyph className="h-4 w-4 text-primary/80" aria-hidden />
                {doctor.city}
              </p>
            ) : null}
          </div>

          {doctor.review_summary.count > 0 && doctor.review_summary.average_rating !== null ? (
            <div className="flex flex-wrap items-center justify-center gap-3 sm:justify-start">
              <StarRating value={doctor.review_summary.average_rating} size="md" tone="amber" />
              <span className="text-lg font-semibold tabular-nums">
                {doctor.review_summary.average_rating}
              </span>
              <span className="text-sm text-muted-foreground">
                ({doctor.review_summary.count}{" "}
                {doctor.review_summary.count === 1 ? t("reviews.countOne") : t("reviews.count")})
              </span>
            </div>
          ) : null}

          <div className="flex flex-wrap justify-center gap-2 sm:justify-start">
            {doctor.accepts_new_patients ? (
              <Badge variant="accent">{t("doctors.acceptingPatients")}</Badge>
            ) : (
              <Badge variant="warning">{t("doctors.notAcceptingPatients")}</Badge>
            )}
            {doctor.years_experience ? (
              <Badge variant="outline">
                {tFormat("doctors.yearsExperience", {
                  years: String(doctor.years_experience),
                })}
              </Badge>
            ) : null}
            {doctor.consultation_fee_note ? (
              <Badge variant="secondary">{doctor.consultation_fee_note}</Badge>
            ) : null}
          </div>

          {doctor.bio ? (
            <p className="border-t border-border/60 pt-4 text-sm leading-relaxed text-muted-foreground">
              {doctor.bio}
            </p>
          ) : null}
        </div>
      </div>
    </Card>
  );
}

function PinGlyph({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M12 21s7-4.35 7-11a7 7 0 10-14 0c0 6.65 7 11 7 11z" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  );
}
