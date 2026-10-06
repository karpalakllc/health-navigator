import type { MetadataRoute } from "next";
import { fetchDoctors } from "@/lib/api/doctors";
import { fetchFacilities } from "@/lib/api/facilities";
import { fetchForumCategories, fetchForumTopicSearch } from "@/lib/api/forum";
import {
  SettingsUnavailableError,
  shouldAbortSitemap,
} from "@/lib/api/public-settings";
import { loadPublicSettings } from "@/lib/api/settings";
import { collectPages } from "@/lib/collect-pages";
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
 * crawler hit. That includes the settings read: the shared fetchPublicSettings()
 * uses a much shorter window, which would drag this route's down to it.
 */
const CACHE = { revalidate } as const;

/** The list endpoints cap per_page at 50; this bounds a runaway crawl. */
const PER_PAGE = 50;
const MAX_PAGES = 40;

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  // If the settings cannot be read this resolves with every optional module
  // off (see resolvePublicSettings). Caching that for an hour would drop every
  // forum URL, so throw instead and let Next keep the previous sitemap.
  const settings = await loadPublicSettings(revalidate);

  if (shouldAbortSitemap(settings, process.env.NEXT_PHASE)) {
    throw new SettingsUnavailableError();
  }

  const staticPaths = [
    "/",
    "/doctors",
    "/facilities",
    "/search",
    "/about",
    "/privacy",
    "/terms",
    "/disclaimer",
    // Optional modules answer 503 while switched off, so their URLs are only
    // advertised while they are on.
    ...(settings.public_forum ? ["/forum"] : []),
    ...(settings.public_guidance ? ["/guidance"] : []),
    ...(settings.public_pharmacies ? ["/pharmacies"] : []),
    ...(settings.public_products ? ["/products"] : []),
  ];

  const entries: MetadataRoute.Sitemap = staticPaths.map((path) => ({
    url: absoluteUrl(path),
    changeFrequency: path === "/" ? "daily" : "weekly",
    priority: path === "/" ? 1 : 0.7,
  }));

  // A partial sitemap is better than a 500 for a crawler: collectPages stops a
  // listing at its first failing page instead of throwing.
  const [doctors, facilities] = await Promise.all([
    collectPages(
      (page) => fetchDoctors({ page, per_page: PER_PAGE }, CACHE),
      MAX_PAGES,
    ),
    collectPages(
      (page) => fetchFacilities({ page, per_page: PER_PAGE }, CACHE),
      MAX_PAGES,
    ),
  ]);

  entries.push(
    ...doctors.map(({ slug }) => ({
      url: absoluteUrl(`/doctors/${slug}`),
      changeFrequency: "weekly" as const,
      priority: 0.8,
    })),
    ...facilities.map(({ slug }) => ({
      url: absoluteUrl(`/facilities/${slug}`),
      changeFrequency: "weekly" as const,
      priority: 0.8,
    })),
  );

  if (settings.public_forum) {
    let categories: Awaited<ReturnType<typeof fetchForumCategories>> = [];

    try {
      categories = await fetchForumCategories(CACHE);
    } catch {
      // Forum entries are optional; never fail the whole sitemap for them.
    }

    entries.push(
      ...categories.map((category) => ({
        url: absoluteUrl(`/forum/${category.slug}`),
        changeFrequency: "daily" as const,
        priority: 0.6,
      })),
    );

    for (const category of categories) {
      // Paginated like doctors and facilities, and per category: a failing
      // page ends only this category's topics, not every category after it.
      const topics = await collectPages(
        (page) =>
          fetchForumTopicSearch(
            { category: category.slug, page, per_page: PER_PAGE },
            CACHE,
          ),
        MAX_PAGES,
      );

      entries.push(
        ...topics.map((topic) => ({
          url: absoluteUrl(`/forum/${category.slug}/${topic.slug}`),
          lastModified: topic.last_post_at ?? topic.published_at ?? undefined,
          changeFrequency: "weekly" as const,
          priority: 0.5,
        })),
      );
    }
  }

  return entries;
}
