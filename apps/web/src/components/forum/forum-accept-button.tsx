"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { t } from "@/i18n/t";

/**
 * „Означи како најкорисен“ for the topic's author (or a moderator) on a
 * reply; on the marked reply, „Тргни ја ознаката“. The thread re-renders
 * from the server afterwards (the answer moves to the top).
 */
export function ForumAcceptButton({
  categorySlug,
  topicSlug,
  postId,
  accepted,
}: {
  categorySlug: string;
  topicSlug: string;
  postId: number;
  accepted: boolean;
}) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit() {
    setError(null);
    setPending(true);

    try {
      const response = await fetch("/api/forum/topics/accepted-answer", {
        method: accepted ? "DELETE" : "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ categorySlug, topicSlug, postId }),
      });

      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        setError(
          payload.errors?.post_id?.[0] ??
            payload.message ??
            t("forum.acceptError"),
        );
        return;
      }

      router.refresh();
    } catch {
      setError(t("forum.acceptError"));
    } finally {
      setPending(false);
    }
  }

  return (
    <span className="inline-flex flex-col">
      <Button
        type="button"
        variant="ghost"
        size="sm"
        leadingIcon={accepted ? "x" : "award"}
        loading={pending}
        onClick={submit}
        className="text-ink-2"
      >
        {accepted ? t("forum.unmarkAccepted") : t("forum.markAccepted")}
      </Button>
      <span role="status" aria-live="polite" className="type-meta text-ink">
        {error}
      </span>
    </span>
  );
}
