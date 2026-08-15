import Link from "next/link";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import type { DoctorListItem } from "@/lib/api/types";
import { cn } from "@/lib/cn";
import { t, tFormat } from "@/i18n/t";

export function HomeFeaturedDoctorCard({
  doctor,
  inRail = false,
}: {
  doctor: DoctorListItem;
  inRail?: boolean;
}) {
  const specialtyLine = [doctor.primary_specialty?.name, doctor.subspecialty]
    .filter(Boolean)
    .join(" · ");
  const city = doctor.city;
  const rating = doctor.review_summary.average_rating;
  const reviewCount = doctor.review_summary.count;

  return (
    <Link
      href={`/doctors/${doctor.slug}`}
      className="block h-full min-h-[44px]"
    >
      <article
        className={cn(
          "surface-glass flex h-full flex-col rounded-[28px] p-[22px]",
          inRail
            ? "shadow-[0_14px_40px_rgb(16_30_36/0.07)] transition-[transform,box-shadow] duration-300 ease-out hover:-translate-y-1 hover:shadow-[0_22px_56px_rgb(16_30_36/0.1)]"
            : "card-lift",
        )}
      >
        <div className="flex items-start justify-between gap-3">
          <div className="flex min-w-0 gap-3.5">
            <DirectoryAvatar
              avatarUrl={doctor.avatar_url}
              name={doctor.full_name}
              kind="doctor"
              className="h-[62px] w-[62px] shrink-0 rounded-[20px]"
            />
            <div className="min-w-0">
              <h3 className="text-[1.08rem] font-extrabold tracking-tight text-foreground">
                {doctor.full_name}
              </h3>
              {specialtyLine ? (
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                  {specialtyLine}
                </p>
              ) : null}
              {city ? (
                <p className="mt-2 text-sm text-[#7c8893]">{city}</p>
              ) : null}
            </div>
          </div>
          {doctor.is_featured ? (
            <span className="inline-flex min-h-[30px] shrink-0 items-center rounded-full bg-[#fff1f1] px-2.5 text-[0.78rem] font-extrabold text-primary">
              {t("doctors.featured")}
            </span>
          ) : null}
        </div>

        {reviewCount > 0 && rating !== null ? (
          <div className="mt-[18px] flex flex-wrap items-center gap-2.5">
            <span className="inline-flex gap-1 text-primary" aria-hidden>
              {Array.from({ length: 5 }).map((_, i) => (
                <StarIcon
                  key={i}
                  className="h-4 w-4"
                  filled={i < Math.round(rating)}
                />
              ))}
            </span>
            <span className="text-[0.94rem] font-bold text-[#485460]">
              {rating} ({reviewCount})
            </span>
            {doctor.accepts_new_patients ? (
              <span className="inline-flex min-h-[34px] items-center rounded-full bg-[#eef8f8] px-3 text-[0.82rem] font-bold text-accent">
                {t("home.availableToday")}
              </span>
            ) : null}
          </div>
        ) : doctor.accepts_new_patients ? (
          <div className="mt-[18px]">
            <span className="inline-flex min-h-[34px] items-center rounded-full bg-[#eef8f8] px-3 text-[0.82rem] font-bold text-accent">
              {t("home.availableToday")}
            </span>
          </div>
        ) : null}

        {doctor.years_experience ? (
          <div className="mt-3">
            <span className="inline-flex min-h-[34px] items-center rounded-full bg-[#f1f4f6] px-3 text-[0.82rem] font-bold text-[#5d6771]">
              {tFormat("doctors.yearsExperience", {
                years: String(doctor.years_experience),
              })}
            </span>
          </div>
        ) : null}
      </article>
    </Link>
  );
}

function StarIcon({
  className,
  filled,
}: {
  className?: string;
  filled?: boolean;
}) {
  return (
    <svg
      className={className}
      viewBox="0 0 20 20"
      fill={filled ? "currentColor" : "none"}
      stroke="currentColor"
      aria-hidden
    >
      <path
        strokeWidth={filled ? 0 : 1.5}
        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
      />
    </svg>
  );
}
