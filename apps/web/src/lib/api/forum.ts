import { apiGet, apiGetPaginated } from "@/lib/api/client";
import { ApiRequestError, apiFetch, apiGetPaginatedServer } from "@/lib/api/server";
import type { PaginatedEnvelope } from "@/lib/api/types";

export type ForumAuthor = {
  name: string;
  member_since: string | null;
  topics_count: number;
  posts_count: number;
  is_team_member: boolean;
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
  author_name: string;
  replies_count: number;
  last_post_at: string | null;
  is_pinned: boolean;
  is_locked: boolean;
  published_at: string | null;
};

export type ForumTopicDetail = {
  slug: string;
  title: string;
  body: string;
  author_name: string;
  author: ForumAuthor;
  category: { slug: string; name: string };
  replies_count: number;
  is_locked: boolean;
  is_pinned: boolean;
  published_at: string | null;
};

export type ForumPost = {
  id: number;
  body: string;
  author_name: string;
  author: ForumAuthor;
  published_at: string | null;
};

export type ForumTopicPage = {
  topic: ForumTopicDetail;
  posts: ForumPost[];
  meta: PaginatedEnvelope<ForumPost>["meta"];
};

export type ForumTopicSearchItem = {
  slug: string;
  title: string;
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

export async function fetchForumCategories(): Promise<ForumCategory[]> {
  return apiGet<ForumCategory[]>("/forum/categories");
}

export async function fetchForumTopicSearch(params: { q?: string; page?: number } = {}) {
  const search = new URLSearchParams();
  if (params.q) search.set("q", params.q);
  if (params.page) search.set("page", String(params.page));
  const query = search.toString();

  return apiGetPaginated<ForumTopicSearchItem>(
    `/forum/topics${query ? `?${query}` : ""}`,
  );
}

export async function fetchForumTopics(
  categorySlug: string,
  params: { q?: string; page?: number } = {},
) {
  const search = new URLSearchParams();
  if (params.q) search.set("q", params.q);
  if (params.page) search.set("page", String(params.page));
  const query = search.toString();

  return apiGetPaginated<ForumTopicListItem>(
    `/forum/categories/${categorySlug}/topics${query ? `?${query}` : ""}`,
  );
}

export async function fetchForumTopicPage(
  categorySlug: string,
  topicSlug: string,
  page = 1,
): Promise<ForumTopicPage> {
  const response = await apiFetch(
    `/forum/categories/${categorySlug}/topics/${topicSlug}?page=${page}`,
  );

  if (!response.ok) {
    const body = (await response.json().catch(() => null)) as { message?: string } | null;
    throw new ApiRequestError(
      body?.message ?? `API request failed (${response.status})`,
      response.status,
      body?.message ? { message: body.message } : undefined,
    );
  }

  const body = (await response.json()) as {
    data: { topic: ForumTopicDetail; posts: ForumPost[] };
    meta: ForumTopicPage["meta"];
  };

  return {
    topic: body.data.topic,
    posts: body.data.posts,
    meta: body.meta,
  };
}

export async function fetchMyForumTopics(page = 1) {
  return apiGetPaginatedServer<MyForumTopic>(`/me/forum/topics?page=${page}`);
}

export async function fetchMyForumPosts(page = 1) {
  return apiGetPaginatedServer<MyForumPost>(`/me/forum/posts?page=${page}`);
}
