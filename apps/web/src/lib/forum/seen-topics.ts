/*
 * „Ново“ since the last visit: for topics this browser has opened, the
 * latest activity it saw then. A list marks a topic whose activity is newer;
 * the thread marks replies published after the last visit.
 *
 * Only in this device's localStorage, never sent anywhere. The topic is kept
 * as a short hash of its path (FNV-1a), not its title or address, so the
 * entries do not spell out which health topics were read. At most
 * MAX_ENTRIES, each dropped after RETENTION_DAYS. Every access is guarded.
 */

export const SEEN_TOPICS_KEY = "z360:forum-seen:v1";

export const MAX_ENTRIES = 300;

export const RETENTION_DAYS = 90;

const DAY_MS = 24 * 60 * 60 * 1000;

type StorageLike = Pick<Storage, "getItem" | "setItem" | "removeItem">;

/** Hash → activity seen (epoch ms). */
type Seen = Record<string, number>;

function defaultStorage(): StorageLike | null {
  try {
    return typeof window === "undefined" ? null : window.localStorage;
  } catch {
    return null;
  }
}

/** FNV-1a (32-bit) as base-36: short, stable, not reversible to the path. */
export function topicHash(path: string): string {
  let hash = 0x811c9dc5;

  for (let i = 0; i < path.length; i++) {
    hash ^= path.charCodeAt(i);
    hash = Math.imul(hash, 0x01000193) >>> 0;
  }

  return hash.toString(36);
}

function read(storage: StorageLike, now: number): Seen {
  try {
    const raw = storage.getItem(SEEN_TOPICS_KEY);
    const parsed: unknown = raw ? JSON.parse(raw) : {};

    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
      return {};
    }

    const seen: Seen = {};

    for (const [key, value] of Object.entries(parsed)) {
      if (
        typeof value === "number" &&
        Number.isFinite(value) &&
        now - value < RETENTION_DAYS * DAY_MS
      ) {
        seen[key] = value;
      }
    }

    return seen;
  } catch {
    return {};
  }
}

function parseTime(value: string | null | undefined): number | null {
  if (!value) {
    return null;
  }

  const time = Date.parse(value);

  return Number.isFinite(time) ? time : null;
}

/** The activity this browser saw on its last visit, or null (never opened). */
export function lastSeenActivity(
  path: string,
  storage: StorageLike | null = defaultStorage(),
  now: number = Date.now(),
): number | null {
  if (!storage) {
    return null;
  }

  return read(storage, now)[topicHash(path)] ?? null;
}

/**
 * Whether a topic opened before has activity since: a topic never opened is
 * not „new“ (every topic would be).
 */
export function hasNewActivity(
  path: string,
  lastActivityAt: string | null | undefined,
  storage: StorageLike | null = defaultStorage(),
  now: number = Date.now(),
): boolean {
  const seen = lastSeenActivity(path, storage, now);
  const activity = parseTime(lastActivityAt);

  return seen !== null && activity !== null && activity > seen;
}

/** Remembers the topic's current activity as seen. */
export function markTopicSeen(
  path: string,
  lastActivityAt: string | null | undefined,
  storage: StorageLike | null = defaultStorage(),
  now: number = Date.now(),
): void {
  const activity = parseTime(lastActivityAt);

  if (!storage || activity === null) {
    return;
  }

  try {
    const seen = read(storage, now);
    const key = topicHash(path);
    seen[key] = Math.max(seen[key] ?? 0, activity);

    const keys = Object.keys(seen).sort((a, b) => seen[b] - seen[a]);
    for (const old of keys.slice(MAX_ENTRIES)) {
      delete seen[old];
    }

    storage.setItem(SEEN_TOPICS_KEY, JSON.stringify(seen));
  } catch {
    // Not kept: nothing is marked new next time.
  }
}
