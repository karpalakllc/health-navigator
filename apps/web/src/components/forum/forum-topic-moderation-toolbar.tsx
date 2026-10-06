"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

type ForumTopicModerationToolbarProps = {
  categorySlug: string;
  topicSlug: string;
  isPinned: boolean;
  isLocked: boolean;
};

type ModerationPatch = { is_pinned?: boolean; is_locked?: boolean };

function doneMessage(patch: ModerationPatch): string | null {
  if (typeof patch.is_pinned === "boolean") {
    return t(
      patch.is_pinned ? "forum.topicPinnedDone" : "forum.topicUnpinnedDone",
    );
  }
  if (typeof patch.is_locked === "boolean") {
    return t(
      patch.is_locked ? "forum.topicLockedDone" : "forum.topicUnlockedDone",
    );
  }
  return null;
}

/**
 * Moderators only: pin/lock on a sand strip above the thread. Each button
 * names the action it will take („Откачи тема“ while pinned); the state itself
 * shows as the „Закачено“ / „Заклучено“ tags by the title after the refresh.
 *
 * While a change saves, the pressed button stays focusable (loading, not
 * disabled), so keyboard focus isn't dropped on <body>; the outcome is
 * announced in a polite live region.
 */
export function ForumTopicModerationToolbar({
  categorySlug,
  topicSlug,
  isPinned: initialPinned,
  isLocked: initialLocked,
}: ForumTopicModerationToolbarProps) {
  const router = useRouter();
  const [isPinned, setIsPinned] = useState(initialPinned);
  const [isLocked, setIsLocked] = useState(initialLocked);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function updateModeration(patch: ModerationPatch) {
    if (pending) {
      return;
    }
    setError(null);
    setDone(null);
    setPending(true);

    try {
      const response = await fetch("/api/forum/topics/moderation", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          categorySlug,
          topicSlug,
          ...patch,
        }),
      });

      const payload = await response.json();

      if (!response.ok) {
        setError(payload.message ?? t("forum.moderationError"));
        return;
      }

      if (typeof payload.data?.is_pinned === "boolean") {
        setIsPinned(payload.data.is_pinned);
      }

      if (typeof payload.data?.is_locked === "boolean") {
        setIsLocked(payload.data.is_locked);
      }

      setDone(doneMessage(patch));
      router.refresh();
    } catch {
      setError(t("forum.moderationErrorRetry"));
    } finally {
      setPending(false);
    }
  }

  return (
    <Card
      as="section"
      tone="sand"
      padding="sm"
      aria-labelledby="forum-moderation-heading"
      className="flex flex-col gap-3"
    >
      <div className="flex flex-wrap items-center gap-x-4 gap-y-3">
        <h2
          id="forum-moderation-heading"
          className="flex items-center gap-2 type-label text-ink"
        >
          <Icon name="shield-check" size={20} />
          {t("forum.moderationTools")}
        </h2>
        <div className="flex flex-wrap gap-2">
          <Button
            variant="white"
            loading={pending}
            onClick={() => updateModeration({ is_pinned: !isPinned })}
          >
            {isPinned ? t("forum.unpinTopic") : t("forum.pinTopic")}
          </Button>
          <Button
            variant="white"
            loading={pending}
            onClick={() => updateModeration({ is_locked: !isLocked })}
          >
            {isLocked ? t("forum.unlockTopic") : t("forum.lockTopic")}
          </Button>
        </div>
      </div>
      {error ? <FormError>{error}</FormError> : null}
      <FormSuccess>{done}</FormSuccess>
    </Card>
  );
}
