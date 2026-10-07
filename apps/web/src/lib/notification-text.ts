import { t, tFormat } from "@/i18n/t";
import type { MemberNotification } from "@/lib/notification-types";

/*
 * One „Известувања“ line: its sentence and where it leads. The API stores only
 * what the line needs (a profile's public name and path, a count, a content
 * title); anything unexpected falls back to a generic line rather than
 * rendering raw data. Links are same-site paths only.
 */

export type NotificationLine = {
  text: string;
  href: string | null;
};

const MONTHS = t("reviews.monthNames").split(",");

function str(value: unknown): string | null {
  return typeof value === "string" && value.trim() !== "" ? value : null;
}

function num(value: unknown): number | null {
  return typeof value === "number" && Number.isFinite(value) ? value : null;
}

/** A same-site path („/doctors/…“), never a protocol-relative or absolute URL. */
export function safePath(value: unknown): string | null {
  const path = str(value);

  return path && path.startsWith("/") && !path.startsWith("//") ? path : null;
}

function profile(data: Record<string, unknown>): {
  name: string;
  path: string | null;
} | null {
  const ref = data.profile;

  if (!ref || typeof ref !== "object") {
    return null;
  }

  const name = str((ref as Record<string, unknown>).name);

  return name
    ? { name, path: safePath((ref as Record<string, unknown>).path) }
    : null;
}

/** „2026-09“ → „септември 2026“. */
export function monthLabel(value: unknown): string | null {
  const match = /^(\d{4})-(\d{2})$/.exec(str(value) ?? "");
  const month = match ? MONTHS[Number(match[2]) - 1] : undefined;

  return match && month ? `${month} ${match[1]}` : null;
}

const MODERATION_KEYS = {
  published: {
    review: "notifications.item.publishedReview",
    topic: "notifications.item.publishedTopic",
    post: "notifications.item.publishedPost",
  },
  rejected: {
    review: "notifications.item.rejectedReview",
    topic: "notifications.item.rejectedTopic",
    post: "notifications.item.rejectedPost",
  },
  removed: {
    review: "notifications.item.removedReview",
    topic: "notifications.item.removedTopic",
    post: "notifications.item.removedPost",
  },
} as const;

const FALLBACK: NotificationLine = {
  text: t("notifications.item.fallback"),
  href: null,
};

export function notificationLine(item: MemberNotification): NotificationLine {
  const data = item.data ?? {};

  switch (item.type) {
    case "moderation": {
      const title = str(data.title);
      const event = str(data.event);
      const content = str(data.content);

      if (!title) {
        return FALLBACK;
      }

      if (event === "report_resolved") {
        return {
          text: tFormat(
            data.removed === true
              ? "notifications.item.reportRemoved"
              : "notifications.item.reportKept",
            { title },
          ),
          href: null,
        };
      }

      if (
        (event === "published" ||
          event === "rejected" ||
          event === "removed") &&
        (content === "review" || content === "topic" || content === "post")
      ) {
        return {
          text: tFormat(MODERATION_KEYS[event][content], { title }),
          href: safePath(data.path),
        };
      }

      return FALLBACK;
    }
    case "review_reply": {
      const ref = profile(data);

      return ref
        ? {
            text: tFormat(
              data.from_doctor === true
                ? "notifications.item.replyDoctor"
                : "notifications.item.replyProfile",
              { name: ref.name },
            ),
            href: ref.path ? `${ref.path}#reviews` : null,
          }
        : FALLBACK;
    }
    case "review_helpful": {
      const ref = profile(data);
      const count = num(data.new_votes);

      return ref && count !== null
        ? {
            text: tFormat("notifications.item.helpful", {
              name: ref.name,
              count,
            }),
            href: "/account/reviews",
          }
        : FALLBACK;
    }
    case "review_reminder": {
      const ref = profile(data);

      return ref
        ? {
            text: tFormat("notifications.item.reminder", { name: ref.name }),
            href: ref.path ? `${ref.path}#review-form` : null,
          }
        : FALLBACK;
    }
    case "impact_digest": {
      const month = monthLabel(data.month);
      const stats =
        data.stats && typeof data.stats === "object"
          ? (data.stats as Record<string, unknown>)
          : {};

      return month
        ? {
            text: tFormat("notifications.item.digest", {
              month,
              views: num(stats.review_views) ?? 0,
              helpful: num(stats.helpful_votes) ?? 0,
            }),
            href: "/account/reviews",
          }
        : FALLBACK;
    }
    case "digest_invite":
      return {
        text: t("notifications.item.digestInvite"),
        href: "#notification-preferences",
      };
    default:
      return FALLBACK;
  }
}
