import { SectionHeading } from "@/components/design/section-heading";
import { HomeFeaturedDoctorCard } from "@/components/directory/home-featured-doctor-card";
import type { DoctorListItem } from "@/lib/api/types";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

/** Matches one column in the 3-up grid within the page column. */
const RAIL_CARD_WIDTH =
  "w-[calc((100cqw-36px)/3)] min-w-[17.5rem] max-w-[22rem] shrink-0 snap-start";

export function HomeFeaturedDoctorsSection({
  doctors,
}: {
  doctors: DoctorListItem[];
}) {
  if (doctors.length === 0) {
    return null;
  }

  const scrollable = doctors.length > 3;

  return (
    <section id="doctors" className="@container min-w-0">
      <SectionHeading
        eyebrow={t("home.featuredDoctors")}
        eyebrowVariant="plain-with-icon"
        title={t("home.topRatedTitle")}
        description={t("home.featuredDoctorsDesc")}
        href="/doctors?sort=rating"
        linkLabel={t("home.featuredDoctorsViewAll")}
        icon={
          <svg
            className="h-4 w-4 text-primary"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden
          >
            <path d="M12 2l2.4 7.4h7.6l-6 4.6 2.3 7.4L12 17l-6.3 4.4 2.3-7.4-6-4.6h7.6z" />
          </svg>
        }
      />

      {scrollable ? (
        <div className="-mx-6 min-w-0 overflow-hidden">
          <ul
            className="rail-scroll-x flex snap-x snap-proximity gap-[18px] px-6 py-3"
            aria-label={t("home.featuredDoctors")}
          >
            {doctors.map((doctor) => (
              <li key={doctor.slug} className={cn(RAIL_CARD_WIDTH, "py-1")}>
                <HomeFeaturedDoctorCard doctor={doctor} inRail />
              </li>
            ))}
          </ul>
        </div>
      ) : (
        <ul className="grid gap-[18px] md:grid-cols-3">
          {doctors.map((doctor) => (
            <li key={doctor.slug}>
              <HomeFeaturedDoctorCard doctor={doctor} />
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
