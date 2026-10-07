import { FIRST_AID_COPY } from "@/content/first-aid/copy";
import { FIRST_AID_BASE } from "@/content/first-aid";
import type { FirstAidGuide } from "@/content/first-aid/types";
import { breadcrumbJsonLd } from "@/lib/structured-data";
import { absoluteUrl } from "@/lib/site-url";

/*
 * Structured data for a published guide. Not HowTo: Google retired HowTo rich
 * results (2023) and no longer lists the type among supported features. A
 * schema.org MedicalWebPage states what the page is (with the sources as
 * `citation` and, once signed off, `lastReviewed`), plus a BreadcrumbList,
 * which Google does still show. Drafts get none of this: they are noindex.
 */
export function firstAidGuideJsonLd(
  guide: FirstAidGuide,
): Record<string, unknown> {
  const url = absoluteUrl(`${FIRST_AID_BASE}/${guide.slug}`);

  return {
    "@context": "https://schema.org",
    "@type": "MedicalWebPage",
    name: guide.title,
    description: guide.summary,
    url,
    inLanguage: "mk",
    dateModified: guide.updated,
    ...(guide.review.reviewedOn
      ? { lastReviewed: guide.review.reviewedOn }
      : {}),
    ...(guide.review.reviewer
      ? { reviewedBy: { "@type": "Person", name: guide.review.reviewer } }
      : {}),
    citation: guide.sources.map((source) => source.url),
    isPartOf: {
      "@type": "CollectionPage",
      name: FIRST_AID_COPY.title,
      url: absoluteUrl(FIRST_AID_BASE),
    },
  };
}

export function firstAidBreadcrumbJsonLd(
  guide: FirstAidGuide,
): Record<string, unknown> | null {
  return breadcrumbJsonLd([
    { name: FIRST_AID_COPY.title, url: absoluteUrl(FIRST_AID_BASE) },
    { name: guide.title },
  ]);
}
