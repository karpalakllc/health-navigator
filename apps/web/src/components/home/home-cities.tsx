import Link from "next/link";
import { Icon } from "@/components/ui/icons";
import { SectionHeader } from "@/components/ui/section-header";
import type { HomeCity } from "@/lib/api/home";
import { t, tCount } from "@/i18n/t";

/**
 * „Пребарај по град“: cities by published doctors, as chips that open the
 * doctor directory filtered to that city. The count is doctors only, because
 * that is the list the chip leads to. Chips wrap, so nothing scrolls sideways.
 */
export function HomeCities({
  cities,
  className,
}: {
  cities: HomeCity[];
  className?: string;
}) {
  if (cities.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="home-cities-title" className={className}>
      <SectionHeader
        id="home-cities-title"
        title={t("homeSections.citiesTitle")}
        description={t("homeSections.citiesLead")}
      />
      <ul className="mt-4 flex flex-wrap gap-2 lg:mt-5 lg:gap-3">
        {cities.map((city) => (
          <li key={city.name} className="max-w-full">
            <Link
              href={`/doctors?city=${encodeURIComponent(city.name)}`}
              className="chip max-w-full min-h-12 gap-2 pl-3.5 pr-2"
            >
              <Icon name="map-pin" size={18} className="text-ink-2" />
              <span className="min-w-0 truncate">{city.name}</span>{" "}
              <span className="inline-flex min-w-8 items-center justify-center rounded-full bg-chip-tint px-2 py-1 text-sm font-semibold leading-4">
                {city.doctors_count}
                {/* A flex container drops this space visually; the
                    accessible name keeps it („Скопје 12 лекари“). */}{" "}
                <span className="sr-only">
                  {tCount("homeSections.cityDoctorsUnit", city.doctors_count)}
                </span>
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
