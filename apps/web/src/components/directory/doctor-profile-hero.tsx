import Link from "next/link";
import { HeroMeshCard } from "@/components/design/hero-mesh-card";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { SponsoredBadge } from "@/components/ui/sponsored-badge";
import { StarRating } from "@/components/ui/star-rating";
import type { DoctorDetail } from "@/lib/api/types";
import { facilityPublicPath } from "@/lib/facility-labels";
import { t, tFormat } from "@/i18n/t";

export function DoctorProfileHero({ doctor }: { doctor: DoctorDetail }) {
  const primarySpecialty =
    doctor.specialties.find((s) => s.is_primary)?.name ?? doctor.specialties[0]?.name;

  const specialtyLine = [primarySpecialty, doctor.subspecialty].filter(Boolean).join(" · ");

  const workplace = doctor.facilities.find((f) => f.is_primary) ?? doctor.facilities[0];

  return (
    <HeroMeshCard variant="profile" align="start" innerClassName="w-full max-w-none">
      <div className="grid items-center gap-5 sm:grid-cols-[140px_minmax(0,1fr)] sm:gap-6">
        <DirectoryAvatar
          kind="doctor"
          avatarUrl={doctor.avatar_url}
          name={doctor.full_name}
          className="mx-auto h-[126px] w-[126px] rounded-[1.375rem] border border-white/90 shadow-[0_18px_44px_rgb(16_30_36_/_0.12)] sm:mx-0"
          fallbackClassName="text-4xl"
        />

        <div className="min-w-0 space-y-4 text-center sm:text-left">
          <div className="space-y-2">
            <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-2 sm:justify-start">
              <h1 className="text-3xl font-black tracking-tight text-foreground sm:text-4xl lg:text-[2.75rem] lg:leading-tight">
                {doctor.full_name}
              </h1>
              {doctor.is_sponsored ? <SponsoredBadge size="md" /> : null}
              {doctor.is_featured && !doctor.is_sponsored ? (
                <span className="rounded-full bg-[#fff1f1] px-3 py-1 text-xs font-extrabold text-primary">
                  {t("doctors.featured")}
                </span>
              ) : null}
            </div>
            {specialtyLine ? (
              <p className="text-base font-medium text-muted-foreground">{specialtyLine}</p>
            ) : null}

            {workplace ? (
              <p className="flex flex-wrap items-center justify-center gap-2 text-sm text-muted-foreground sm:justify-start">
                <PinGlyph className="h-4 w-4 shrink-0 text-accent" aria-hidden />
                <Link
                  href={facilityPublicPath(workplace.type, workplace.slug)}
                  className="font-semibold text-foreground underline-offset-4 hover:text-primary hover:underline"
                >
                  {workplace.name}
                  {workplace.city ? ` · ${workplace.city}` : ""}
                </Link>
              </p>
            ) : doctor.city ? (
              <p className="flex items-center justify-center gap-2 text-sm text-muted-foreground sm:justify-start">
                <PinGlyph className="h-4 w-4 text-accent" aria-hidden />
                {doctor.city}
              </p>
            ) : null}
          </div>

          {doctor.review_summary.count > 0 && doctor.review_summary.average_rating !== null ? (
            <div className="flex flex-wrap items-center justify-center gap-3 sm:justify-start">
              <StarRating value={doctor.review_summary.average_rating} size="md" />
              <span className="text-lg font-extrabold tabular-nums">
                {doctor.review_summary.average_rating}
              </span>
              <span className="text-sm text-muted-foreground">
                ({doctor.review_summary.count}{" "}
                {doctor.review_summary.count === 1 ? t("reviews.countOne") : t("reviews.count")})
              </span>
            </div>
          ) : null}

          <div className="flex flex-wrap justify-center gap-2.5 sm:justify-start">
            {doctor.accepts_new_patients ? (
              <span className="directory-tag directory-tag-teal">{t("doctors.acceptingPatients")}</span>
            ) : (
              <span className="directory-tag">{t("doctors.notAcceptingPatients")}</span>
            )}
            {doctor.years_experience ? (
              <span className="directory-tag">
                {tFormat("doctors.yearsExperience", {
                  years: String(doctor.years_experience),
                })}
              </span>
            ) : null}
            {doctor.consultation_fee_note ? (
              <span className="directory-tag">{doctor.consultation_fee_note}</span>
            ) : null}
          </div>

          {doctor.bio ? (
            <p className="border-t border-border/60 pt-4 text-sm leading-relaxed text-muted-foreground">
              {doctor.bio}
            </p>
          ) : null}
        </div>
      </div>
    </HeroMeshCard>
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
