"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { loginHref } from "@/lib/auth/login-href";
import { t } from "@/i18n/t";

/**
 * „Следи ја темата“: a toggle (aria-pressed, one constant name; the icon and
 * fill show the state) that tells the member about new published replies.
 * Optimistic; a refusal rolls back.
 */
export function ForumFollowButton({
  categorySlug,
  topicSlug,
  following: initialFollowing,
  returnTo,
}: {
  categorySlug: string;
  topicSlug: string;
  following: boolean;
  returnTo: string;
}) {
  const router = useRouter();
  const [following, setFollowing] = useState(initialFollowing);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function toggle() {
    if (pending) {
      return;
    }

    const next = !following;
    setError(null);
    setPending(true);
    setFollowing(next);

    try {
      const response = await fetch("/api/forum/topics/follow", {
        method: next ? "PUT" : "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ categorySlug, topicSlug }),
      });

      if (response.status === 401) {
        setFollowing(!next);
        router.push(loginHref(returnTo));
        return;
      }

      if (!response.ok) {
        setFollowing(!next);
        setError(t("forum.followError"));
      }
    } catch {
      setFollowing(!next);
      setError(t("forum.followError"));
    } finally {
      setPending(false);
    }
  }

  return (
    <span className="inline-flex flex-col">
      <Button
        type="button"
        variant={following ? "soft" : "secondary"}
        size="sm"
        leadingIcon={following ? "check" : "bell"}
        aria-pressed={following}
        aria-describedby="forum-follow-hint"
        onClick={toggle}
      >
        {t("forum.follow")}
      </Button>
      <span id="forum-follow-hint" className="sr-only">
        {t("forum.followHint")}
      </span>
      <span role="status" aria-live="polite" className="type-meta text-ink">
        {error}
      </span>
    </span>
  );
}
