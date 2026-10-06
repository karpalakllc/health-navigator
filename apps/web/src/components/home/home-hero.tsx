import type { ReactNode } from "react";
import { HeroSearchPrompt } from "@/components/home/hero-search-prompt";
import { ChipLink } from "@/components/ui/chip";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

export type HomeQuickLink = { href: string; label: string };

/**
 * The apricot hero band. Mobile: eyebrow, display heading, the two-field
 * search card („Што барате?“ / „Каде?“) and quick chips. Desktop (lg): the
 * header pill is the search, so the left column has a lead line and a prompt
 * that focuses it; the right column (`aside`) holds the featured card.
 */
export function HomeHero({
  quickLinks,
  aside,
}: {
  quickLinks: HomeQuickLink[];
  aside?: ReactNode;
}) {
  return (
    <section
      aria-labelledby="home-hero-title"
      className="mx-3 mt-1 rounded-sheet bg-apricot px-5 pb-6 pt-7 lg:mx-0 lg:mt-6 lg:p-14"
    >
      <div className="lg:grid lg:grid-cols-12 lg:items-center lg:gap-x-6">
        <div className="lg:col-span-7">
          <p className="font-ui text-[0.9375rem] font-semibold leading-[1.375rem] text-ink lg:text-base lg:leading-6">
            {t("nav.wordmark")}
          </p>
          <h1
            id="home-hero-title"
            className="type-display mt-2 text-ink lg:mt-3"
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
              className="scroll-row -mx-5 mt-4 flex gap-2 overflow-x-auto px-5 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0"
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

        {aside ? (
          <div className="hidden lg:col-span-5 lg:block">{aside}</div>
        ) : null}
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
      className="mt-5 rounded-card bg-white p-2 shadow-card lg:hidden"
    >
      <div className="flex h-14 items-center gap-3 pl-3 pr-2">
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
          className="h-12 min-w-0 flex-1 rounded-sm border-0 bg-transparent p-0 text-[1.0625rem] text-ink placeholder:text-ink-2"
        />
      </div>
      <div aria-hidden="true" className="mx-3 h-px bg-line" />
      <div className="flex h-14 items-center gap-3 pl-3 pr-2">
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
          className="h-12 min-w-0 flex-1 rounded-sm border-0 bg-transparent p-0 text-[1.0625rem] text-ink placeholder:text-ink-2"
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
