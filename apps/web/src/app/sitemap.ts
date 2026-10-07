import type { MetadataRoute } from "next";
import { fetchDoctors } from "@/lib/api/doctors";
import { fetchFacilities } from "@/lib/api/facilities";
import {
  fetchForumCategories,
  fetchForumTags,
  fetchForumTopics,
} from "@/lib/api/forum";
import { fetchPharmacies } from "@/lib/api/pharmacies";
import { fetchProducts } from "@/lib/api/products";
import {
  SettingsUnavailableError,
  shouldAbortSitemap,
} from "@/lib/api/public-settings";
import { loadPublicSettings } from "@/lib/api/settings";
import { collectPages } from "@/lib/collect-pages";
import { FIRST_AID_BASE, publishedFirstAidGuides } from "@/content/first-aid";
import { isIndexableTag, TAG_INDEXABLE_MIN_TOPICS } from "@/lib/metadata";
import { absoluteUrl } from "@/lib/site-url";
import { fetchUrgentCareCities } from "@/lib/api/urgent-care";
import { GUIDES } from "@/content/guides/guides";
import { citySlug } from "@/lib/urgent-care";

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
    "/about",
    "/privacy",
    "/terms",
    "/disclaimer",
    // „Транспарентност“ (W5-I): moderation statistics and ordering rules.
    "/transparency",
    // „Заедница“ (W8-C): monthly top lists and how titles are earned.
    "/community",
    // „Каде веднаш“ and the healthcare guides (docs/urgent-care.md).
    "/urgent-care",
    "/guides",
    ...GUIDES.map((guide) => `/guides/${guide.slug}`),
    // Optional modules answer 503 while switched off, so their URLs are only
    // advertised while they are on.
    ...(settings.public_forum ? ["/forum"] : []),
    ...(settings.public_guidance ? ["/guidance"] : []),
    ...(settings.public_pharmacies ? ["/pharmacies"] : []),
    ...(settings.public_products ? ["/products"] : []),
  ];

  // „Прва помош“: only clinician-reviewed, published guides (docs/first-aid.md);
  // the index only once there is at least one.
  const firstAid = publishedFirstAidGuides();
  if (firstAid.length > 0) {
    staticPaths.push(
      FIRST_AID_BASE,
      ...firstAid.map((guide) => `${FIRST_AID_BASE}/${guide.slug}`),
    );
  }

  const entries: MetadataRoute.Sitemap = staticPaths.map((path) => ({
    url: absoluteUrl(path),
    changeFrequency: path === "/" ? "daily" : "weekly",
    priority: path === "/" ? 1 : 0.7,
  }));

  // A partial sitemap is better than a 500 for a crawler: collectPages stops a
  // listing at its first failing page instead of throwing.
  // City pages of „Каде веднаш“ with at least one place (the others are
  // noindex). A failure only drops them from this sitemap.
  const urgentCities = await fetchUrgentCareCities(CACHE).catch(() => []);
  const urgentPaths = [
    ...new Set(
      urgentCities
        .map((city) => citySlug(city.name))
        .filter((slug): slug is string => slug !== null),
    ),
  ];
  entries.push(
    ...urgentPaths.map((slug) => ({
      url: absoluteUrl(`/urgent-care/${slug}`),
      changeFrequency: "weekly" as const,
      priority: 0.7,
    })),
  );

  const [doctors, facilities, pharmacies, products] = await Promise.all([
    collectPages(
      (page) => fetchDoctors({ page, per_page: PER_PAGE }, CACHE),
      MAX_PAGES,
    ),
    collectPages(
      (page) => fetchFacilities({ page, per_page: PER_PAGE }, CACHE),
      MAX_PAGES,
    ),
    // Like their list pages, only while the module is on (503 otherwise).
    settings.public_pharmacies
      ? collectPages(
          (page) => fetchPharmacies({ page, per_page: PER_PAGE }, CACHE),
          MAX_PAGES,
        )
      : [],
    settings.public_products
      ? collectPages(
          (page) => fetchProducts({ page, per_page: PER_PAGE }, CACHE),
          MAX_PAGES,
        )
      : [],
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
    ...pharmacies.map(({ slug }) => ({
      url: absoluteUrl(`/pharmacies/${slug}`),
      changeFrequency: "weekly" as const,
      priority: 0.7,
    })),
    ...products.map(({ slug }) => ({
      url: absoluteUrl(`/products/${slug}`),
      changeFrequency: "weekly" as const,
      priority: 0.5,
    })),
  );

  if (settings.public_forum) {
    entries.push(...(await forumEntries()));
  }

  return entries;
}

/** Latest of ISO timestamps (lexical order works for one offset), if any. */
function latest(dates: (string | null | undefined)[]): string | undefined {
  return dates
    .filter((date): date is string => Boolean(date))
    .sort()
    .pop();
}

/**
 * Categories, their topics and the indexable tag pages, each with lastmod
 * from real activity (Google uses lastmod when it is consistently accurate,
 * docs/seo.md). Forum entries are optional: a failure drops them, never the
 * whole sitemap.
 */
async function forumEntries(): Promise<MetadataRoute.Sitemap> {
  let categories: Awaited<ReturnType<typeof fetchForumCategories>> = [];

  try {
    categories = await fetchForumCategories(CACHE);
  } catch {
    return [];
  }

  const entries: MetadataRoute.Sitemap = [];

  for (const category of categories) {
    // Paginated per category: a failing page ends only this category's
    // topics. (This used to call the search endpoint, which returns nothing
    // without a query, so no topic was ever listed.)
    const topics = await collectPages(
      (page) =>
        fetchForumTopics(category.slug, { page, per_page: PER_PAGE }, CACHE),
      MAX_PAGES,
    );
    const activity = topics.map(
      (topic) => topic.last_post_at ?? topic.published_at,
    );

    entries.push(
      {
        url: absoluteUrl(`/forum/${category.slug}`),
        lastModified: latest(activity),
        changeFrequency: "daily" as const,
        priority: 0.6,
      },
      ...topics.map((topic) => ({
        url: absoluteUrl(`/forum/${category.slug}/${topic.slug}`),
        lastModified: topic.last_post_at ?? topic.published_at ?? undefined,
        changeFrequency: "weekly" as const,
        priority: 0.5,
      })),
    );
  }

  // Only tag pages that are indexable (noindex below the threshold).
  const tags = await collectPages(
    (page) =>
      fetchForumTags(
        { min_topics: TAG_INDEXABLE_MIN_TOPICS, page, per_page: PER_PAGE },
        CACHE,
      ),
    MAX_PAGES,
  );

  entries.push(
    ...tags
      .filter((tag) => isIndexableTag(tag.topics_count))
      .map((tag) => ({
        url: absoluteUrl(`/forum/tags/${tag.slug}`),
        lastModified: tag.last_activity_at ?? undefined,
        changeFrequency: "weekly" as const,
        priority: 0.5,
      })),
  );

  return entries;
}
