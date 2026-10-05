/*
 * Pure half of lib/api/settings.ts: the settings shape, its defaults and the
 * decision of what the site shows when /settings/public cannot be read. Kept
 * free of React, Next and Sentry imports so the decision is unit-testable.
 */

export type PublicSettings = {
  public_guidance: boolean;
  public_products: boolean;
  public_pharmacies: boolean;
  public_forum: boolean;
  registrations_enabled: boolean;
  maintenance_mode: boolean;
  maintenance_message: string | null;
  logo_url: string | null;
  favicon_url: string | null;
  placeholder_doctor_url: string | null;
  placeholder_facility_url: string | null;
  placeholder_pharmacy_url: string | null;
  footer_emergency_text: string;
  footer_disclaimer_text: string;
  copyright_name: string;
  profile_avatar_min_messages: number;
  site_font_family: "geist" | "inter" | "system";
  forum_rules_enabled: boolean;
  forum_rules_title: string | null;
  forum_rules_body: string | null;
  /**
   * Web-side only, never sent by the API: true when /settings/public could not
   * be read and these values are the fallback. A module that reads as off is
   * then *unknown*, not switched off — see isModuleOn.
   */
  degraded: boolean;
};

export const publicSettingsDefaults: PublicSettings = {
  public_guidance: false,
  public_products: false,
  public_pharmacies: false,
  public_forum: true,
  registrations_enabled: true,
  maintenance_mode: false,
  maintenance_message: null,
  logo_url: null,
  favicon_url: null,
  placeholder_doctor_url: null,
  placeholder_facility_url: null,
  placeholder_pharmacy_url: null,
  site_font_family: "geist",
  forum_rules_enabled: true,
  forum_rules_title: null,
  forum_rules_body: null,
  footer_emergency_text: "При медицинска итност повикајте 194 или 112 веднаш.",
  footer_disclaimer_text:
    "Корисничките рецензии се модерираат пред објава. Цените во аптеките се референтни податоци од администратор, не понуди за купување на оваа страница. Насоки за симптоми се само информативни.",
  copyright_name: "Zdravje360",
  profile_avatar_min_messages: 10,
  degraded: false,
};

/**
 * What the site assumes when the settings could not be read.
 *
 * Every optional module is treated as off: a module the admin disabled answers
 * 503 on the API, so advertising one on a guess (nav, footer, sitemap) would
 * send visitors and crawlers to pages that cannot load. The core directory —
 * doctors and facilities — is not a module and stays up.
 */
export const publicSettingsUnavailable: PublicSettings = {
  ...publicSettingsDefaults,
  degraded: true,
  public_guidance: false,
  public_products: false,
  public_pharmacies: false,
  public_forum: false,
};

export type PublicSettingsOutcome =
  | { kind: "ok"; data: Partial<PublicSettings> }
  | { kind: "http-error"; status: number }
  | { kind: "network-error" };

/**
 * Turns the result of reading /settings/public into the settings the site runs on.
 *
 * - 200: the API's values, laid over the defaults so a field added on one side
 *   first cannot leave `undefined` holes.
 * - 503: the API as a whole is in maintenance (`php artisan down`, or a proxy in
 *   front of it saying so). The admin maintenance toggle never produces this —
 *   /settings/public is exempt from it and answers 200 with
 *   `maintenance_mode: true` — so a 503 here means no other request will load
 *   either. Show the maintenance page instead of a site of broken pages: this is
 *   the one failure that must not fail open.
 * - anything else (other 5xx, 429, network, bad JSON): keep the site up without
 *   the optional modules. The caller reports it.
 */
export function resolvePublicSettings(
  outcome: PublicSettingsOutcome,
): PublicSettings {
  if (outcome.kind === "ok") {
    // `degraded` is ours; a stray field of that name from the API cannot set it.
    return { ...publicSettingsDefaults, ...outcome.data, degraded: false };
  }

  if (outcome.kind === "http-error" && outcome.status === 503) {
    return { ...publicSettingsUnavailable, maintenance_mode: true };
  }

  return publicSettingsUnavailable;
}

export type ModuleFlag =
  "public_guidance" | "public_products" | "public_pharmacies" | "public_forum";

/**
 * Thrown by a page when it cannot tell whether its module is on, so the error
 * boundary renders a "try again" page instead of a 404 or a "switched off"
 * page that a crawler would believe.
 */
export class SettingsUnavailableError extends Error {
  constructor() {
    super("Public settings unavailable; module state unknown");
    this.name = "SettingsUnavailableError";
  }
}

/**
 * Whether a module's pages should render. False only when the admin really
 * switched it off (the page then shows its 404 or "not available" state).
 *
 * When the settings could not be read the flag says nothing about the module,
 * and answering "off" would turn every forum, pharmacy and product URL into a
 * real 404 for the length of an API blip — long enough for crawlers to drop
 * them. So this throws SettingsUnavailableError instead. Navigation, footer and
 * sitemap keep reading the flag directly: hiding a link is harmless.
 */
export function isModuleOn(
  settings: Pick<PublicSettings, ModuleFlag | "degraded">,
  flag: ModuleFlag,
): boolean {
  if (settings[flag]) {
    return true;
  }

  if (settings.degraded) {
    throw new SettingsUnavailableError();
  }

  return false;
}

/** process.env.NEXT_PHASE while `next build` prerenders (next/constants). */
const PHASE_PRODUCTION_BUILD = "phase-production-build";

/**
 * Whether the sitemap should give up rather than be generated from fallback
 * settings.
 *
 * The sitemap is cached for an hour; built from fallback settings it would
 * drop every forum URL for that hour. Throwing instead makes Next keep serving
 * the last good sitemap (an error during ISR revalidation leaves the stale
 * entry in place and retries on the next request).
 *
 * Except during `next build`: there is no previous sitemap to keep, and a build
 * must not fail because the API was unreachable from the build machine. That
 * first sitemap is replaced on the first revalidation that can read settings.
 */
export function shouldAbortSitemap(
  settings: Pick<PublicSettings, "degraded">,
  phase: string | undefined,
): boolean {
  return settings.degraded && phase !== PHASE_PRODUCTION_BUILD;
}

/** How often one server instance reports a settings outage to Sentry. */
export const SETTINGS_OUTAGE_REPORT_INTERVAL_MS = 60_000;

/**
 * A per-instance gate: true at most once per `intervalMs`. Settings are read on
 * every page view, so an outage would otherwise send one Sentry event per
 * visitor — burning the quota and burying everything else.
 */
export function createReportThrottle(
  intervalMs: number = SETTINGS_OUTAGE_REPORT_INTERVAL_MS,
): (now?: number) => boolean {
  let lastReportedAt: number | null = null;

  return (now = Date.now()) => {
    if (lastReportedAt !== null && now - lastReportedAt < intervalMs) {
      return false;
    }

    lastReportedAt = now;
    return true;
  };
}
