import { apiFetch, apiGetServer, ApiRequestError } from "@/lib/api/server";
import type { ApiErrorBody } from "@/lib/api/errors";

/*
 * W8-B „Известувања“: the member's in-app list, e-mail switches and review
 * reminders (GET /me/notifications, /me/notification-preferences,
 * /me/review-reminders).
 */

export {
  PREFERENCE_TYPES,
  type MemberNotification,
  type MemberNotificationType,
  type NotificationList,
  type NotificationPreferences,
  type NotificationProfileRef,
  type PreferenceType,
  type ReviewReminder,
} from "@/lib/notification-types";
import type {
  NotificationList,
  NotificationPreferences,
  ReviewReminder,
} from "@/lib/notification-types";

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
