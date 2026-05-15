import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { StarRating } from "@/components/ui/star-rating";
import type { FacilityDetail, PharmacyDetail } from "@/lib/api/types";
import { facilityTypeLabel } from "@/lib/facility-labels";
import { t } from "@/i18n/t";

type FacilityLike = FacilityDetail | PharmacyDetail;

export function FacilityProfileHero({
  facility,
  typeLabel,
}: {
  facility: FacilityLike & { type?: FacilityDetail["type"] };
  typeLabel?: string;
}) {
  const label =
    typeLabel ??
    ("type" in facility && facility.type
      ? facilityTypeLabel(facility.type)
      : t("nav.pharmacies"));

  return (
    <Card className="p-6">
      <div className="flex flex-col gap-5 sm:flex-row">
        {facility.avatar_url ? (
          <img
            src={facility.avatar_url}
            alt=""
            className="h-24 w-24 rounded-2xl border border-border object-cover"
          />
        ) : (
          <div className="flex h-24 w-24 items-center justify-center rounded-2xl bg-accent/10 text-2xl font-bold text-accent">
            {facility.name.charAt(0)}
          </div>
        )}
        <div className="min-w-0 flex-1 space-y-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
              {facility.name}
            </h1>
            <p className="mt-1 text-muted-foreground">
              {[label, facility.city].filter(Boolean).join(" · ")}
            </p>
          </div>
          {facility.review_summary.count > 0 &&
          facility.review_summary.average_rating !== null ? (
            <div className="flex flex-wrap items-center gap-2">
              <StarRating value={facility.review_summary.average_rating} size="md" />
              <span className="font-semibold">
                {facility.review_summary.average_rating}
              </span>
              <span className="text-sm text-muted-foreground">
                ({facility.review_summary.count})
              </span>
            </div>
          ) : (
            <Badge variant="outline">{label}</Badge>
          )}
        </div>
      </div>
      {facility.description ? (
        <p className="mt-4 text-sm leading-relaxed text-muted-foreground">
          {facility.description}
        </p>
      ) : null}
      {facility.address ? (
        <p className="mt-2 text-sm text-muted-foreground">{facility.address}</p>
      ) : null}
    </Card>
  );
}
