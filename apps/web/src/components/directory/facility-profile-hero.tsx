import { HeroMeshCard } from "@/components/design/hero-mesh-card";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { StarRating } from "@/components/ui/star-rating";
import type { FacilityDetail, PharmacyDetail } from "@/lib/api/types";
import { facilityTypeLabel } from "@/lib/facility-labels";
import { t, tFormat } from "@/i18n/t";

type FacilityLike = FacilityDetail | PharmacyDetail;

function isClinicalFacility(
  facility: FacilityLike,
): facility is FacilityDetail {
  return "type" in facility;
}

export function FacilityProfileHero({
  facility,
  typeLabel,
}: {
  facility: FacilityLike;
  typeLabel?: string;
}) {
  const label =
    typeLabel ??
    (isClinicalFacility(facility)
      ? facilityTypeLabel(facility.type)
      : t("nav.pharmacies"));

  const departments = isClinicalFacility(facility) ? facility.departments : [];
  const hasEmergency =
    isClinicalFacility(facility) && facility.has_emergency_services;

  return (
    <HeroMeshCard
      variant="profile"
      align="start"
      innerClassName="w-full max-w-none"
    >
      <div className="grid items-center gap-5 sm:grid-cols-[140px_minmax(0,1fr)] sm:gap-6">
        <DirectoryAvatar
          kind={isClinicalFacility(facility) ? "facility" : "pharmacy"}
          avatarUrl={facility.avatar_url}
          name={facility.name}
          className="mx-auto h-[126px] w-[126px] rounded-[1.375rem] border border-white/90 shadow-[0_18px_44px_rgb(16_30_36_/_0.12)] sm:mx-0"
          fallbackClassName="text-4xl"
        />

        <div className="min-w-0 space-y-4 text-center sm:text-left">
          <div className="space-y-2">
            <h1 className="text-3xl font-black tracking-tight text-foreground sm:text-4xl lg:text-[2.75rem] lg:leading-tight">
              {facility.name}
            </h1>
            <p className="flex flex-wrap items-center justify-center gap-2 text-base text-muted-foreground sm:justify-start">
              <PinGlyph className="h-4 w-4 shrink-0 text-accent" aria-hidden />
              {[label, facility.city].filter(Boolean).join(" · ")}
            </p>
            {facility.address ? (
              <p className="text-sm text-muted-foreground">
                {facility.address}
              </p>
            ) : null}
          </div>

          {facility.review_summary.count > 0 &&
          facility.review_summary.average_rating !== null ? (
            <div className="flex flex-wrap items-center justify-center gap-3 sm:justify-start">
              <StarRating
                value={facility.review_summary.average_rating}
                size="md"
              />
              <span className="text-lg font-extrabold tabular-nums">
                {facility.review_summary.average_rating}
              </span>
              <span className="text-sm text-muted-foreground">
                ({facility.review_summary.count}{" "}
                {facility.review_summary.count === 1
                  ? t("reviews.countOne")
                  : t("reviews.count")}
                )
              </span>
            </div>
          ) : null}

          {isClinicalFacility(facility) ? (
            <div className="flex flex-wrap justify-center gap-2.5 sm:justify-start">
              {hasEmergency ? (
                <span className="directory-tag bg-amber-500/15 text-amber-900">
                  {t("facilities.emergencyAvailable")}
                </span>
              ) : (
                <span className="directory-tag">
                  {t("facilities.emergencyNotAvailable")}
                </span>
              )}
              {departments.length > 0 ? (
                <span className="directory-tag directory-tag-teal">
                  {tFormat("facilities.departmentCount", {
                    count: String(departments.length),
                  })}
                </span>
              ) : null}
            </div>
          ) : (
            <div className="flex justify-center sm:justify-start">
              <span className="directory-tag directory-tag-teal">{label}</span>
            </div>
          )}

          {facility.description ? (
            <p className="border-t border-border/60 pt-4 text-sm leading-relaxed text-muted-foreground">
              {facility.description}
            </p>
          ) : null}
        </div>
      </div>
    </HeroMeshCard>
  );
}

function PinGlyph({ className }: { className?: string }) {
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
        strokeLinejoin="round"
      />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  );
}
