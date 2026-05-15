import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { StarRating } from "@/components/ui/star-rating";
import type { PharmacyListItem } from "@/lib/api/types";
import { t } from "@/i18n/t";

export function PharmacyCard({ pharmacy }: { pharmacy: PharmacyListItem }) {
  return (
    <Link href={`/pharmacies/${pharmacy.slug}`} className="block h-full">
      <Card className="card-hover flex h-full flex-col gap-4 p-5">
        <div className="flex gap-4">
          {pharmacy.avatar_url ? (
            <img
              src={pharmacy.avatar_url}
              alt=""
              className="h-14 w-14 shrink-0 rounded-2xl border border-border object-cover"
            />
          ) : (
            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-teal-500/10 text-lg font-bold text-teal-700">
              {pharmacy.name.charAt(0)}
            </div>
          )}
          <div className="min-w-0 flex-1">
            <h2 className="font-semibold text-foreground">{pharmacy.name}</h2>
            {pharmacy.city ? (
              <p className="mt-1 text-sm text-muted-foreground">{pharmacy.city}</p>
            ) : null}
          </div>
        </div>
        {pharmacy.review_summary.count > 0 &&
        pharmacy.review_summary.average_rating !== null ? (
          <div className="mt-auto flex items-center gap-1.5 text-sm">
            <StarRating value={pharmacy.review_summary.average_rating} />
            <span className="font-medium">{pharmacy.review_summary.average_rating}</span>
            <span className="text-muted-foreground">
              ({pharmacy.review_summary.count})
            </span>
          </div>
        ) : (
          <Badge variant="accent" className="mt-auto w-fit">
            {t("nav.pharmacies")}
          </Badge>
        )}
      </Card>
    </Link>
  );
}
