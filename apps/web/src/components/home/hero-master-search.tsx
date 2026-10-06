import { CityPicker } from "@/components/home/city-picker";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import type { LocationCity } from "@/lib/api/locations";
import { t, type MessageKey } from "@/i18n/t";

/** Id of the hero's query field (one per page). */
export const HERO_SEARCH_INPUT_ID = "home-hero-q";

export type HeroSearchModules = {
  pharmacies: boolean;
  products: boolean;
  forum: boolean;
};

/** „Пребарува лекари, специјалности, установи, аптеки и теми од форумот.“ */
export function heroSearchScope(modules: HeroSearchModules): string {
  const keys: MessageKey[] = [
    "homeSearch.scopeDoctors",
    "homeSearch.scopeSpecialties",
    "homeSearch.scopeFacilities",
  ];
  if (modules.pharmacies) keys.push("homeSearch.scopePharmacies");
  if (modules.products) keys.push("homeSearch.scopeProducts");
  if (modules.forum) keys.push("homeSearch.scopeForum");
  const parts = keys.map((key) => t(key));
  const last = parts.pop();
  return `${t("homeSearch.scopeLead")} ${parts.join(", ")} ${t("homeSearch.scopeAnd")} ${last}.`;
}

/**
 * The master search: one white pill, „🔍 Лекар, установа, тема… | 📍 Град ▾“,
 * and „Барај“. A GET form to /search, which searches every section at once
 * (doctors, specialties, facilities, pharmacies, products, forum topics), so
 * the query and city stay in the URL and it works without JavaScript (the
 * city then covers the whole country). The city is a suffix inside the
 * same pill on every width; „Барај“ sits inside the pill from lg and under
 * it, full width, on phones, where the pill needs its room for the query.
 */
export function HeroMasterSearch({
  modules,
  knownCities,
}: {
  modules: HeroSearchModules;
  knownCities?: LocationCity[];
}) {
  return (
    <form
      role="search"
      aria-label={t("nav.searchLandmark")}
      action="/search"
      method="get"
      className="col-span-2 mt-5 lg:mt-8"
    >
      <div className="flex h-[3.75rem] items-center gap-1 rounded-pill bg-white p-1.5 pl-4 shadow-card lg:h-[4.25rem] lg:pl-5">
        <Icon name="search" className="shrink-0 text-ink-2" />
        <label htmlFor={HERO_SEARCH_INPUT_ID} className="sr-only">
          {t("nav.searchWhat")}
        </label>
        <input
          id={HERO_SEARCH_INPUT_ID}
          name="q"
          type="search"
          autoComplete="off"
          enterKeyHint="search"
          placeholder={t("homeSearch.placeholder")}
          className="h-12 min-w-0 flex-1 rounded-md border-0 bg-transparent px-1.5 type-body text-ink placeholder:text-ink-2"
        />
        <span aria-hidden="true" className="h-7 w-px shrink-0 bg-line" />
        <CityPicker
          knownCities={knownCities}
          className="max-w-[44%] shrink-0 lg:max-w-[15rem]"
        />
        <Button
          type="submit"
          size="lg"
          leadingIcon="search"
          className="ml-1 hidden lg:inline-flex lg:h-14"
        >
          {t("homeSearch.submit")}
        </Button>
      </div>
      <Button
        type="submit"
        size="lg"
        fullWidth
        leadingIcon="search"
        className="mt-3 lg:hidden"
      >
        {t("homeSearch.submit")}
      </Button>
      <p className="type-meta mt-3 text-ink lg:mt-4">
        {heroSearchScope(modules)}
      </p>
    </form>
  );
}
