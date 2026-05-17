import { apiGet } from "@/lib/api/client";
import { apiGetServer } from "@/lib/api/server";

export type PublicSettings = {
  public_guidance: boolean;
  public_products: boolean;
  public_pharmacies: boolean;
  public_forum: boolean;
  registrations_enabled: boolean;
  maintenance_mode: boolean;
  require_email_verification: boolean;
  logo_url: string | null;
  favicon_url: string | null;
  footer_emergency_text: string;
  footer_disclaimer_text: string;
  copyright_name: string;
  profile_avatar_min_messages: number;
};

export const publicSettingsDefaults: PublicSettings = {
  public_guidance: false,
  public_products: false,
  public_pharmacies: false,
  public_forum: true,
  registrations_enabled: true,
  maintenance_mode: false,
  require_email_verification: false,
  logo_url: null,
  favicon_url: null,
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
