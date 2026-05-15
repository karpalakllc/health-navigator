import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { StarRating } from "@/components/ui/star-rating";
import type { FacilityListItem } from "@/lib/api/types";
import { facilityTypeLabel } from "@/lib/facility-labels";

export function FacilityCard({ facility }: { facility: FacilityListItem }) {
  return (
    <Link href={`/facilities/${facility.slug}`} className="block h-full">
      <Card className="card-hover flex h-full flex-col gap-4 p-5">
        <div className="flex gap-4">
          {facility.avatar_url ? (
            <img
              src={facility.avatar_url}
              alt=""
              className="h-14 w-14 shrink-0 rounded-2xl border border-border object-cover"
            />
          ) : (
            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-accent/10 text-lg font-bold text-accent">
              {facility.name.charAt(0)}
            </div>
          )}
          <div className="min-w-0 flex-1">
            <h2 className="font-semibold text-foreground">{facility.name}</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              {[facilityTypeLabel(facility.type), facility.city]
                .filter(Boolean)
                .join(" · ")}
            </p>
          </div>
        </div>
        {facility.review_summary.count > 0 &&
        facility.review_summary.average_rating !== null ? (
          <div className="mt-auto flex items-center gap-1.5 text-sm">
            <StarRating value={facility.review_summary.average_rating} />
            <span className="font-medium">{facility.review_summary.average_rating}</span>
            <span className="text-muted-foreground">
              ({facility.review_summary.count})
            </span>
          </div>
        ) : (
          <Badge variant="outline" className="mt-auto w-fit">
            {facilityTypeLabel(facility.type)}
          </Badge>
        )}
      </Card>
    </Link>
  );
}
