import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { StarRating } from "@/components/ui/star-rating";
import { cn } from "@/lib/cn";
import type { FacilityDetail, PharmacyDetail } from "@/lib/api/types";
import { facilityTypeLabel } from "@/lib/facility-labels";
import { t, tFormat } from "@/i18n/t";

type FacilityLike = FacilityDetail | PharmacyDetail;

function isClinicalFacility(facility: FacilityLike): facility is FacilityDetail {
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
    (isClinicalFacility(facility) ? facilityTypeLabel(facility.type) : t("nav.pharmacies"));

  const departments = isClinicalFacility(facility) ? facility.departments : [];
  const hasEmergency = isClinicalFacility(facility) && facility.has_emergency_services;

  return (
    <Card className="relative overflow-hidden p-0 shadow-[0_24px_60px_-36px_rgb(15_23_42/0.45)]">
      <div className="h-1.5 bg-gradient-to-r from-accent via-[color-mix(in_srgb,var(--color-accent)_70%,var(--color-primary))] to-primary" />

      <div className="flex flex-col gap-6 p-6 sm:flex-row sm:gap-8 sm:p-8">
        {facility.avatar_url ? (
          <img
            src={facility.avatar_url}
            alt=""
            className="mx-auto h-28 w-28 shrink-0 rounded-2xl border-4 border-card object-cover shadow-lg ring-2 ring-accent/15 sm:mx-0 sm:h-32 sm:w-32"
          />
        ) : (
          <div
            className={cn(
              "mx-auto flex h-28 w-28 shrink-0 items-center justify-center rounded-2xl border-4 border-card text-3xl font-bold shadow-inner ring-2 sm:mx-0 sm:h-32 sm:w-32",
              "bg-gradient-to-br from-accent/15 to-primary/15 text-accent ring-accent/15",
            )}
          >
            {facility.name.charAt(0)}
          </div>
        )}

        <div className="min-w-0 flex-1 space-y-4 text-center sm:text-left">
          <div className="space-y-2">
            <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
              {facility.name}
            </h1>
            <p className="flex flex-wrap items-center justify-center gap-2 text-base text-muted-foreground sm:justify-start">
              <PinGlyph className="h-4 w-4 shrink-0 text-accent/90" aria-hidden />
              {[label, facility.city].filter(Boolean).join(" · ")}
            </p>
            {facility.address ? (
              <p className="text-sm text-muted-foreground">{facility.address}</p>
            ) : null}
          </div>

          {facility.review_summary.count > 0 && facility.review_summary.average_rating !== null ? (
            <div className="flex flex-wrap items-center justify-center gap-3 sm:justify-start">
              <StarRating value={facility.review_summary.average_rating} size="md" tone="amber" />
              <span className="text-lg font-semibold tabular-nums">
                {facility.review_summary.average_rating}
              </span>
              <span className="text-sm text-muted-foreground">
                ({facility.review_summary.count})
              </span>
            </div>
          ) : (
            <div className="flex justify-center sm:justify-start">
              <Badge variant="outline">{label}</Badge>
            </div>
          )}

          {isClinicalFacility(facility) ? (
            <div className="flex flex-wrap justify-center gap-2 sm:justify-start">
              {hasEmergency ? (
                <Badge variant="warning">{t("facilities.emergencyAvailable")}</Badge>
              ) : (
                <Badge variant="secondary">{t("facilities.emergencyNotAvailable")}</Badge>
              )}
              {departments.length > 0 ? (
                <Badge variant="accent">
                  {tFormat("facilities.departmentCount", { count: String(departments.length) })}
                </Badge>
              ) : null}
            </div>
          ) : null}

          {facility.description ? (
            <p className="border-t border-border/60 pt-4 text-sm leading-relaxed text-muted-foreground">
              {facility.description}
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
