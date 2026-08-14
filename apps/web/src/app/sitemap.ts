import type { MetadataRoute } from "next";
import { fetchDoctors } from "@/lib/api/doctors";
import { fetchFacilities } from "@/lib/api/facilities";
import { fetchForumCategories, fetchForumTopicSearch } from "@/lib/api/forum";
import { fetchPublicSettings } from "@/lib/api/settings";
import { absoluteUrl } from "@/lib/site-url";

/**
 * Regenerated at most hourly. Without this the sitemap would call the API on
 * every crawler request, and a sitemap is by definition crawled often.
 */
export const revalidate = 3600;

/**
 * Every fetch below opts into the same window. Without this they inherit
 * `cache: "no-store"`, which silently opts the whole route out of caching and
 * makes the export above a no-op — meaning a full re-crawl of the API on every
 * crawler hit.
 */
const CACHE = { revalidate } as const;

/** The list endpoints cap per_page at 50; this bounds a runaway crawl. */
const PER_PAGE = 50;
const MAX_PAGES = 40;

async function collectSlugs(
  fetchPage: (page: number) => Promise<{
    data: Array<{ slug: string }>;
    meta: { last_page: number };
  }>,
): Promise<string[]> {
  const slugs: string[] = [];

  for (let page = 1; page <= MAX_PAGES; page += 1) {
    let result;
    try {
      result = await fetchPage(page);
    } catch {
      // A partial sitemap is better than a 500 for a crawler.
      break;
    }

    slugs.push(...result.data.map((item) => item.slug));

    if (page >= result.meta.last_page) {
      break;
    }
  }

  return slugs;
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const settings = await fetchPublicSettings(CACHE);

  const staticPaths = [
    "/",
    "/doctors",
    "/facilities",
    "/search",
    "/about",
    "/privacy",
    "/terms",
    "/disclaimer",
    ...(settings.public_forum ? ["/forum"] : []),
    ...(settings.public_guidance ? ["/guidance"] : []),
    // Modules that are off return 503, so their URLs must not be advertised.
    ...(settings.public_pharmacies ? ["/pharmacies"] : []),
    ...(settings.public_products ? ["/products"] : []),
  ];

  const entries: MetadataRoute.Sitemap = staticPaths.map((path) => ({
    url: absoluteUrl(path),
    changeFrequency: path === "/" ? "daily" : "weekly",
    priority: path === "/" ? 1 : 0.7,
  }));

  const [doctorSlugs, facilitySlugs] = await Promise.all([
    collectSlugs((page) => fetchDoctors({ page, per_page: PER_PAGE }, CACHE)),
    collectSlugs((page) => fetchFacilities({ page, per_page: PER_PAGE }, CACHE)),
  ]);

  entries.push(
    ...doctorSlugs.map((slug) => ({
      url: absoluteUrl(`/doctors/${slug}`),
      changeFrequency: "weekly" as const,
      priority: 0.8,
    })),
    ...facilitySlugs.map((slug) => ({
      url: absoluteUrl(`/facilities/${slug}`),
      changeFrequency: "weekly" as const,
      priority: 0.8,
    })),
  );

  if (settings.public_forum) {
    try {
      const categories = await fetchForumCategories(CACHE);

      entries.push(
        ...categories.map((category) => ({
          url: absoluteUrl(`/forum/${category.slug}`),
          changeFrequency: "daily" as const,
          priority: 0.6,
        })),
      );

      for (const category of categories) {
        // Paginated like doctors and facilities — a single unpaginated call
        // silently dropped every topic past the first page.
        for (let page = 1; page <= MAX_PAGES; page += 1) {
          const topics = await fetchForumTopicSearch(
            { category: category.slug, page, per_page: PER_PAGE },
            CACHE,
          );

          entries.push(
            ...topics.data.map((topic) => ({
              url: absoluteUrl(`/forum/${category.slug}/${topic.slug}`),
              lastModified: topic.last_post_at ?? topic.published_at ?? undefined,
              changeFrequency: "weekly" as const,
              priority: 0.5,
            })),
          );

          if (page >= topics.meta.last_page) {
            break;
          }
        }
      }
    } catch {
      // Forum entries are optional; never fail the whole sitemap for them.
    }
  }

  return entries;
}
