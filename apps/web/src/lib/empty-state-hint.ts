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

  // Inline rather than in mk.ts: a production build replaces NODE_ENV and
  // drops this branch, so the set-up text never reaches the client bundle.
  if (process.env.NODE_ENV === "development") {
    return "Ако листите се празни без филтри: уверете се дека API работи (на пр. http://127.0.0.1:8000/api/v1/health), дека NEXT_PUBLIC_API_URL во apps/web/.env.local одговара на тој сервер, и извршете од apps/api: php artisan migrate:fresh --seed. Ако APP_ENV не е local, додајте SEED_LOCAL_DEMO=true во apps/api/.env.";
  }

  return t(`common.emptyNoneYet.${directory}`);
}
