"use client";

import Link from "next/link";
import { useEffect } from "react";
import { Card } from "@/components/ui/card";
import { Icon, type IconName } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import type { MemberNotification } from "@/lib/api/notifications";
import { formatMkDate } from "@/lib/mk-date";
import { notificationLine } from "@/lib/notification-text";
import { t } from "@/i18n/t";

const ICONS: Record<string, IconName> = {
  moderation: "check",
  review_reply: "reply",
  review_helpful: "heart",
  review_reminder: "clock",
  impact_digest: "activity",
  digest_invite: "mail",
};

/**
 * The member's „Известувања“, newest first. Opening the page marks them read
 * (one request, only when something is unread); the „Ново“ tag stays on
 * this render so the member still sees what was new.
 */
export function NotificationList({
  items,
  unreadCount,
}: {
  items: MemberNotification[];
  unreadCount: number;
}) {
  useEffect(() => {
    if (unreadCount > 0) {
      void fetch("/api/notifications/read", { method: "POST" }).catch(() => {});
    }
  }, [unreadCount]);

  if (items.length === 0) {
    return (
      <Card padding="md" className="flex items-center gap-3">
        <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-apricot text-ink">
          <Icon name="bell" size={24} />
        </span>
        <p className="type-body text-ink">{t("notifications.empty")}</p>
      </Card>
    );
  }

  return (
    <ul
      aria-labelledby="notifications-list-title"
      className="m-0 flex list-none flex-col gap-3 p-0"
    >
      {items.map((item) => {
        const line = notificationLine(item);
        const date = formatMkDate(item.created_at);

        return (
          <li key={item.id}>
            <Card
              padding="md"
              className="flex items-start gap-3 transition-shadow motion-reduce:transition-none"
            >
              <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-chip-tint text-ink">
                <Icon name={ICONS[item.type] ?? "bell"} size={20} />
              </span>
              <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="type-body text-ink">
                  {line.href ? (
                    <Link
                      href={line.href}
                      className="link-underline text-ink hover:text-black"
                    >
                      {line.text}
                    </Link>
                  ) : (
                    line.text
                  )}
                </p>
                <div className="flex flex-wrap items-center gap-2">
                  {date && item.created_at ? (
                    <time
                      dateTime={item.created_at}
                      className="type-meta text-ink-2"
                    >
                      {date}
                    </time>
                  ) : null}
                  {!item.read ? (
                    <Tag tone="white">{t("notifications.unread")}</Tag>
                  ) : null}
                </div>
              </div>
            </Card>
          </li>
        );
      })}
    </ul>
  );
}
