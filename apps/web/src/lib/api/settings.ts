import { apiGet } from "@/lib/api/client";

export type PublicSettings = {
  public_guidance: boolean;
  public_products: boolean;
  public_pharmacies: boolean;
  public_forum: boolean;
  registrations_enabled: boolean;
  maintenance_mode: boolean;
  require_email_verification: boolean;
};

const defaults: PublicSettings = {
  public_guidance: false,
  public_products: false,
  public_pharmacies: false,
  public_forum: true,
  registrations_enabled: true,
  maintenance_mode: false,
  require_email_verification: false,
};

export async function fetchPublicSettings(): Promise<PublicSettings> {
  try {
    return await apiGet<PublicSettings>("/settings/public");
  } catch {
    return defaults;
  }
}
