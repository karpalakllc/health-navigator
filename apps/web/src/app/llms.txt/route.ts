import {
  fetchForumCategories,
  fetchForumRecentTopics,
  fetchForumTags,
} from "@/lib/api/forum";
import { loadPublicSettings } from "@/lib/api/settings";
import { buildLlmsTxt } from "@/lib/llms-txt";
import { isIndexableTag, TAG_INDEXABLE_MIN_TOPICS } from "@/lib/metadata";
import { siteUrl } from "@/lib/site-url";

/*
 * Rendered per request from hour-cached API reads, so `next build` never
 * needs the API and a cold cache never pins a stripped-down file for an hour
 * (the sitemap's problem, solved there differently).
 */
export const dynamic = "force-dynamic";

const CACHE = { revalidate: 3600 } as const;

export async function GET(): Promise<Response> {
  const settings = await loadPublicSettings(CACHE.revalidate);
  const forum = settings.public_forum && !settings.degraded;

  // Every forum list is optional: a failing one leaves its section out.
  const [categories, topics, tags] = forum
    ? await Promise.all([
        fetchForumCategories(CACHE).catch(() => []),
        fetchForumRecentTopics(20, CACHE)
          .then((page) => page.data)
          .catch(() => []),
        fetchForumTags(
          { min_topics: TAG_INDEXABLE_MIN_TOPICS, per_page: 50 },
          CACHE,
        )
          .then((page) =>
            page.data.filter((tag) => isIndexableTag(tag.topics_count)),
          )
          .catch(() => []),
      ])
    : [[], [], []];

  const body = buildLlmsTxt({
    siteUrl: siteUrl(),
    modules: { forum, guidance: settings.public_guidance },
    categories,
    topics,
    tags,
  });

  return new Response(body, {
    headers: {
      "Content-Type": "text/plain; charset=utf-8",
      "Cache-Control": "public, max-age=3600",
    },
  });
}
