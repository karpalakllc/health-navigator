import { Button, TextLink } from "@/components/ui/button";
import { SponsoredBadge } from "@/components/ui/sponsored-badge";
import { StarRating } from "@/components/ui/star-rating";
import { Tag } from "@/components/ui/tag";
import type { DoctorListItem } from "@/lib/api/types";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

/*
 * The mockup's „Дежурни аптеки денес“ slot. The API has no on-duty pharmacy
 * data (no duty roster, no opening hours on list items), so the home shows the
 * closest real rail instead: featured / top-rated doctors from /doctors.
 */

function doctorMeta(doctor: DoctorListItem): string {
  return [doctor.primary_specialty?.name, doctor.city]
    .filter(Boolean)
    .join(" · ");
}

/** „Види профил: д-р …“ — starts with the visible label (WCAG 2.5.3). */
function profileLabel(visible: string, doctor: DoctorListItem): string {
  return `${visible}: ${doctor.full_name}`;
}

function Rating({ doctor }: { doctor: DoctorListItem }) {
  const { average_rating: average, count } = doctor.review_summary;
  if (average === null || count === 0) {
    return null;
  }

  return (
    <span className="inline-flex items-center gap-1.5">
      <StarRating value={average} size="sm" />
      <span className="type-meta font-semibold text-ink">
        {average.toFixed(1).replace(".", ",")}
      </span>
      <span className="type-meta text-ink-2">({count})</span>
    </span>
  );
}

function StatusTag({ doctor }: { doctor: DoctorListItem }) {
  if (doctor.is_sponsored) {
    return <SponsoredBadge />;
  }
  if (doctor.accepts_new_patients) {
    return (
      <Tag tone="care" icon="check">
        {t("doctors.acceptingPatients")}
      </Tag>
    );
  }
  return null;
}

/** Desktop: white card in the hero's right column, three rows. */
export function HomeFeaturedDoctorsCard({
  doctors,
}: {
  doctors: DoctorListItem[];
}) {
  if (doctors.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="home-featured-card-title" className="card p-6">
      <h2 id="home-featured-card-title" className="type-h3 text-ink">
        {t("home.featuredDoctors")}
      </h2>
      <ul className="mt-2">
        {doctors.slice(0, 3).map((doctor, index) => (
          <li
            key={doctor.slug}
            className={cn(
              "flex items-center gap-4 py-3.5",
              index > 0 && "border-t border-line",
            )}
          >
            <div className="min-w-0 flex-1">
              <h3 className="font-ui text-lg font-semibold leading-6 text-ink">
                {doctor.full_name}
              </h3>
              <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                <span className="type-meta text-ink-2">
                  {doctorMeta(doctor)}
                </span>
                <Rating doctor={doctor} />
                {doctor.is_sponsored ? <SponsoredBadge /> : null}
              </div>
            </div>
            <Button
              href={`/doctors/${doctor.slug}`}
              variant="soft"
              size="sm"
              trailingIcon="chevron-right"
              aria-label={profileLabel(t("doctors.viewProfile"), doctor)}
            >
              {t("doctors.viewProfile")}
            </Button>
          </li>
        ))}
      </ul>
      <div className="border-t border-line pt-1">
        <TextLink href="/doctors" trailingIcon="arrow-right">
          {t("home.topRatedViewAll")}
        </TextLink>
      </div>
    </section>
  );
}

/** Mobile/tablet: a horizontal rail of cards below the tiles. */
export function HomeFeaturedDoctorsRail({
  doctors,
  className,
}: {
  doctors: DoctorListItem[];
  className?: string;
}) {
  if (doctors.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="home-featured-rail-title" className={className}>
      <h2 id="home-featured-rail-title" className="type-h2 px-5 text-ink">
        {t("home.featuredDoctors")}
      </h2>
      <ul className="scroll-row mt-2 flex gap-3 overflow-x-auto px-5 pb-4 pt-2">
        {doctors.map((doctor) => (
          <li
            key={doctor.slug}
            className="card flex w-[260px] flex-none flex-col p-4"
          >
            <div className="flex min-h-8 flex-wrap gap-2">
              <StatusTag doctor={doctor} />
            </div>
            <h3 className="mt-3 font-ui text-lg font-semibold leading-6 text-ink">
              {doctor.full_name}
            </h3>
            <p className="mt-0.5 text-[0.9375rem] leading-[1.375rem] text-ink-2">
              {doctorMeta(doctor)}
            </p>
            <div className="mt-1 min-h-6">
              <Rating doctor={doctor} />
            </div>
            <div className="mt-auto pt-4">
              <Button
                href={`/doctors/${doctor.slug}`}
                variant="soft"
                fullWidth
                aria-label={profileLabel(t("doctors.viewProfile"), doctor)}
              >
                {t("doctors.viewProfile")}
              </Button>
            </div>
          </li>
        ))}
      </ul>
      <div className="px-5 pt-1">
        <TextLink href="/doctors" trailingIcon="arrow-right">
          {t("home.topRatedViewAll")}
        </TextLink>
      </div>
    </section>
  );
}
