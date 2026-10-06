import type { MetadataRoute } from "next";
import { AI_CRAWLERS, SEARCH_CRAWLERS } from "@/lib/crawlers";
import { absoluteUrl } from "@/lib/site-url";

/**
 * Private and non-content paths. Ships together with sitemap.ts on purpose:
 * publishing a sitemap without these would invite crawlers into the account
 * area first. /search is a filtered view of the directory (also noindex) and
 * /forum/new a form — neither is content worth a crawl.
 */
export const DISALLOWED_PATHS = [
  "/api/",
  "/account",
  "/admin",
  "/login",
  "/register",
  "/forgot-password",
  "/reset-password",
  "/verify-email",
  "/search",
  "/forum/new",
  "/design-system",
] as const;

/**
 * Search engines and AI crawlers are named explicitly (docs/seo.md). A
 * crawler obeys only the most specific group that names it, so the named
 * group repeats the same disallow list rather than inheriting `*`'s.
 */
export default function robots(): MetadataRoute.Robots {
  const rules = { allow: "/", disallow: [...DISALLOWED_PATHS] };

  return {
    rules: [
      { userAgent: [...SEARCH_CRAWLERS, ...AI_CRAWLERS], ...rules },
      { userAgent: "*", ...rules },
    ],
    sitemap: absoluteUrl("/sitemap.xml"),
  };
}
