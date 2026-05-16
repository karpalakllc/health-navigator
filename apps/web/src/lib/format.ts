import { t, tFormat } from "@/i18n/t";

export function formatForumReplyCount(count: number): string {
  if (count === 1) {
    return t("forum.replyCountOne");
  }

  return tFormat("forum.repliesCount", { count });
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

export function formatForumLastActivity(iso: string | null | undefined): string {
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
    return tFormat("forum.activityHoursAgo", { count: String(diffHours) });
  }

  const diffDays = Math.round(diffHours / 24);

  if (diffDays < 7) {
    return tFormat("forum.activityDaysAgo", { count: String(diffDays) });
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
