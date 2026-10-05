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
    return { ...publicSettingsDefaults, ...outcome.data };
  }

  if (outcome.kind === "http-error" && outcome.status === 503) {
    return { ...publicSettingsUnavailable, maintenance_mode: true };
  }

  return publicSettingsUnavailable;
}
