import { apiGet } from "@/lib/api/client";
import { apiGetServer } from "@/lib/api/server";

export type PublicSettings = {
  public_guidance: boolean;
  public_products: boolean;
  public_pharmacies: boolean;
  public_forum: boolean;
  registrations_enabled: boolean;
  maintenance_mode: boolean;
  maintenance_message: string | null;
  require_email_verification: boolean;
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
  require_email_verification: false,
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
    "При медицинска итност повикайте 194 или 112 веднаш.",
  footer_disclaimer_text:
    "Корисничките рецензии се модерираат пред објава. Цените во аптеките се референтни податоци од администратор, не понуди за купување на оваа страница. Насоки за симптоми се само информативни.",
  copyright_name: "Zdravje360",
  profile_avatar_min_messages: 10,
};

export async function fetchPublicSettings(): Promise<PublicSettings> {
  try {
    return await apiGet<PublicSettings>("/settings/public");
  } catch {
    return publicSettingsDefaults;
  }
}

export async function fetchPublicSettingsServer(): Promise<PublicSettings> {
  try {
    return await apiGetServer<PublicSettings>("/settings/public");
  } catch {
    return publicSettingsDefaults;
  }
}
