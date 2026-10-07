"use client";

import Link from "next/link";
import { useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import type { ReviewReminder } from "@/lib/notification-types";
import { formatMkDate } from "@/lib/mk-date";
import { t, tFormat } from "@/i18n/t";

/**
 * Pending „Потсети ме за 2 недели“ requests, each cancellable. A cancelled
 * row disappears, the result is announced, and focus moves to the heading.
 */
export function ReviewRemindersList({
  initial,
}: {
  initial: ReviewReminder[];
}) {
  const [list, setList] = useState(initial);
  const [pending, setPending] = useState<number | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const heading = useRef<HTMLHeadingElement>(null);

  async function cancel(reminder: ReviewReminder) {
    const name = reminder.profile?.name ?? "";
    setPending(reminder.id);
    setMessage(null);
    setError(null);

    try {
      const response = await fetch(`/api/review-reminders/${reminder.id}`, {
        method: "DELETE",
      });

      if (!response.ok) {
        setError(t("notifications.reminderError"));
        return;
      }

      setList((current) => current.filter((row) => row.id !== reminder.id));
      setMessage(tFormat("notifications.reminderCancelled", { name }));
      heading.current?.focus();
    } catch {
      setError(t("notifications.reminderError"));
    } finally {
      setPending(null);
    }
  }

  return (
    <section
      aria-labelledby="review-reminders-title"
      className="flex flex-col gap-3"
    >
      <div className="flex flex-col gap-1">
        <h2
          ref={heading}
          id="review-reminders-title"
          tabIndex={-1}
          className="type-h2 text-ink"
        >
          {t("notifications.remindersTitle")}
        </h2>
        <p className="type-meta text-ink-2">
          {t("notifications.remindersLead")}
        </p>
      </div>
      <FormSuccess>{message}</FormSuccess>
      {error ? <FormError>{error}</FormError> : null}
      {list.length === 0 ? (
        <p className="type-body text-ink-2">
          {t("notifications.remindersEmpty")}
        </p>
      ) : (
        <ul className="m-0 flex list-none flex-col gap-3 p-0">
          {list.map((reminder) =>
            reminder.profile ? (
              <li key={reminder.id}>
                <Card
                  padding="md"
                  className="flex flex-wrap items-center justify-between gap-3"
                >
                  <div className="flex min-w-0 flex-col gap-0.5">
                    <Link
                      href={reminder.profile.path}
                      className="link-underline type-body font-semibold text-ink"
                    >
                      {reminder.profile.name}
                    </Link>
                    <span className="type-meta text-ink-2">
                      {tFormat("notifications.reminderOn", {
                        date: formatMkDate(reminder.remind_at) ?? "",
                      })}
                    </span>
                  </div>
                  <Button
                    type="button"
                    variant="secondary"
                    loading={pending === reminder.id}
                    disabled={pending !== null}
                    aria-label={tFormat("notifications.reminderCancelAria", {
                      name: reminder.profile.name,
                    })}
                    onClick={() => void cancel(reminder)}
                  >
                    {t("notifications.reminderCancel")}
                  </Button>
                </Card>
              </li>
            ) : null,
          )}
        </ul>
      )}
    </section>
  );
}
