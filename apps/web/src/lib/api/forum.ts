import {
  apiGet,
  apiGetPaginated,
  TAXONOMY_CACHE,
  type ApiCacheOptions,
} from "@/lib/api/client";
import {
  ApiRequestError,
  apiFetch,
  apiGetPaginatedServer,
} from "@/lib/api/server";
import type { PaginatedEnvelope, RemovedItem } from "@/lib/api/types";
import { pathSegment } from "@/lib/api/path";

export type ForumAuthor = {
  name: string;
  member_since: string | null;
  topics_count: number;
  posts_count: number;
  is_team_member: boolean;
  is_forum_moderator: boolean;
};

export type ForumCategory = {
  slug: string;
  name: string;
  description: string | null;
  topics_count?: number;
};

export type ForumTopicListItem = {
  slug: string;
  title: string;
  excerpt?: string;
  author_name: string;
  replies_count: number;
  last_post_at: string | null;
  is_pinned: boolean;
  is_locked: boolean;
  published_at: string | null;
};

/** A forum keyword (docs/seo.md). latin: diacritic-free Latin spelling. */
export type ForumTag = {
  name: string;
  slug: string;
  latin: string;
  /** Tag list and tag page only: publicly visible topics carrying it. */
  topics_count?: number;
  /** Tag list and tag page only: latest activity among those topics. */
  last_activity_at?: string | null;
};

export type ForumTopicDetail = {
  slug: string;
  title: string;
  body: string;
  /** Optional until every API serves it. */
  tags?: ForumTag[];
  /** Latest approved reply, or the publication itself. */
  last_post_at?: string | null;
  author_name: string;
  author: ForumAuthor;
  category: { slug: string; name: string };
  replies_count: number;
  is_locked: boolean;
  is_pinned: boolean;
  published_at: string | null;
  /** Signed-in requests only. can_moderate is present only when true. */
  viewer?: { is_own?: boolean; can_moderate?: boolean };
};

export type ForumPost = {
  id: number;
  body: string;
  author_name: string;
  author: ForumAuthor;
  published_at: string | null;
  /** Written by the topic's opener (the API decides; no ids are exposed). */
  is_topic_author?: boolean;
  /** Signed-in requests only: the viewer wrote this reply. */
  viewer?: { is_own: boolean };
};

export type ForumTopicPage = {
  topic: ForumTopicDetail;
  /** Replies, with a placeholder where a removed one was. */
  posts: Array<ForumPost | RemovedItem>;
  /** Shared keywords first, then the same category; may be cross-category. */
  related_topics?: ForumTopicSearchItem[];
  meta: PaginatedEnvelope<ForumPost>["meta"];
};

export type ForumTopicSearchItem = {
  slug: string;
  title: string;
  excerpt?: string;
  author_name: string;
  replies_count: number;
  last_post_at: string | null;
  published_at: string | null;
  category: { slug: string; name: string };
};

export type MyForumTopic = {
  slug: string;
  title: string;
  status: string;
  rejection_note: string | null;
  replies_count: number;
  last_post_at: string | null;
  published_at: string | null;
  category: { slug: string; name: string };
  created_at: string | null;
};

export type MyForumPost = {
  id: number;
  body: string;
  status: string;
  rejection_note: string | null;
  topic: { slug: string; title: string; category_slug: string };
  created_at: string | null;
};

export async function fetchForumCategories(
  options?: ApiCacheOptions,
): Promise<ForumCategory[]> {
  return apiGet<ForumCategory[]>(
    "/forum/categories",
    options ?? TAXONOMY_CACHE,
  );
}

export async function fetchForumTopicSearch(
  params: {
    q?: string;
    category?: string;
    page?: number;
    per_page?: number;
  } = {},
  options?: ApiCacheOptions,
) {
  const search = new URLSearchParams();
  if (params.q) search.set("q", params.q);
  if (params.category) search.set("category", params.category);
  if (params.page) search.set("page", String(params.page));
  if (params.per_page) search.set("per_page", String(params.per_page));
  const query = search.toString();

  return apiGetPaginated<ForumTopicSearchItem>(
    `/forum/topics${query ? `?${query}` : ""}`,
    options,
  );
}

export async function fetchForumRecentTopics(
  perPage = 8,
  options?: ApiCacheOptions,
) {
  return apiGetPaginated<ForumTopicSearchItem>(
    `/forum/topics/recent?per_page=${perPage}`,
    options,
  );
}

export async function fetchForumTopics(
  categorySlug: string,
  params: {
    q?: string;
    sort?: "latest" | "active";
    page?: number;
    per_page?: number;
  } = {},
  options?: ApiCacheOptions,
) {
  const search = new URLSearchParams();
  if (params.q) search.set("q", params.q);
  if (params.sort && params.sort !== "latest") search.set("sort", params.sort);
  if (params.page) search.set("page", String(params.page));
  if (params.per_page) search.set("per_page", String(params.per_page));
  const query = search.toString();

  return apiGetPaginated<ForumTopicListItem>(
    `/forum/categories/${pathSegment(categorySlug)}/topics${query ? `?${query}` : ""}`,
    options,
  );
}

/** Tags with at least minTopics visible topics (sitemap, llms.txt). */
export async function fetchForumTags(
  params: { min_topics?: number; page?: number; per_page?: number } = {},
  options?: ApiCacheOptions,
) {
  const search = new URLSearchParams();
  if (params.min_topics) search.set("min_topics", String(params.min_topics));
  if (params.page) search.set("page", String(params.page));
  if (params.per_page) search.set("per_page", String(params.per_page));
  const query = search.toString();

  return apiGetPaginated<ForumTag>(
    `/forum/tags${query ? `?${query}` : ""}`,
    options,
  );
}

export type ForumTagPage = {
  tag: ForumTag;
  topics: ForumTopicSearchItem[];
  meta: PaginatedEnvelope<ForumTopicSearchItem>["meta"];
};

/** Throws ApiRequestError (404 for an unknown tag or one without topics). */
export async function fetchForumTagPage(
  slug: string,
  page = 1,
): Promise<ForumTagPage> {
  const response = await apiFetch(
    `/forum/tags/${pathSegment(slug)}?page=${page}`,
  );

  if (!response.ok) {
    throw new ApiRequestError(
      `API request failed (${response.status})`,
      response.status,
    );
  }

  const body = (await response.json()) as {
    data: { tag: ForumTag; topics: ForumTopicSearchItem[] };
    meta: ForumTagPage["meta"];
  };

  return { tag: body.data.tag, topics: body.data.topics, meta: body.meta };
}

/**
 * Forum topics linked to a doctor (confirmed keywords only) or facility
 * profile. Decorative on the profile, so callers treat a failure as none.
 */
export async function fetchRelatedForumTopics(
  target: { doctor: string } | { facility: string },
  limit = 5,
): Promise<ForumTopicSearchItem[]> {
  const [key, slug] =
    "doctor" in target
      ? (["doctor", target.doctor] as const)
      : (["facility", target.facility] as const);
  const search = new URLSearchParams({ [key]: slug, limit: String(limit) });

  return apiGet<ForumTopicSearchItem[]>(
    `/forum/topics/related?${search.toString()}`,
    { revalidate: 300 },
  );
}

export async function fetchForumTopicPage(
  categorySlug: string,
  topicSlug: string,
  page = 1,
): Promise<ForumTopicPage> {
  const response = await apiFetch(
    `/forum/categories/${pathSegment(categorySlug)}/topics/${pathSegment(topicSlug)}?page=${page}`,
  );

  if (!response.ok) {
    const body = (await response.json().catch(() => null)) as {
      message?: string;
    } | null;
    throw new ApiRequestError(
      body?.message ?? `API request failed (${response.status})`,
      response.status,
      body?.message ? { message: body.message } : undefined,
    );
  }

  const body = (await response.json()) as {
    data: {
      topic: ForumTopicDetail;
      posts: ForumTopicPage["posts"];
      related_topics?: ForumTopicSearchItem[];
    };
    meta: ForumTopicPage["meta"];
  };

  return {
    topic: body.data.topic,
    posts: body.data.posts,
    related_topics: body.data.related_topics ?? [],
    meta: body.meta,
  };
}

export async function fetchMyForumTopics(page = 1) {
  return apiGetPaginatedServer<MyForumTopic>(`/me/forum/topics?page=${page}`);
}

export async function fetchMyForumPosts(page = 1) {
  return apiGetPaginatedServer<MyForumPost>(`/me/forum/posts?page=${page}`);
}
