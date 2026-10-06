"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { chooseUsernameHref, loginHref } from "@/lib/auth/login-href";
import { t, tFormat } from "@/i18n/t";

type ReviewHelpfulButtonProps = {
  reviewId: number;
  count: number;
  voted: boolean;
  isLoggedIn: boolean;
  /** Still a temporary „clen-…“ name: the API refuses the vote. */
  mustChooseUsername?: boolean;
  /** Where sign-in brings a signed-out visitor back to. */
  returnTo: string;
};

function helpfulText(count: number): string {
  return count > 0
    ? tFormat("reviews.helpfulCount", { count })
    : t("reviews.helpful");
}

/**
 * „Корисно (3)“: a toggle (aria-pressed). The count moves at once and is
 * rolled back if the API refuses; the server's count then wins. Signed out,
 * it is a link to sign-in that returns here (with a temporary username, to
 * the username chooser); a session that expired since the
 * page rendered goes to the same sign-in.
 */
export function ReviewHelpfulButton({
  reviewId,
  count: initialCount,
  voted: initialVoted,
  isLoggedIn,
  mustChooseUsername = false,
  returnTo,
}: ReviewHelpfulButtonProps) {
  const [count, setCount] = useState(initialCount);
  const [voted, setVoted] = useState(initialVoted);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const router = useRouter();

  // Signed out: sign in first. A temporary „clen-…“ name: choose one first.
  if (!isLoggedIn || mustChooseUsername) {
    return (
      <Button
        href={isLoggedIn ? chooseUsernameHref(returnTo) : loginHref(returnTo)}
        variant="ghost"
        size="sm"
        leadingIcon="thumbs-up"
        className="text-ink-2"
      >
        {helpfulText(initialCount)}
      </Button>
    );
  }

  async function toggle() {
    if (pending) {
      return;
    }

    const previous = { count, voted };
    const next = !voted;

    setError(null);
    setPending(true);
    setVoted(next);
    setCount(Math.max(0, count + (next ? 1 : -1)));

    try {
      const response = await fetch("/api/reviews/helpful", {
        method: next ? "PUT" : "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: reviewId }),
      });
      // The session ran out since the page rendered: sign in and come back.
      if (response.status === 401) {
        setVoted(previous.voted);
        setCount(previous.count);
        router.push(loginHref(returnTo));
        return;
      }

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        setVoted(previous.voted);
        setCount(previous.count);
        setError(
          payload.errors?.review?.[0] ??
            payload.message ??
            t("reviews.helpfulError"),
        );
        return;
      }

      if (typeof payload.data?.helpful_count === "number") {
        setCount(payload.data.helpful_count);
      }
      if (typeof payload.data?.has_voted_helpful === "boolean") {
        setVoted(payload.data.has_voted_helpful);
      }
    } catch {
      setVoted(previous.voted);
      setCount(previous.count);
      setError(t("reviews.helpfulError"));
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
        leadingIcon="thumbs-up"
        aria-pressed={voted}
        onClick={toggle}
        className="text-ink-2 aria-pressed:bg-chip-tint aria-pressed:text-ink"
      >
        {helpfulText(count)}
      </Button>
      <span role="status" aria-live="polite" className="type-meta text-ink">
        {error}
      </span>
    </span>
  );
}
