"use client";

import Link from "next/link";
import { useEffect, useId, useRef, useState } from "react";
import { buttonClassName, Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/field";
import { Icon } from "@/components/ui/icons";
import { loginHref } from "@/lib/auth/login-href";
import { formatMkDate } from "@/lib/mk-date";
import {
  canShowReviewPrompt,
  markReviewPromptShown,
} from "@/lib/review-prompt-storage";
import { t, tFormat } from "@/i18n/t";

/** Seconds on the page before the prompt may appear. */
export const PROMPT_DWELL_MS = 15_000;

/** …or this share of the page scrolled, whichever comes first. */
export const PROMPT_SCROLL_SHARE = 0.3;

type ReviewPromptProps = {
  kind: "doctor" | "facility" | "pharmacy";
  slug: string;
  name: string;
  isLoggedIn: boolean;
  /** Where „Оцени“ lands (the profile's review form). */
  basePath: string;
};

/**
 * „Дали сте биле кај д-р Х?“ (W8-B): a calm inline card above the reviews,
 * never a dialog. It may appear after a short dwell or some scrolling, and
 * only while its place is still below the visible part of the page, so it
 * never pushes what the visitor is reading. Once per profile per device
 * (lib/review-prompt-storage), counted when the card comes on screen;
 * dismissible. The server leaves it out on the
 * viewer's own profile and when they already reviewed this one.
 *
 * A signed-in member can tick „Потсети ме за 2 недели“: one e-mail later,
 * stored only until it is sent (and cancellable in „Известувања“).
 */
export function ReviewPrompt({
  kind,
  slug,
  name,
  isLoggedIn,
  basePath,
}: ReviewPromptProps) {
  const [visible, setVisible] = useState(false);
  const [dismissed, setDismissed] = useState(false);
  const slot = useRef<HTMLDivElement>(null);
  const titleId = useId();

  useEffect(() => {
    if (!canShowReviewPrompt(kind, slug)) {
      return;
    }

    let eligible = false;
    let done = false;

    function reveal() {
      const top = slot.current?.getBoundingClientRect().top;

      // Only below the fold: appearing above the reader would shift the page.
      if (!eligible || done || top === undefined || top < window.innerHeight) {
        return;
      }

      done = true;
      setVisible(true);
      cleanup();
    }

    function onScroll() {
      const scrollable =
        document.documentElement.scrollHeight - window.innerHeight;

      if (
        scrollable > 0 &&
        window.scrollY / scrollable >= PROMPT_SCROLL_SHARE
      ) {
        eligible = true;
      }

      reveal();
    }

    const timer = setTimeout(() => {
      eligible = true;
      reveal();
    }, PROMPT_DWELL_MS);

    function cleanup() {
      clearTimeout(timer);
      window.removeEventListener("scroll", onScroll);
    }

    window.addEventListener("scroll", onScroll, { passive: true });

    return cleanup;
  }, [kind, slug]);

  // „Shown“ (once per profile per device) only when the card actually
  // comes on screen: a visitor who never scrolls down to it may see it on a
  // later visit.
  useEffect(() => {
    const card = slot.current;

    if (!visible || !card) {
      return;
    }

    if (typeof IntersectionObserver === "undefined") {
      markReviewPromptShown(kind, slug);
      return;
    }

    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        markReviewPromptShown(kind, slug);
        observer.disconnect();
      }
    });
    observer.observe(card);

    return () => observer.disconnect();
  }, [visible, kind, slug]);

  const question = tFormat(
    kind === "doctor" ? "reviewFlow.promptDoctor" : "reviewFlow.promptPlace",
    { name },
  );

  return (
    <div ref={slot}>
      {visible && !dismissed ? (
        <Card
          as="aside"
          tone="apricot"
          padding="md"
          aria-labelledby={titleId}
          data-track="review-prompt"
          className="motion-fade-in flex flex-col gap-3"
        >
          <div className="flex items-start gap-3">
            <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-ink">
              <Icon name="star" size={22} />
            </span>
            <div className="flex min-w-0 flex-col gap-1">
              <p id={titleId} className="type-body font-semibold text-ink">
                {question}
              </p>
              <p className="type-meta text-ink">{t("reviewFlow.promptLead")}</p>
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2 sm:pl-[52px]">
            {isLoggedIn ? (
              <Button
                type="button"
                leadingIcon="star"
                onClick={() => {
                  const form = document.getElementById("review-form");
                  form?.scrollIntoView?.({ block: "center" });
                  form
                    ?.querySelector<HTMLElement>('[role="radio"]')
                    ?.focus({ preventScroll: true });
                }}
              >
                {t("reviewFlow.promptRate")}
              </Button>
            ) : (
              <Link
                href={loginHref(`${basePath}#review-form`)}
                className={buttonClassName()}
              >
                <Icon name="star" size={20} />
                <span>{t("reviewFlow.promptRate")}</span>
              </Link>
            )}
            <Button
              type="button"
              variant="white"
              onClick={() => setDismissed(true)}
            >
              {t("reviewFlow.promptLater")}
            </Button>
          </div>
          {isLoggedIn ? (
            <div className="sm:pl-[52px]">
              <ReminderToggle kind={kind} slug={slug} />
            </div>
          ) : null}
        </Card>
      ) : null}
    </div>
  );
}

function ReminderToggle({
  kind,
  slug,
}: {
  kind: ReviewPromptProps["kind"];
  slug: string;
}) {
  const [reminder, setReminder] = useState<{
    id: number;
    remindAt: string;
  } | null>(null);
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function toggle(on: boolean) {
    setPending(true);
    setError(null);
    setMessage(null);

    try {
      if (on) {
        const response = await fetch("/api/review-reminders", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ kind, slug }),
        });
        const payload = (await response.json().catch(() => null)) as {
          data?: { id: number; remind_at: string };
          errors?: { reminder?: string[] };
          message?: string;
        } | null;

        if (!response.ok || !payload?.data) {
          setError(
            payload?.errors?.reminder?.[0] ??
              t("reviewFlow.promptReminderError"),
          );
          return;
        }

        setReminder({ id: payload.data.id, remindAt: payload.data.remind_at });
        setMessage(
          tFormat("reviewFlow.promptReminderSet", {
            date: formatMkDate(payload.data.remind_at) ?? "",
          }),
        );
      } else if (reminder) {
        const response = await fetch(`/api/review-reminders/${reminder.id}`, {
          method: "DELETE",
        });

        if (!response.ok) {
          setError(t("reviewFlow.promptReminderError"));
          return;
        }

        setReminder(null);
        setMessage(t("reviewFlow.promptReminderRemoved"));
      }
    } catch {
      setError(t("reviewFlow.promptReminderError"));
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col">
      <Checkbox
        label={t("reviewFlow.promptRemind")}
        hint={t("reviewFlow.promptRemindHint")}
        error={error ?? undefined}
        checked={reminder !== null}
        disabled={pending}
        onChange={(event) => void toggle(event.target.checked)}
      />
      <p role="status" className="pl-9 type-meta text-ink">
        {message}
      </p>
    </div>
  );
}
