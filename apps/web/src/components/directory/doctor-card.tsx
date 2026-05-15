import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { StarRating } from "@/components/ui/star-rating";
import type { DoctorListItem } from "@/lib/api/types";
import { t, tFormat } from "@/i18n/t";

export function DoctorCard({ doctor }: { doctor: DoctorListItem }) {
  const specialtyLine = [
    doctor.primary_specialty?.name,
    doctor.subspecialty,
  ]
    .filter(Boolean)
    .join(" · ");

  return (
    <Link href={`/doctors/${doctor.slug}`} className="block h-full">
      <Card className="card-hover flex h-full flex-col gap-4 p-5">
        <div className="flex gap-4">
          {doctor.avatar_url ? (
            <img
              src={doctor.avatar_url}
              alt=""
              className="h-16 w-16 shrink-0 rounded-2xl border border-border object-cover"
            />
          ) : (
            <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-lg font-bold text-primary">
              {doctor.full_name.charAt(0)}
            </div>
          )}
          <div className="min-w-0 flex-1">
            <h2 className="font-semibold text-foreground">{doctor.full_name}</h2>
            {specialtyLine ? (
              <p className="mt-1 text-sm text-muted-foreground">{specialtyLine}</p>
            ) : null}
            {doctor.city ? (
              <p className="mt-1 text-sm text-muted-foreground">{doctor.city}</p>
            ) : null}
          </div>
        </div>
        <div className="mt-auto flex flex-wrap items-center gap-2">
          {doctor.review_summary.count > 0 &&
          doctor.review_summary.average_rating !== null ? (
            <div className="flex items-center gap-1.5 text-sm">
              <StarRating value={doctor.review_summary.average_rating} />
              <span className="font-medium">{doctor.review_summary.average_rating}</span>
              <span className="text-muted-foreground">
                ({doctor.review_summary.count})
              </span>
            </div>
          ) : null}
          {doctor.accepts_new_patients ? (
            <Badge variant="accent">{t("doctors.acceptingPatients")}</Badge>
          ) : null}
          {doctor.is_featured ? (
            <Badge variant="primary">{t("doctors.featured")}</Badge>
          ) : null}
          {doctor.years_experience ? (
            <Badge variant="outline">
              {tFormat("doctors.yearsExperience", { years: String(doctor.years_experience) })}
            </Badge>
          ) : null}
        </div>
      </Card>
    </Link>
  );
}
