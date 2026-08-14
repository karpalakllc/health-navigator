import { cache } from "react";
import { apiGet, type ApiCacheOptions } from "@/lib/api/client";
import { apiGetServer } from "@/lib/api/server";

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
  footer_emergency_text:
    "При медицинска итност повикајте 194 или 112 веднаш.",
  footer_disclaimer_text:
    "Корисничките рецензии се модерираат пред објава. Цените во аптеките се референтни податоци од администратор, не понуди за купување на оваа страница. Насоки за симптоми се само информативни.",
  copyright_name: "Zdravje360",
  profile_avatar_min_messages: 10,
};

/*
 * Both fetchers are wrapped in React's cache() so the five-plus call sites in a
 * single render (generateMetadata, the layout, the maintenance gate, the header
 * and the footer, plus any page-level call) collapse to one fetch per request.
 *
 * They stay as two separate memo cells on purpose: the *Server variant carries
 * the session bearer token and the plain one does not, so sharing a cell would
 * mix an authenticated and an anonymous response.
 */
export const fetchPublicSettings = cache(
  async function fetchPublicSettings(options?: ApiCacheOptions): Promise<PublicSettings> {
    try {
      return await apiGet<PublicSettings>("/settings/public", options);
    } catch {
      return publicSettingsDefaults;
    }
  },
);

export const fetchPublicSettingsServer = cache(
  async function fetchPublicSettingsServer(): Promise<PublicSettings> {
    try {
      return await apiGetServer<PublicSettings>("/settings/public");
    } catch {
      return publicSettingsDefaults;
    }
  },
);
