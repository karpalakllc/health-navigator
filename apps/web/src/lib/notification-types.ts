/*
 * W8-B „Известувања“ shapes and constants, free of server-only imports so
 * client components can use them (the fetchers live in lib/api/notifications).
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
