import { apiFetch, apiGetServer, ApiRequestError } from "@/lib/api/server";
import type { ApiErrorBody } from "@/lib/api/errors";

/*
 * W8-B „Известувања“: the member's in-app list, e-mail switches and review
 * reminders (GET /me/notifications, /me/notification-preferences,
 * /me/review-reminders).
 */

export type NotificationProfileRef = {
  kind: "doctor" | "facility" | "pharmacy";
  slug: string;
  name: string;
  path: string;
};

export type MemberNotificationType =
  | "moderation"
  | "review_reply"
  | "review_helpful"
  | "impact_digest"
  | "review_reminder"
  | "digest_invite";

export type MemberNotification = {
  id: number;
  type: MemberNotificationType | string;
  data: Record<string, unknown>;
  read: boolean;
  created_at: string | null;
};

export type NotificationList = {
  data: MemberNotification[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    unread_count: number;
  };
};

export const PREFERENCE_TYPES = [
  "moderation",
  "review_reply",
  "review_helpful",
  "impact_digest",
] as const;

export type PreferenceType = (typeof PREFERENCE_TYPES)[number];

export type NotificationPreferences = {
  email_enabled: boolean;
  types: Record<PreferenceType, boolean>;
  digest_invited_at: string | null;
};

export type ReviewReminder = {
  id: number;
  profile: NotificationProfileRef | null;
  remind_at: string;
  created_at: string | null;
};

export async function fetchMyNotifications(
  page = 1,
): Promise<NotificationList> {
  const response = await apiFetch(`/me/notifications?page=${page}`);

  if (!response.ok) {
    const body = (await response.json().catch(() => undefined)) as
      ApiErrorBody | undefined;
    throw new ApiRequestError(
      body?.message ?? `API request failed (${response.status})`,
      response.status,
      body,
    );
  }

  return (await response.json()) as NotificationList;
}

export async function fetchNotificationPreferences(): Promise<NotificationPreferences> {
  return apiGetServer<NotificationPreferences>("/me/notification-preferences");
}

export async function fetchMyReviewReminders(): Promise<ReviewReminder[]> {
  return apiGetServer<ReviewReminder[]>("/me/review-reminders");
}
