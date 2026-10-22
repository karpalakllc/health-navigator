import { t } from "@/i18n/t";

type Directory = "doctors" | "pharmacies" | "facilities" | "products";

/**
 * Empty-list text for the public directories. Visitors get one plain line;
 * the developer set-up hint (API, env, seeding) only shows in `next dev`.
 */
export function emptyStateHint(hasFilters: boolean, directory: Directory) {
  if (hasFilters) {
    return t("common.emptyTryFilters");
  }

  if (process.env.NODE_ENV === "development") {
    return t("common.demoDataHint");
  }

  return t(`common.emptyNoneYet.${directory}`);
}
