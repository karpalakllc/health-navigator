"use client";

import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

/** Id of the desktop header's „Што барате?“ input (Ctrl+K focuses it). */
export const HEADER_SEARCH_INPUT_ID = "site-header-q";

/**
 * Desktop header search pill (white, 56px, card shadow): „Што барате?“ |
 * „Каде?“ | ink round submit. A plain GET form to /search, so it works
 * without JavaScript and the query stays in the URL.
 */
export function HeaderSearch() {
  return (
    <form
      role="search"
      action="/search"
      method="get"
      className="flex h-14 w-full items-center gap-3 rounded-full bg-white pl-5 pr-1.5 shadow-card"
    >
      <Icon name="search" size={20} className="text-ink-2" />
      <label htmlFor={HEADER_SEARCH_INPUT_ID} className="sr-only">
        {t("nav.searchWhat")}
      </label>
      <input
        id={HEADER_SEARCH_INPUT_ID}
        name="q"
        type="search"
        autoComplete="off"
        placeholder={t("nav.searchWhatPlaceholder")}
        className="h-11 min-w-0 flex-1 rounded-md border-0 bg-transparent p-0 text-[1.0625rem] text-ink"
      />
      <span aria-hidden="true" className="h-7 w-px shrink-0 bg-line" />
      <Icon name="map-pin" size={20} className="text-ink-2" />
      <label htmlFor="site-header-city" className="sr-only">
        {t("nav.searchWhere")}
      </label>
      <input
        id="site-header-city"
        name="city"
        type="text"
        autoComplete="address-level2"
        placeholder={t("nav.searchWherePlaceholder")}
        className="h-11 w-24 rounded-md border-0 bg-transparent p-0 text-[1.0625rem] text-ink"
      />
      <button
        type="submit"
        aria-label={t("nav.searchSubmit")}
        className="btn btn-primary btn-icon size-11"
      >
        <Icon name="search" size={20} />
      </button>
    </form>
  );
}
