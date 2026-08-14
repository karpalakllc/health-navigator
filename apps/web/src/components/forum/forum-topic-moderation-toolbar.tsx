"use client";

import { useRouter } from "next/navigation";
import { useState, type ReactNode } from "react";
import { Badge } from "@/components/ui/badge";
import { t } from "@/i18n/t";
import { cn } from "@/lib/cn";

type ForumTopicModerationToolbarProps = {
  categorySlug: string;
  topicSlug: string;
  isPinned: boolean;
  isLocked: boolean;
};

function ModerationIconButton({
  label,
  pressed,
  onClick,
  disabled,
  children,
}: {
  label: string;
  pressed: boolean;
  onClick: () => void;
  disabled: boolean;
  children: ReactNode;
}) {
  return (
    <button
      type="button"
      title={label}
      aria-label={label}
      aria-pressed={pressed}
      disabled={disabled}
      onClick={onClick}
      className={cn(
        "inline-flex size-10 items-center justify-center rounded-lg border text-sm transition-colors",
        "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
        "disabled:cursor-not-allowed disabled:opacity-50",
        pressed
          ? "border-primary/40 bg-primary/10 text-primary"
          : "border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground",
      )}
    >
      {children}
    </button>
  );
}

function PinIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      className="size-5"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <path
        d="M12 17v5M9 3h6l1 7h-4l1 7H9l1-7H7L9 3z"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function LockIcon({ locked }: { locked: boolean }) {
  return locked ? (
    <svg
      viewBox="0 0 24 24"
      className="size-5"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <rect x="5" y="11" width="14" height="10" rx="2" />
      <path d="M8 11V8a4 4 0 0 1 8 0v3" strokeLinecap="round" />
    </svg>
  ) : (
    <svg
      viewBox="0 0 24 24"
      className="size-5"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <rect x="5" y="11" width="14" height="10" rx="2" />
      <path d="M8 11V8a4 4 0 0 1 7.5-1" strokeLinecap="round" />
    </svg>
  );
}

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
  const [pending, setPending] = useState(false);

  async function updateModeration(patch: {
    is_pinned?: boolean;
    is_locked?: boolean;
  }) {
    setError(null);
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

      router.refresh();
    } catch {
      setError(t("forum.moderationErrorRetry"));
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="grid gap-2 rounded-xl border border-border/80 bg-muted/30 p-3">
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
          {t("forum.moderationTools")}
        </span>
        <ModerationIconButton
          label={isPinned ? t("forum.unpinTopic") : t("forum.pinTopic")}
          pressed={isPinned}
          disabled={pending}
          onClick={() => updateModeration({ is_pinned: !isPinned })}
        >
          <PinIcon />
        </ModerationIconButton>
        <ModerationIconButton
          label={isLocked ? t("forum.unlockTopic") : t("forum.lockTopic")}
          pressed={isLocked}
          disabled={pending}
          onClick={() => updateModeration({ is_locked: !isLocked })}
        >
          <LockIcon locked={isLocked} />
        </ModerationIconButton>
      </div>
      {(isPinned || isLocked) && (
        <div className="flex flex-wrap gap-2">
          {isPinned ? (
            <Badge variant="primary">{t("forum.pinned")}</Badge>
          ) : null}
          {isLocked ? (
            <Badge variant="secondary">{t("forum.locked")}</Badge>
          ) : null}
        </div>
      )}
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
    </div>
  );
}
