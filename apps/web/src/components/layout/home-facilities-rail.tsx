import { SectionHeading } from "@/components/design/section-heading";
import { HomeFacilityCard } from "@/components/directory/home-facility-card";
import type { FacilityListItem } from "@/lib/api/types";
import { t } from "@/i18n/t";

export function HomeFacilitiesRail({ facilities }: { facilities: FacilityListItem[] }) {
  if (facilities.length === 0) {
    return null;
  }

  return (
    <section id="institutions">
      <SectionHeading
        title={t("home.featuredFacilitiesTitle")}
        description={t("home.featuredFacilitiesDescription")}
        href="/facilities"
        linkLabel={t("home.featuredFacilitiesViewAll")}
      />
      <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {facilities.map((facility, index) => (
          <li key={facility.slug}>
            <HomeFacilityCard facility={facility} variant={index === 1 ? "teal" : "default"} />
          </li>
        ))}
      </ul>
    </section>
  );
}
