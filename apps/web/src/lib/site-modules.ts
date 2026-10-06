import type { PublicSettings } from "@/lib/api/public-settings";

/**
 * The optional parts of the site an admin can switch off. A disabled module's
 * API routes answer 503, so the web must neither link to it nor advertise it.
 */
export type ModuleFlags = Pick<
  PublicSettings,
  "public_guidance" | "public_products" | "public_pharmacies" | "public_forum"
>;

const MODULE_FLAG_BY_PATH: Record<string, keyof ModuleFlags> = {
  "/guidance": "public_guidance",
  "/products": "public_products",
  "/pharmacies": "public_pharmacies",
  "/forum": "public_forum",
};

/** Just the module switches, small enough to hand to client components. */
export function moduleFlags(settings: ModuleFlags): ModuleFlags {
  return {
    public_guidance: settings.public_guidance,
    public_products: settings.public_products,
    public_pharmacies: settings.public_pharmacies,
    public_forum: settings.public_forum,
  };
}

/**
 * Whether a site path belongs to an enabled module (or to no module at all).
 * Matches the module root and everything below it, nothing that merely shares
 * the prefix.
 */
export function isPathEnabled(path: string, flags: ModuleFlags): boolean {
  for (const [root, flag] of Object.entries(MODULE_FLAG_BY_PATH)) {
    if (path === root || path.startsWith(`${root}/`)) {
      return flags[flag];
    }
  }

  return true;
}
