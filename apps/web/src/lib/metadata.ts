import type { Metadata } from "next";
import { mk } from "@/i18n/mk";
import { parseListPage } from "@/lib/api/directory-cache-policy";
import { siteUrl } from "@/lib/site-url";

/**
 * The site-wide share image (public/og-default.png, 1200×630, D2a palette).
 *
 * Set on every page here rather than through an app/opengraph-image file: a
 * page's own `openGraph` object replaces its parent's wholesale, so a root
 * file-based image would be dropped by every page that calls pageMetadata.
 * Twitter's card falls back to the OpenGraph image.
 */
export const DEFAULT_OG_IMAGE = {
  url: "/og-default.png",
  width: 1200,
  height: 630,
  alt: `${mk.nav.wordmark}: ${mk.meta.description}`,
} as const;

/**
 * Shared metadata for a public page.
 *
 * Previously this emitted only a title and description, so every link shared to
 * Facebook, Viber or X rendered as a blank card, and no page declared a
 * canonical URL. `metadataBase` is what lets Next resolve the relative canonical
 * and OpenGraph paths below into absolute ones.
 */
export function pageMetadata(
  title: string,
  description?: string,
  options: { path?: string; noIndex?: boolean } = {},
): Metadata {
  const fullTitle = `${title} · ${mk.meta.title}`;
  const summary = description ?? mk.meta.description;

  return {
    metadataBase: new URL(siteUrl()),
    title: fullTitle,
    description: summary,
    alternates: options.path ? { canonical: options.path } : undefined,
    openGraph: {
      title: fullTitle,
      description: summary,
      siteName: mk.meta.title,
      locale: "mk_MK",
      type: "website",
      url: options.path,
      images: [DEFAULT_OG_IMAGE],
    },
    twitter: {
      card: "summary_large_image",
      title: fullTitle,
      description: summary,
    },
    // Account pages hold personal data and must never be indexed, nor may a
    // stand-in for a switched-off module. The key is omitted otherwise: Next
    // merges metadata key by key, so `robots: undefined` here would wipe the
    // root layout's maintenance-mode noindex.
    ...(options.noIndex ? { robots: { index: false, follow: false } } : {}),
  };
}

export type ListSearchParams = Record<string, string | string[] | undefined>;

/**
 * Canonical path for a paginated, filterable list page.
 *
 * Any filter (text, slug, sort) points at the bare list: filter combinations
 * are views of it, not documents to index one by one. An unfiltered deeper
 * page keeps its `?page=N`, since page 2 is not a duplicate of page 1.
 */
export function listCanonicalPath(
  path: string,
  params: ListSearchParams = {},
): string {
  const filtered = Object.entries(params).some(
    ([key, value]) =>
      key !== "page" &&
      (Array.isArray(value) ? value.length > 0 : (value ?? "").trim() !== ""),
  );

  if (filtered) {
    return path;
  }

  const page = parseListPage(
    typeof params.page === "string" ? params.page : undefined,
  );

  return page > 1 ? `${path}?page=${page}` : path;
}
