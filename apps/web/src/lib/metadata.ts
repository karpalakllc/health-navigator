import type { Metadata } from "next";
import { mk } from "@/i18n/mk";
import { siteUrl } from "@/lib/site-url";

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
    },
    twitter: {
      card: "summary_large_image",
      title: fullTitle,
      description: summary,
    },
    // Account pages hold personal data and must never be indexed.
    robots: options.noIndex ? { index: false, follow: false } : undefined,
  };
}
