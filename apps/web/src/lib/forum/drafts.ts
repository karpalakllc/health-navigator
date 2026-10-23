/*
 * Unsent forum text kept on this device only (a new topic, a reply), so a
 * closed tab or a lost connection does not cost the member what they wrote.
 *
 * localStorage, one entry per place („reply:/forum/c/t“, „topic:new“), dropped
 * after RETENTION_DAYS or when sent. Never sent to the API. Every storage
 * access is guarded: private windows and blocked site data simply mean no
 * draft is kept.
 */

export const DRAFTS_KEY = "z360:forum-drafts:v1";

export const MAX_DRAFTS = 20;

export const RETENTION_DAYS = 14;

const DAY_MS = 24 * 60 * 60 * 1000;

/** The API's longest field (a topic or reply body). */
const MAX_LENGTH = 10000;

type StorageLike = Pick<Storage, "getItem" | "setItem" | "removeItem">;

export type ForumDraft = Record<string, string>;

type Entry = { fields: ForumDraft; savedAt: number };

function defaultStorage(): StorageLike | null {
  try {
    return typeof window === "undefined" ? null : window.localStorage;
  } catch {
    return null;
  }
}

function readAll(storage: StorageLike, now: number): Record<string, Entry> {
  try {
    const raw = storage.getItem(DRAFTS_KEY);
    const parsed: unknown = raw ? JSON.parse(raw) : {};

    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
      return {};
    }

    const result: Record<string, Entry> = {};

    for (const [key, value] of Object.entries(parsed)) {
      const entry = value as Partial<Entry> | null;

      if (
        !entry ||
        typeof entry.savedAt !== "number" ||
        now - entry.savedAt > RETENTION_DAYS * DAY_MS ||
        !entry.fields ||
        typeof entry.fields !== "object"
      ) {
        continue;
      }

      const fields: ForumDraft = {};

      for (const [name, text] of Object.entries(entry.fields)) {
        if (typeof text === "string") {
          fields[name] = text.slice(0, MAX_LENGTH);
        }
      }

      result[key] = { fields, savedAt: entry.savedAt };
    }

    return result;
  } catch {
    return {};
  }
}

function writeAll(storage: StorageLike, all: Record<string, Entry>): void {
  try {
    const keys = Object.keys(all);

    if (keys.length === 0) {
      storage.removeItem(DRAFTS_KEY);
      return;
    }

    storage.setItem(DRAFTS_KEY, JSON.stringify(all));
  } catch {
    // Full quota or blocked storage: the draft is simply not kept.
  }
}

/** The saved draft for this place, or null. */
export function loadDraft(
  scope: string,
  storage: StorageLike | null = defaultStorage(),
  now: number = Date.now(),
): ForumDraft | null {
  if (!storage) {
    return null;
  }

  const entry = readAll(storage, now)[scope];

  return entry && Object.values(entry.fields).some((text) => text.trim())
    ? entry.fields
    : null;
}

/** Saves the fields; all-empty fields remove the draft. */
export function saveDraft(
  scope: string,
  fields: ForumDraft,
  storage: StorageLike | null = defaultStorage(),
  now: number = Date.now(),
): void {
  if (!storage) {
    return;
  }

  const all = readAll(storage, now);

  if (!Object.values(fields).some((text) => text.trim())) {
    delete all[scope];
  } else {
    all[scope] = { fields, savedAt: now };

    // The oldest go first once there are too many.
    const keys = Object.keys(all).sort(
      (a, b) => all[b].savedAt - all[a].savedAt,
    );
    for (const key of keys.slice(MAX_DRAFTS)) {
      delete all[key];
    }
  }

  writeAll(storage, all);
}

export function clearDraft(
  scope: string,
  storage: StorageLike | null = defaultStorage(),
  now: number = Date.now(),
): void {
  saveDraft(scope, {}, storage, now);
}
