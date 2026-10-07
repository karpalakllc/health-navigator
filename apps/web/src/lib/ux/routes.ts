/**
 * Which pages the UX tracker may report, as route templates.
 *
 * Statistics are kept per template (`/doctors/[slug]`), never per address, so
 * no slug, query string or fragment ever leaves the browser. Anything not
 * listed is not tracked at all: the account area, sign-in and registration,
 * password reset, e-mail verification, the doctor claim, correction and
 * objection forms, the forum composer, the design system and unknown pages.
 *
 * Keep UX_ROUTES in step with apps/api/config/ux.php (UxRoutesParityTest).
 */
export const UX_ROUTES = [
  // ux-routes:start
  "/",
  "/about",
  "/disclaimer",
  "/privacy",
  "/terms",
  "/transparency",
  "/search",
  "/guidance",
  "/doctors",
  "/doctors/[slug]",
  "/facilities",
  "/facilities/[slug]",
  "/pharmacies",
  "/pharmacies/[slug]",
  "/products",
  "/products/[slug]",
  "/forum",
  "/forum/[categorySlug]",
  "/forum/[categorySlug]/[topicSlug]",
  "/forum/tags/[tag]",
  // ux-routes:end
] as const;

export type UxRoute = (typeof UX_ROUTES)[number];

const STATIC = new Set<string>(UX_ROUTES.filter((r) => !r.includes("[")));

/** One path segment of a slug-like value; anything else is not a page of ours. */
const SEGMENT = /^[^/]{1,200}$/;

export function isUxRoute(value: unknown): value is UxRoute {
  return (
    typeof value === "string" &&
    (UX_ROUTES as readonly string[]).includes(value)
  );
}

/**
 * The route template for a pathname, or null when the page must not be
 * tracked. Only the pathname is read: callers never pass a query or fragment.
 */
export function uxRouteTemplate(pathname: string): UxRoute | null {
  const path =
    pathname.length > 1 && pathname.endsWith("/")
      ? pathname.slice(0, -1)
      : pathname;

  if (STATIC.has(path)) {
    return path as UxRoute;
  }

  const parts = path.split("/").slice(1);

  if (!parts.every((part) => SEGMENT.test(part))) {
    return null;
  }

  const [section, first, second, third] = parts;

  switch (section) {
    case "doctors":
    case "facilities":
    case "pharmacies":
    case "products":
      // Only the profile itself; /doctors/x/claim, /correction and
      // /objection are forms and are not tracked.
      return parts.length === 2 ? (`/${section}/[slug]` as UxRoute) : null;
    case "forum":
      if (first === "new") return null;
      if (first === "tags") {
        return parts.length === 3 ? "/forum/tags/[tag]" : null;
      }
      if (parts.length === 2) return "/forum/[categorySlug]";
      if (parts.length === 3 && second && !third) {
        return "/forum/[categorySlug]/[topicSlug]";
      }
      return null;
    default:
      return null;
  }
}
