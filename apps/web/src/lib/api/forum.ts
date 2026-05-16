import { apiGet, apiGetPaginated } from "@/lib/api/client";
import { apiGetPaginatedServer } from "@/lib/api/server";
import { apiUrl } from "@/lib/config";
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

export type MyForumTopic = {
  slug: string;
  title: string;
  status: string;
  category: { slug: string; name: string };
  created_at: string | null;
};

export type MyForumPost = {
  id: number;
  body: string;
  status: string;
  topic: { slug: string; title: string; category_slug: string };
  created_at: string | null;
};

export async function fetchForumCategories(): Promise<ForumCategory[]> {
  return apiGet<ForumCategory[]>("/forum/categories");
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
  const response = await fetch(
    apiUrl(
      `/forum/categories/${categorySlug}/topics/${topicSlug}?page=${page}`,
    ),
    { cache: "no-store" },
  );

  if (!response.ok) {
    throw new Error(`API request failed (${response.status})`);
  }

  const body = await response.json();

  return {
    topic: body.data.topic as ForumTopicDetail,
    posts: body.data.posts as ForumPost[],
    meta: body.meta,
  };
}

export async function fetchMyForumTopics(page = 1) {
  return apiGetPaginatedServer<MyForumTopic>(`/me/forum/topics?page=${page}`);
}

export async function fetchMyForumPosts(page = 1) {
  return apiGetPaginatedServer<MyForumPost>(`/me/forum/posts?page=${page}`);
}
