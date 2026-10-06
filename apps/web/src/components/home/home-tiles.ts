import type { HomeTile } from "@/components/home/home-directory-tiles";
import type { HomeHeroStat } from "@/components/home/home-hero";
import { t, tCount, type MessageKey } from "@/i18n/t";

/**
 * The home's apricot „feature“ tiles, by destination. Chosen by the owner
 * (Лекари and Форум); a tile is highlighted because of where it goes, never
 * because of its position in the grid, so switching a module off or
 * reordering tiles cannot move the highlight onto another tile.
 */
export const FEATURED_TILE_HREFS: ReadonlySet<string> = new Set([
  "/doctors",
  "/forum",
]);

/** Each list endpoint's total; undefined when the module is off or failed. */
export type HomeTotals = {
  doctors?: number;
  facilities?: number;
  pharmacies?: number;
  products?: number;
  forumTopics?: number;
};

export type HomeTileModules = {
  pharmacies: boolean;
  products: boolean;
  guidance: boolean;
  forum: boolean;
};

function countLine(key: MessageKey, total: number | undefined) {
  return total === undefined ? undefined : tCount(key, total);
}

/** „Што ви треба?“: one tile per enabled section, in the nav's order. */
export function buildHomeTiles(
  modules: HomeTileModules,
  totals: HomeTotals,
): HomeTile[] {
  const tiles: Omit<HomeTile, "feature">[] = [
    {
      href: "/doctors",
      label: t("nav.doctors"),
      icon: "stethoscope",
      sub: countLine("home.tileDoctorsCount", totals.doctors),
    },
    {
      href: "/facilities",
      label: t("nav.facilities"),
      icon: "building",
      sub: countLine("home.tileFacilitiesCount", totals.facilities),
    },
  ];
  if (modules.pharmacies) {
    tiles.push({
      href: "/pharmacies",
      label: t("nav.pharmacies"),
      icon: "pill",
      sub: countLine("home.tilePharmaciesCount", totals.pharmacies),
    });
  }
  if (modules.products) {
    tiles.push({
      href: "/products",
      label: t("nav.products"),
      icon: "package",
      sub: countLine("home.tileProductsCount", totals.products),
    });
  }
  if (modules.guidance) {
    tiles.push({
      href: "/guidance",
      label: t("nav.guidance"),
      icon: "compass",
      sub: t("home.tileGuidanceSub"),
    });
  }
  if (modules.forum) {
    tiles.push({
      href: "/forum",
      label: t("nav.forum"),
      icon: "message-circle",
      sub: countLine("home.tileForumCount", totals.forumTopics),
    });
  }

  return tiles.map((tile) => ({
    ...tile,
    feature: FEATURED_TILE_HREFS.has(tile.href),
  }));
}

/** The hero's value line („14 лекари · 6 установи · 3 аптеки“); known totals only. */
export function buildHeroStats(totals: HomeTotals): HomeHeroStat[] {
  const stats: HomeHeroStat[] = [];
  for (const [icon, key, total] of [
    ["stethoscope", "home.heroStatDoctors", totals.doctors],
    ["building", "home.tileFacilitiesCount", totals.facilities],
    ["pill", "home.tilePharmaciesCount", totals.pharmacies],
  ] as const) {
    if (total) {
      stats.push({ icon, label: tCount(key, total) });
    }
  }
  return stats;
}
