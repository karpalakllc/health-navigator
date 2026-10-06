/*
 * „Последно прегледани“: the profiles this browser opened most recently.
 *
 * Kept only in this device's localStorage — never sent to the API, never tied
 * to an account. Only what the home list needs to draw a card is stored (kind,
 * slug, name, a subtitle, the avatar URL and when it was viewed); nothing
 * about what the visitor did on the page. Every storage access is guarded:
 * private windows, blocked site data and full quotas throw, and the feature
 * then simply shows nothing.
 */

export type RecentlyViewedKind = "doctor" | "facility" | "pharmacy";

export type RecentlyViewedEntry = {
  kind: RecentlyViewedKind;
  slug: string;
  name: string;
  subtitle: string | null;
  avatarUrl: string | null;
  /** Epoch milliseconds. */
  viewedAt: number;
};

/** Versioned: a later shape change bumps it rather than misreading old data. */
export const RECENTLY_VIEWED_KEY = "z360:recently-viewed:v1";

export const RECENTLY_VIEWED_LIMIT = 8;

/** Fired on window after this tab changes the list (the storage event only reaches other tabs). */
export const RECENTLY_VIEWED_EVENT = "z360:recently-viewed";

const KINDS: ReadonlySet<string> = new Set(["doctor", "facility", "pharmacy"]);

const MAX_TEXT = 200;

type StorageLike = Pick<Storage, "getItem" | "setItem" | "removeItem">;

const EMPTY: readonly RecentlyViewedEntry[] = Object.freeze([]);

function defaultStorage(): StorageLike | null {
  try {
    return typeof window === "undefined" ? null : window.localStorage;
  } catch {
    // Accessing localStorage itself throws when site data is blocked.
    return null;
  }
}

function text(value: unknown): string | null {
  if (typeof value !== "string") {
    return null;
  }
  const trimmed = value.trim();

  return trimmed === "" ? null : trimmed.slice(0, MAX_TEXT);
}

/** Only same-site paths and http(s) URLs: the value ends up in an <img src>. */
function avatar(value: unknown): string | null {
  const url = text(value);
  if (!url) {
    return null;
  }

  return /^https?:\/\//i.test(url) ||
    (url.startsWith("/") && !url.startsWith("//"))
    ? url
    : null;
}

/**
 * Validates one stored item. Storage is user-editable, so nothing read back is
 * trusted: the slug becomes part of a link, the avatar an image URL.
 */
export function sanitizeEntry(raw: unknown): RecentlyViewedEntry | null {
  if (raw === null || typeof raw !== "object") {
    return null;
  }
  const item = raw as Record<string, unknown>;
  const kind =
    typeof item.kind === "string" && KINDS.has(item.kind) ? item.kind : null;
  const slug = text(item.slug);
  const name = text(item.name);

  if (!kind || !slug || !name || slug.includes("/")) {
    return null;
  }

  return {
    kind: kind as RecentlyViewedKind,
    slug,
    name,
    subtitle: text(item.subtitle),
    avatarUrl: avatar(item.avatarUrl),
    viewedAt:
      typeof item.viewedAt === "number" && Number.isFinite(item.viewedAt)
        ? item.viewedAt
        : 0,
  };
}

function parse(serialized: string | null): RecentlyViewedEntry[] {
  if (!serialized) {
    return [];
  }
  try {
    const value: unknown = JSON.parse(serialized);
    if (!Array.isArray(value)) {
      return [];
    }

    return value
      .map(sanitizeEntry)
      .filter((entry): entry is RecentlyViewedEntry => entry !== null)
      .slice(0, RECENTLY_VIEWED_LIMIT);
  } catch {
    return [];
  }
}

function readRaw(storage: StorageLike | null): string | null {
  if (!storage) {
    return null;
  }
  try {
    return storage.getItem(RECENTLY_VIEWED_KEY);
  } catch {
    return null;
  }
}

export function readRecentlyViewed(
  storage: StorageLike | null = defaultStorage(),
): RecentlyViewedEntry[] {
  return parse(readRaw(storage));
}

function notify() {
  if (typeof window !== "undefined") {
    window.dispatchEvent(new Event(RECENTLY_VIEWED_EVENT));
  }
}

/**
 * Puts a profile first, dropping an older visit to the same one and anything
 * past the limit. Returns false when the browser would not store it.
 */
export function recordRecentlyViewed(
  entry: Omit<RecentlyViewedEntry, "viewedAt"> & { viewedAt?: number },
  storage: StorageLike | null = defaultStorage(),
): boolean {
  const clean = sanitizeEntry({
    ...entry,
    viewedAt: entry.viewedAt ?? Date.now(),
  });
  if (!clean || !storage) {
    return false;
  }

  const next = [
    clean,
    ...readRecentlyViewed(storage).filter(
      (item) => !(item.kind === clean.kind && item.slug === clean.slug),
    ),
  ].slice(0, RECENTLY_VIEWED_LIMIT);

  try {
    storage.setItem(RECENTLY_VIEWED_KEY, JSON.stringify(next));
  } catch {
    // Quota or blocked storage: the list just does not grow.
    return false;
  }
  notify();

  return true;
}

export function clearRecentlyViewed(
  storage: StorageLike | null = defaultStorage(),
): void {
  try {
    storage?.removeItem(RECENTLY_VIEWED_KEY);
  } catch {
    // Nothing to do: the next read fails the same way and shows nothing.
  }
  notify();
}

/** The public profile path for a stored entry. */
export function recentlyViewedPath(entry: RecentlyViewedEntry): string {
  const base =
    entry.kind === "doctor"
      ? "/doctors"
      : entry.kind === "pharmacy"
        ? "/pharmacies"
        : "/facilities";

  return `${base}/${encodeURIComponent(entry.slug)}`;
}

/*
 * useSyncExternalStore plumbing. The snapshot is cached by the raw string so
 * React gets the same array until the stored value really changes; the server
 * (and the hydration pass) always sees the empty list.
 */
let cachedRaw: string | null = null;
let cachedEntries: readonly RecentlyViewedEntry[] = EMPTY;

export function getRecentlyViewedSnapshot(): readonly RecentlyViewedEntry[] {
  const raw = readRaw(defaultStorage());
  if (raw !== cachedRaw) {
    cachedRaw = raw;
    cachedEntries = raw ? parse(raw) : EMPTY;
  }

  return cachedEntries;
}

export function getRecentlyViewedServerSnapshot(): readonly RecentlyViewedEntry[] {
  return EMPTY;
}

export function subscribeRecentlyViewed(onChange: () => void): () => void {
  if (typeof window === "undefined") {
    return () => {};
  }
  const onStorage = (event: StorageEvent) => {
    if (event.key === null || event.key === RECENTLY_VIEWED_KEY) {
      onChange();
    }
  };
  window.addEventListener(RECENTLY_VIEWED_EVENT, onChange);
  window.addEventListener("storage", onStorage);

  return () => {
    window.removeEventListener(RECENTLY_VIEWED_EVENT, onChange);
    window.removeEventListener("storage", onStorage);
  };
}
