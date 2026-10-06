import {
  HeroMasterSearch,
  type HeroSearchModules,
} from "@/components/home/hero-master-search";
import {
  HeroSpecialtyChips,
  type HeroSpecialty,
} from "@/components/home/hero-specialty-chips";
import { HomeHeroIllustration } from "@/components/home/home-hero-illustration";
import { Icon, type IconName } from "@/components/ui/icons";
import type { LocationCity } from "@/lib/api/locations";
import { t } from "@/i18n/t";

/** One „14 лекари“ line in the hero; counts come from the page’s totals. */
export type HomeHeroStat = { icon: IconName; label: string };

const ALL_MODULES: HeroSearchModules = {
  pharmacies: true,
  products: true,
  forum: true,
};

/**
 * The apricot hero band. A short value line (what the directory holds, from
 * the totals the page already fetched) and the illustration open it; then the
 * display heading, the master search (query + city in one pill, every
 * section at once) and the specialty chips with „Сите специјалности“.
 *
 * Mobile: the value line and a compact illustration share the first row, the
 * search sits right under the heading. Desktop (lg): text on the left, the
 * illustration on the right. Two soft shapes sit behind it all (decorative,
 * clipped to the band without clipping the city picker's popover).
 *
 * One illustration in the DOM: on mobile the text column is `display:
 * contents`, so its children join the band's two-column grid around it.
 */
export function HomeHero({
  specialties = [],
  allSpecialties = [],
  stats = [],
  modules = ALL_MODULES,
  knownCities = [],
}: {
  specialties?: HeroSpecialty[];
  allSpecialties?: HeroSpecialty[];
  stats?: HomeHeroStat[];
  modules?: HeroSearchModules;
  knownCities?: LocationCity[];
}) {
  return (
    <section
      aria-labelledby="home-hero-title"
      className="relative mx-3 mt-1 rounded-sheet bg-apricot px-4 pb-6 pt-5 sm:px-5 lg:mx-0 lg:mt-6 lg:px-14 lg:py-12"
    >
      <div
        aria-hidden="true"
        data-hero-shapes=""
        className="pointer-events-none absolute inset-0 overflow-hidden rounded-sheet"
      >
        <span className="absolute -bottom-24 -left-20 size-64 rounded-full bg-chip-tint/80 lg:-bottom-40 lg:-left-24 lg:size-[26rem]" />
        <span className="absolute -right-10 -top-16 size-48 rounded-full border-[18px] border-white/45 lg:-right-16 lg:-top-24 lg:size-80 lg:border-[28px]" />
      </div>

      <div className="relative grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 lg:grid-cols-12 lg:gap-x-8">
        <div className="contents lg:col-span-7 lg:block">
          {stats.length > 0 ? (
            <ul
              aria-label={t("home.heroStatsAria")}
              className="col-start-1 row-start-1 flex flex-col gap-2 self-center lg:flex-row lg:flex-wrap lg:gap-2"
            >
              {stats.map((stat) => (
                <li
                  key={stat.label}
                  className="flex items-center gap-2 font-ui text-[0.9375rem] font-semibold leading-5 text-ink lg:min-h-9 lg:rounded-pill lg:bg-white/70 lg:pl-1.5 lg:pr-3.5"
                >
                  <span className="flex size-8 flex-none items-center justify-center rounded-full bg-white lg:size-7 lg:bg-apricot">
                    <Icon name={stat.icon} size={18} />
                  </span>
                  {stat.label}
                </li>
              ))}
            </ul>
          ) : null}
          <h1
            id="home-hero-title"
            className="type-display col-span-2 mt-4 text-ink lg:mt-5"
          >
            {t("home.heroHeading")}
          </h1>
          <p className="mt-4 hidden max-w-[620px] font-reading text-xl leading-[1.875rem] text-ink lg:block">
            {t("home.heroLead")}
          </p>

          <HeroMasterSearch modules={modules} knownCities={knownCities} />

          <HeroSpecialtyChips top={specialties} all={allSpecialties} />
        </div>

        <div className="col-start-2 row-start-1 w-[clamp(9.5rem,44vw,13rem)] lg:col-span-5 lg:col-start-8 lg:w-full">
          <HomeHeroIllustration className="lg:mx-auto lg:max-w-[30rem]" />
        </div>
      </div>
    </section>
  );
}
