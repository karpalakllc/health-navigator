import { t, tCount, tFormat } from "@/i18n/t";

export function formatForumReplyCount(count: number): string {
  return tCount("forum.repliesCount", count);
}

/**
 * The compact author line under a forum post's name: „Член од 2025 · 12
 * објави“. The post count adds the author's approved topics and replies. The year is read in
 * Europe/Skopje so the server and the browser render the same text.
 */
export function formatForumAuthorStats(author: {
  member_since: string | null;
  topics_count: number;
  posts_count: number;
}): string {
  const parts: string[] = [];
  const since = author.member_since ? new Date(author.member_since) : null;

  if (since && !Number.isNaN(since.getTime())) {
    const year = new Intl.DateTimeFormat("en", {
      year: "numeric",
      timeZone: "Europe/Skopje",
    }).format(since);
    parts.push(tFormat("forum.authorMemberSince", { year }));
  }

  const posts = author.topics_count + author.posts_count;

  if (posts > 0) {
    parts.push(tCount("forum.authorPosts", posts));
  }

  return parts.join(" · ");
}

export function formatForumDateTime(iso: string | null | undefined): string {
  if (!iso) {
    return "";
  }

  const date = new Date(iso);

  if (Number.isNaN(date.getTime())) {
    return "";
  }

  return new Intl.DateTimeFormat("mk-MK", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

export function formatForumLastActivity(
  iso: string | null | undefined,
): string {
  if (!iso) {
    return t("forum.noActivityYet");
  }

  const date = new Date(iso);

  if (Number.isNaN(date.getTime())) {
    return "";
  }

  const now = Date.now();
  const diffMs = now - date.getTime();
  const diffMinutes = Math.round(diffMs / (1000 * 60));

  if (diffMinutes < 1) {
    return t("forum.activityJustNow");
  }

  if (diffMinutes < 60) {
    return tFormat("forum.activityMinutesAgo", { count: String(diffMinutes) });
  }

  const diffHours = Math.round(diffMinutes / 60);

  if (diffHours < 24) {
    return tCount("forum.activityHoursAgo", diffHours);
  }

  const diffDays = Math.round(diffHours / 24);

  if (diffDays < 7) {
    return tCount("forum.activityDaysAgo", diffDays);
  }

  return formatForumDateTime(iso);
}

export function authorInitials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);

  if (parts.length === 0) {
    return "?";
  }

  if (parts.length === 1) {
    return parts[0]!.slice(0, 2).toUpperCase();
  }

  return `${parts[0]![0] ?? ""}${parts[1]![0] ?? ""}`.toUpperCase();
}
