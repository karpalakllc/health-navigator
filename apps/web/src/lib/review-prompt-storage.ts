/*
 * „Дали сте биле кај…?“ is shown at most once per profile per device (W8-B).
 *
 * What is kept, only in this browser's localStorage: a short hash of the
 * profile (FNV-1a of „kind:slug“ — not its name or address) and the day it
 * was shown. At most MAX_ENTRIES, each dropped after RETENTION_DAYS. Never
 * sent to the API, never tied to an account. Every storage access is
 * guarded; when storage is unavailable the prompt simply never shows (a
 * prompt we could not remember would come back on every visit).
 */

export const REVIEW_PROMPT_KEY = "z360:review-prompt:v1";

export const MAX_ENTRIES = 200;

export const RETENTION_DAYS = 180;

const DAY_MS = 24 * 60 * 60 * 1000;

type StorageLike = Pick<Storage, "getItem" | "setItem" | "removeItem">;

/** Hash → epoch day it was shown. */
type Seen = Record<string, number>;

function defaultStorage(): StorageLike | null {
  try {
    return typeof window === "undefined" ? null : window.localStorage;
  } catch {
    return null;
  }
}

/** 32-bit FNV-1a, hex: enough to tell profiles apart, not to read them back. */
export function profileHash(kind: string, slug: string): string {
  let hash = 0x811c9dc5;
  const input = `${kind}:${slug}`;

  for (let index = 0; index < input.length; index++) {
    hash ^= input.charCodeAt(index);
    hash = Math.imul(hash, 0x01000193) >>> 0;
  }

  return hash.toString(16).padStart(8, "0");
}

function today(now: number): number {
  return Math.floor(now / DAY_MS);
}

function read(storage: StorageLike, now: number): Seen {
  let parsed: unknown;

  try {
    parsed = JSON.parse(storage.getItem(REVIEW_PROMPT_KEY) ?? "{}");
  } catch {
    return {};
  }

  if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
    return {};
  }

  const oldest = today(now) - RETENTION_DAYS;
  const seen: Seen = {};

  for (const [hash, day] of Object.entries(parsed)) {
    if (
      /^[0-9a-f]{8}$/.test(hash) &&
      typeof day === "number" &&
      day >= oldest
    ) {
      seen[hash] = day;
    }
  }

  return seen;
}

/**
 * Whether the prompt may show for this profile: storage works and it has not
 * been shown here (within the retention window).
 */
export function canShowReviewPrompt(
  kind: string,
  slug: string,
  storage: StorageLike | null = defaultStorage(),
  now: number = Date.now(),
): boolean {
  if (!storage) {
    return false;
  }

  try {
    // A probe write (removed at once): a full or read-only storage could
    // not remember the prompt. Nothing stays unless the prompt is shown.
    storage.setItem(`${REVIEW_PROMPT_KEY}:probe`, "1");
    storage.removeItem(`${REVIEW_PROMPT_KEY}:probe`);
  } catch {
    return false;
  }

  return !(profileHash(kind, slug) in read(storage, now));
}

/** Records that the prompt was shown, keeping the newest MAX_ENTRIES. */
export function markReviewPromptShown(
  kind: string,
  slug: string,
  storage: StorageLike | null = defaultStorage(),
  now: number = Date.now(),
): void {
  if (!storage) {
    return;
  }

  const seen = read(storage, now);
  seen[profileHash(kind, slug)] = today(now);

  const kept = Object.entries(seen)
    .sort((a, b) => b[1] - a[1])
    .slice(0, MAX_ENTRIES);

  try {
    storage.setItem(
      REVIEW_PROMPT_KEY,
      JSON.stringify(Object.fromEntries(kept)),
    );
  } catch {
    // Nothing to do: the prompt just may show again.
  }
}
