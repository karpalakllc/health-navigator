import { FeaturedMark } from "@/components/directory/cover-media";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { HomeCarousel } from "@/components/home/home-carousel";
import { Button, TextLink } from "@/components/ui/button";
import { SectionHeader } from "@/components/ui/section-header";
import { SponsoredBadge } from "@/components/ui/sponsored-badge";
import { StarRating } from "@/components/ui/star-rating";
import { Tag } from "@/components/ui/tag";
import type { DoctorListItem } from "@/lib/api/types";
import { cn } from "@/lib/cn";
import { formatRating } from "@/lib/rating";
import { t, tFormat } from "@/i18n/t";

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

function Rating({ doctor }: { doctor: DoctorListItem }) {
  const { average_rating: average, count } = doctor.review_summary;
  if (average === null || count === 0) {
    return null;
  }

  return (
    <span className="inline-flex items-center gap-1.5">
      <StarRating value={average} size="sm" />
      <span className="type-meta font-semibold text-ink">
        {formatRating(average)}
      </span>
      <span className="type-meta text-ink-2">({count})</span>
    </span>
  );
}

/**
 * One rail card, built like the featured card on /doctors: a featured doctor
 * gets the apricot band with „Истакнат“ (and „Спонзорирано“ when paid) across
 * the top. Below: photo beside name and specialty, the rating, the
 * „Прима нови пациенти“ tag on its own line (never squeezed beside the
 * photo), and a secondary „Види профил“ pill.
 *
 * The card's five parts are rows of the rail's grid (subgrid), so names,
 * ratings, tags and buttons line up across cards even when only some carry
 * the band — like a pricing table's „most popular“ ribbon.
 */
function FeaturedDoctorCard({ doctor }: { doctor: DoctorListItem }) {
  const featured = doctor.is_featured;
  const meta = doctorMeta(doctor);

  return (
    <article
      data-featured={featured || undefined}
      className="card hover-lift row-span-5 grid w-full grid-rows-subgrid"
    >
      {featured ? (
        <div
          data-featured-band=""
          className="flex min-h-11 flex-wrap items-center gap-2 rounded-t-card bg-apricot px-4 py-1.5 lg:px-5"
        >
          <FeaturedMark />
          {doctor.is_sponsored ? <SponsoredBadge /> : null}
        </div>
      ) : (
        <div aria-hidden="true" />
      )}

      <div
        data-doctor-identity=""
        className="flex items-center gap-3 px-4 pt-4 lg:px-5 lg:pt-5"
      >
        <DirectoryAvatar
          kind="doctor"
          avatarUrl={doctor.avatar_url}
          name={doctor.full_name}
          size={56}
        />
        <div className="min-w-0 flex-1">
          <h3 className="font-ui text-lg font-semibold leading-6 text-ink">
            {doctor.full_name}
          </h3>
          {meta ? (
            <p className="mt-0.5 text-[0.9375rem] leading-5 text-ink-2">
              {meta}
            </p>
          ) : null}
        </div>
      </div>

      <div className="flex min-h-6 items-center px-4 pt-3 lg:px-5">
        <Rating doctor={doctor} />
      </div>

      <div
        data-doctor-tags=""
        className="flex flex-wrap content-start gap-2 px-4 pt-3 lg:px-5"
      >
        {doctor.accepts_new_patients ? (
          <Tag tone="care" icon="check">
            {t("doctors.acceptingPatients")}
          </Tag>
        ) : null}
        {doctor.is_sponsored && !featured ? <SponsoredBadge /> : null}
      </div>

      <div className="self-end px-4 pb-4 pt-4 lg:px-5 lg:pb-5">
        <Button
          href={`/doctors/${doctor.slug}`}
          variant="secondary"
          fullWidth
          trailingIcon="arrow-right"
          aria-label={tFormat("directory.viewProfileOf", {
            name: doctor.full_name,
          })}
        >
          {t("doctors.viewProfile")}
        </Button>
      </div>
    </article>
  );
}

/**
 * „Истакнати лекари“ as a carousel (HomeCarousel): cards of one width that
 * snap one by one, the next card peeking in, a „1 од 6“ line with dots and,
 * from md, previous/next buttons. Phones show one card and a peek; desktop
 * three side by side. Either way a grid whose five rows the cards share
 * (see above), so names, ratings and buttons line up across cards.
 */
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
      <SectionHeader
        id="home-featured-rail-title"
        title={t("home.featuredDoctors")}
        action={
          // Phones: the title alone fits the row; the link follows the rail.
          <span className="hidden lg:block">
            <TextLink href="/doctors" trailingIcon="arrow-right">
              {t("home.topRatedViewAll")}
            </TextLink>
          </span>
        }
        className="items-center px-5 lg:px-0"
      />
      <HomeCarousel
        listClassName={cn(
          "mt-1 grid snap-x snap-mandatory scroll-px-5 auto-cols-[min(18rem,calc(100vw-5rem))] grid-flow-col grid-rows-[repeat(5,auto)] gap-x-3 px-5 pb-6 pt-2 sm:auto-cols-[18.5rem]",
          "lg:-mx-3 lg:mt-3 lg:scroll-px-3 lg:auto-cols-[calc((100%-3rem)/3)] lg:gap-x-6 lg:px-3",
        )}
        controlsClassName="px-5 lg:px-0"
      >
        {doctors.map((doctor) => (
          <li
            key={doctor.slug}
            className="row-span-5 grid snap-start grid-rows-subgrid"
          >
            <FeaturedDoctorCard doctor={doctor} />
          </li>
        ))}
      </HomeCarousel>
      <div className="mt-4 px-5 lg:hidden">
        <TextLink href="/doctors" trailingIcon="arrow-right">
          {t("home.topRatedViewAll")}
        </TextLink>
      </div>
    </section>
  );
}
