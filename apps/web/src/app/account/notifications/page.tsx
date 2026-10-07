import { redirect } from "next/navigation";
import {
  AccountLayout,
  AccountPage,
} from "@/components/account/account-layout";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { NotificationList } from "@/components/notifications/notification-list";
import { NotificationPreferencesForm } from "@/components/notifications/notification-preferences";
import { ReviewRemindersList } from "@/components/notifications/review-reminders-list";
import {
  fetchMyNotifications,
  fetchMyReviewReminders,
  fetchNotificationPreferences,
} from "@/lib/api/notifications";
import { ApiRequestError } from "@/lib/api/server";
import { getSessionToken } from "@/lib/auth/session";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata = pageMetadata(t("notifications.title"), undefined, {
  noIndex: true,
});

const LOGIN = "/login?redirect=/account/notifications";

/**
 * W8-B „Известувања“: what happened to the member's reviews and posts, the
 * e-mail switches, and pending review reminders.
 */
export default async function AccountNotificationsPage() {
  if (!(await getSessionToken())) {
    redirect(LOGIN);
  }

  let list, preferences, reminders;

  try {
    [list, preferences, reminders] = await Promise.all([
      fetchMyNotifications(),
      fetchNotificationPreferences(),
      fetchMyReviewReminders(),
    ]);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 401) {
      redirect(LOGIN);
    }

    throw error;
  }

  // Invited once (after the first published review) and not yet turned on.
  const showInvite =
    preferences.digest_invited_at !== null && !preferences.types.impact_digest;

  return (
    <AccountPage>
      <AccountPageHero
        badge={t("account.hubBadge")}
        title={t("notifications.title")}
        description={t("notifications.heroDescription")}
      />
      <AccountLayout current="notifications">
        <section
          aria-labelledby="notifications-list-title"
          className="flex flex-col gap-3"
        >
          <div className="flex flex-col gap-1">
            <h2 id="notifications-list-title" className="type-h2 text-ink">
              {t("notifications.listTitle")}
            </h2>
            <p className="type-meta text-ink-2">
              {t("notifications.retention")}
            </p>
          </div>
          <NotificationList
            items={list.data}
            unreadCount={list.meta.unread_count}
          />
        </section>
        <NotificationPreferencesForm
          initial={preferences}
          showInvite={showInvite}
        />
        <ReviewRemindersList initial={reminders} />
      </AccountLayout>
    </AccountPage>
  );
}
