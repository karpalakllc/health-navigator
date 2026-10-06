import { HeroSearchPrompt } from "@/components/home/hero-search-prompt";
import { ChipLink } from "@/components/ui/chip";
import { Button } from "@/components/ui/button";
import { HomeHeroIllustration } from "@/components/home/home-hero-illustration";
import { Icon, type IconName } from "@/components/ui/icons";
import { t } from "@/i18n/t";

export type HomeQuickLink = { href: string; label: string };

/** One „14 лекари“ line in the hero; counts come from the page’s totals. */
export type HomeHeroStat = { icon: IconName; label: string };

/**
 * The apricot hero band. A short value line (what the directory holds, from
 * the totals the page already fetched) and the illustration open it; then the
 * display heading, the search and quick chips.
 *
 * Mobile: the value line and a compact illustration share the first row, the
 * two-field search card („Што барате?“ / „Каде?“) sits right under the
 * heading. Desktop (lg): text on the left (the header pill is the search, so
 * a prompt focuses it), the illustration on the right.
 *
 * One illustration in the DOM: on mobile the text column is `display:
 * contents`, so its children join the band's two-column grid around it.
 */
export function HomeHero({
  quickLinks,
  stats = [],
}: {
  quickLinks: HomeQuickLink[];
  stats?: HomeHeroStat[];
}) {
  return (
    <section
      aria-labelledby="home-hero-title"
      className="mx-3 mt-1 rounded-sheet bg-apricot px-4 pb-6 pt-5 sm:px-5 lg:mx-0 lg:mt-6 lg:px-14 lg:py-12"
    >
      <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 lg:grid-cols-12 lg:gap-x-8">
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

          <HeroSearchForm />

          <div className="mt-8 hidden lg:block">
            <HeroSearchPrompt />
          </div>

          {quickLinks.length > 0 ? (
            <ul
              aria-label={t("home.quickLinksAria")}
              className="scroll-row col-span-2 -mx-4 -mb-2.5 mt-1.5 flex gap-2 overflow-x-auto px-4 py-2.5 sm:-mx-5 sm:px-5 lg:mx-0 lg:mb-0 lg:mt-4 lg:flex-wrap lg:overflow-visible lg:px-0 lg:py-0"
            >
              {quickLinks.map((link) => (
                <li key={link.href} className="flex-none">
                  <ChipLink
                    href={link.href}
                    icon="stethoscope"
                    className="shadow-none"
                  >
                    {link.label}
                  </ChipLink>
                </li>
              ))}
            </ul>
          ) : null}
        </div>

        <div className="col-start-2 row-start-1 w-[clamp(9.5rem,44vw,13rem)] lg:col-span-5 lg:col-start-8 lg:w-full">
          <HomeHeroIllustration className="lg:mx-auto lg:max-w-[30rem]" />
        </div>
      </div>
    </section>
  );
}

/** Mobile/tablet search card: a GET form to /search, works without JS. */
function HeroSearchForm() {
  return (
    <form
      role="search"
      aria-label={t("nav.searchLandmark")}
      action="/search"
      method="get"
      className="col-span-2 mt-5 rounded-card bg-white p-2 shadow-card lg:hidden"
    >
      <div className="flex h-14 items-center gap-3 pl-2.5 pr-1">
        <Icon name="search" className="shrink-0 text-ink-2" />
        <label htmlFor="home-hero-q" className="sr-only">
          {t("nav.searchWhat")}
        </label>
        <input
          id="home-hero-q"
          name="q"
          type="search"
          autoComplete="off"
          placeholder={t("nav.searchWhatPlaceholder")}
          className="h-12 min-w-0 flex-1 rounded-sm border-0 bg-transparent p-0 type-body text-ink placeholder:text-ink-2"
        />
      </div>
      <div aria-hidden="true" className="mx-3 h-px bg-line" />
      <div className="flex h-14 items-center gap-3 pl-2.5 pr-1">
        <Icon name="map-pin" className="shrink-0 text-ink-2" />
        <label htmlFor="home-hero-city" className="sr-only">
          {t("nav.searchWhere")}
        </label>
        <input
          id="home-hero-city"
          name="city"
          type="text"
          autoComplete="address-level2"
          placeholder={t("nav.searchWherePlaceholder")}
          className="h-12 min-w-0 flex-1 rounded-sm border-0 bg-transparent p-0 type-body text-ink placeholder:text-ink-2"
        />
      </div>
      <Button
        type="submit"
        size="lg"
        fullWidth
        leadingIcon="search"
        className="mt-2"
      >
        {t("nav.searchSubmit")}
      </Button>
    </form>
  );
}
