import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { StarRating } from "@/components/ui/star-rating";
import type { DoctorDetail } from "@/lib/api/types";
import { t, tFormat } from "@/i18n/t";

export function DoctorProfileHero({ doctor }: { doctor: DoctorDetail }) {
  const primarySpecialty = doctor.specialties.find((s) => s.is_primary)?.name
    ?? doctor.specialties[0]?.name;

  const specialtyLine = [primarySpecialty, doctor.subspecialty].filter(Boolean).join(" · ");

  return (
    <Card className="p-6">
      <div className="flex flex-col gap-5 sm:flex-row">
        {doctor.avatar_url ? (
          <img
            src={doctor.avatar_url}
            alt=""
            className="h-24 w-24 rounded-2xl border border-border object-cover"
          />
        ) : (
          <div className="flex h-24 w-24 items-center justify-center rounded-2xl bg-primary/10 text-2xl font-bold text-primary">
            {doctor.full_name.charAt(0)}
          </div>
        )}
        <div className="min-w-0 flex-1 space-y-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
              {doctor.full_name}
            </h1>
            {specialtyLine ? (
              <p className="mt-1 text-muted-foreground">{specialtyLine}</p>
            ) : null}
            {doctor.city ? (
              <p className="text-sm text-muted-foreground">{doctor.city}</p>
            ) : null}
          </div>
          {doctor.review_summary.count > 0 &&
          doctor.review_summary.average_rating !== null ? (
            <div className="flex flex-wrap items-center gap-2">
              <StarRating value={doctor.review_summary.average_rating} size="md" />
              <span className="font-semibold">{doctor.review_summary.average_rating}</span>
              <span className="text-sm text-muted-foreground">
                ({doctor.review_summary.count}{" "}
                {doctor.review_summary.count === 1
                  ? t("reviews.countOne")
                  : t("reviews.count")})
              </span>
            </div>
          ) : null}
          <div className="flex flex-wrap gap-2">
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
        </div>
      </div>
      {doctor.bio ? (
        <p className="mt-4 text-sm leading-relaxed text-muted-foreground">{doctor.bio}</p>
      ) : null}
    </Card>
  );
}
