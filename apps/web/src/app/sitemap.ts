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
  const settings = await fetchPublicSettings();

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
    collectSlugs((page) => fetchDoctors({ page, per_page: PER_PAGE })),
    collectSlugs((page) => fetchFacilities({ page, per_page: PER_PAGE })),
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
      const categories = await fetchForumCategories();

      entries.push(
        ...categories.map((category) => ({
          url: absoluteUrl(`/forum/${category.slug}`),
          changeFrequency: "daily" as const,
          priority: 0.6,
        })),
      );

      for (const category of categories) {
        const topics = await fetchForumTopicSearch({ category: category.slug });

        entries.push(
          ...topics.data.map((topic) => ({
            url: absoluteUrl(`/forum/${category.slug}/${topic.slug}`),
            lastModified: topic.last_post_at ?? topic.published_at ?? undefined,
            changeFrequency: "weekly" as const,
            priority: 0.5,
          })),
        );
      }
    } catch {
      // Forum entries are optional; never fail the whole sitemap for them.
    }
  }

  return entries;
}
