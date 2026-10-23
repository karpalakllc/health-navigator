"use client";

import { useEffect, useSyncExternalStore } from "react";
import { Tag } from "@/components/ui/tag";
import {
  hasNewActivity,
  lastSeenActivity,
  markTopicSeen,
} from "@/lib/forum/seen-topics";
import { t } from "@/i18n/t";

/*
 * „Ново“ since the last visit (lib/forum/seen-topics): device-only markers.
 * Nothing renders on the server or before hydration, so the markup never
 * depends on storage.
 */

/** Per open topic: the activity seen on the previous visit (this page view). */
const baselines = new Map<string, number | null>();
const listeners = new Set<() => void>();

function emit() {
  for (const listener of listeners) {
    listener();
  }
}

function subscribe(listener: () => void) {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

const noop = () => () => {};

/**
 * Rendered once on the thread: remembers what the previous visit saw (for
 * the replies' markers), then records this visit.
 */
export function ForumTopicVisit({
  path,
  lastActivityAt,
}: {
  path: string;
  lastActivityAt: string | null;
}) {
  useEffect(() => {
    baselines.set(path, lastSeenActivity(path));
    markTopicSeen(path, lastActivityAt);
    emit();

    return () => {
      baselines.delete(path);
      emit();
    };
  }, [path, lastActivityAt]);

  return null;
}

/** „Ново“ on a reply published after the previous visit to its topic. */
export function ForumNewReplyTag({
  topicPath,
  publishedAt,
}: {
  topicPath: string;
  publishedAt: string | null;
}) {
  const baseline = useSyncExternalStore(
    subscribe,
    () => baselines.get(topicPath) ?? null,
    () => null,
  );
  const published = publishedAt ? Date.parse(publishedAt) : NaN;

  if (
    baseline === null ||
    !Number.isFinite(published) ||
    published <= baseline
  ) {
    return null;
  }

  return (
    <Tag tone="ink">
      {t("forum.newSinceVisit")}
      <span className="sr-only">: {t("forum.newSinceVisitAria")}</span>
    </Tag>
  );
}

/** „Ново“ on a topic row: opened here before, active since. */
export function ForumNewActivityTag({
  path,
  lastActivityAt,
  className,
}: {
  path: string;
  lastActivityAt: string | null;
  className?: string;
}) {
  const isNew = useSyncExternalStore(
    noop,
    () => hasNewActivity(path, lastActivityAt),
    () => false,
  );

  if (!isNew) {
    return null;
  }

  return (
    <Tag tone="ink" className={className}>
      {t("forum.newSinceVisit")}
      <span className="sr-only">: {t("forum.newSinceVisitAria")}</span>
    </Tag>
  );
}
