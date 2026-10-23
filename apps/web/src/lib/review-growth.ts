/*
 * „First reviews“ (docs/reviews-growth.md): the share link, the draft kept
 * across sign-in, and the frequency caps of the prompt shown back on the
 * search results. Everything here lives in this browser only; nothing is
 * sent to the API except the one consent-gated „started from the shared
 * link“ boolean on a review.
 *
 * Every storage access is guarded: private windows, blocked site data and
 * full quotas throw, and the features then simply stay quiet.
 */

import { REVIEW_ANCHOR } from "@/components/reviews/review-paths";
import { statisticsAllowed } from "@/lib/consent/consent";
import { profileHash } from "@/lib/review-prompt-storage";

export { REVIEW_ANCHOR };

/**
 * Fixed campaign tags (docs/campaign-links.md): identical for every sender —
 * no per-person token that could be counted, rewarded or traced.
 */
export const SHARE_UTM = {
  utm_source: "share",
  utm_medium: "referral",
  utm_campaign: "napishi-recenzija",
} as const;

export type ReviewKind = "doctor" | "facility" | "pharmacy";

type StorageLike = Pick<Storage, "getItem" | "setItem" | "removeItem">;

const HOUR_MS = 60 * 60 * 1000;
const DAY_MS = 24 * HOUR_MS;

function local(): StorageLike | null {
  try {
    return typeof window === "undefined" ? null : window.localStorage;
  } catch {
    return null;
  }
}

function session(): StorageLike | null {
  try {
    return typeof window === "undefined" ? null : window.sessionStorage;
  } catch {
    return null;
  }
}

function readJson(storage: StorageLike | null, key: string): unknown {
  if (!storage) return null;

  try {
    const raw = storage.getItem(key);
    return raw ? (JSON.parse(raw) as unknown) : null;
  } catch {
    return null;
  }
}

function writeJson(
  storage: StorageLike | null,
  key: string,
  value: unknown,
): boolean {
  if (!storage) return false;

  try {
    storage.setItem(key, JSON.stringify(value));
    return true;
  } catch {
    return false;
  }
}

function remove(storage: StorageLike | null, key: string): void {
  try {
    storage?.removeItem(key);
  } catch {
    // Nothing to do.
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

/* ------------------------------------------------------------------ share */

/**
 * The link a patient sends to someone else: the canonical profile URL, the
 * fixed campaign tags and the review fragment. `profileUrl` is absolute.
 */
export function reviewShareUrl(profileUrl: string): string {
  const url = new URL(profileUrl);

  for (const [key, value] of Object.entries(SHARE_UTM)) {
    url.searchParams.set(key, value);
  }

  url.hash = REVIEW_ANCHOR;

  return url.toString();
}

/** Whether a page was opened through the share link (by its campaign tag). */
export function isSharedReviewLanding(search: string): boolean {
  try {
    return (
      new URLSearchParams(search).get("utm_campaign") === SHARE_UTM.utm_campaign
    );
  } catch {
    return false;
  }
}

export const SHARED_LANDING_KEY = "z360:review-share:v1";

/** How long after opening the link a review still counts as „from it“. */
export const SHARED_LANDING_TTL_MS = DAY_MS;

// Until the visitor answers the cookie banner the landing is only kept in
// memory for this page view; it is written to sessionStorage once (and only
// if) statistics are allowed.
let pendingLanding: { h: string; at: number } | null = null;

/**
 * Notes that this profile was opened from the share link. Kept in this tab
 * only, and only with statistics consent (the cookie model's „Статистика“):
 * it ends up as one boolean on the review, seen by moderators.
 */
export function rememberSharedLanding(
  kind: ReviewKind,
  slug: string,
  now: number = Date.now(),
): void {
  pendingLanding = { h: profileHash(kind, slug), at: now };
  persistSharedLanding();
}

/** Called again when the consent changes during the page view. */
export function persistSharedLanding(): void {
  if (pendingLanding && statisticsAllowed()) {
    writeJson(session(), SHARED_LANDING_KEY, pendingLanding);
  }
}

/**
 * Whether a review being sent for this profile started from the share link.
 * False without statistics consent, whatever was noted.
 */
export function cameFromSharedLink(
  kind: ReviewKind,
  slug: string,
  now: number = Date.now(),
): boolean {
  if (kind !== "doctor" || !statisticsAllowed()) {
    return false;
  }

  const stored = readJson(session(), SHARED_LANDING_KEY);
  const landing = isRecord(stored) ? stored : pendingLanding;

  return (
    isRecord(landing) &&
    landing.h === profileHash(kind, slug) &&
    typeof landing.at === "number" &&
    now - landing.at < SHARED_LANDING_TTL_MS
  );
}

export function forgetSharedLanding(): void {
  pendingLanding = null;
  remove(session(), SHARED_LANDING_KEY);
}

/* ------------------------------------------------------------------ draft */

export const DRAFT_KEY = "z360:review-draft:v1";

/**
 * A draft outlives sign-in, registration and the e-mail check (which opens
 * a new tab), so it is in localStorage — but only for a day, only one at a
 * time, and it is deleted as soon as the review is sent.
 */
export const DRAFT_TTL_MS = DAY_MS;

export type ReviewDraft = {
  kind: ReviewKind;
  slug: string;
  /** The profile's name, for „Продолжете ја рецензијата за …“. */
  name: string | null;
  /** Where the form is, e.g. /doctors/ana. */
  path: string;
  rating: number | null;
  body: string;
  aspects: Record<string, number>;
  savedAt: number;
};

function validDraft(value: unknown, now: number): ReviewDraft | null {
  if (!isRecord(value)) return null;

  const { kind, slug, name, path, rating, body, aspects, savedAt } = value;

  if (
    (kind !== "doctor" && kind !== "facility" && kind !== "pharmacy") ||
    typeof slug !== "string" ||
    typeof path !== "string" ||
    !path.startsWith("/") ||
    path.startsWith("//") ||
    typeof savedAt !== "number" ||
    now - savedAt > DRAFT_TTL_MS ||
    now < savedAt
  ) {
    return null;
  }

  const cleanAspects: Record<string, number> = {};

  if (isRecord(aspects)) {
    for (const [code, value] of Object.entries(aspects)) {
      if (
        /^[a-z_]{1,40}$/.test(code) &&
        Number.isInteger(value) &&
        (value as number) >= 1 &&
        (value as number) <= 5
      ) {
        cleanAspects[code] = value as number;
      }
    }
  }

  return {
    kind,
    slug,
    name: typeof name === "string" ? name : null,
    path,
    rating:
      Number.isInteger(rating) &&
      (rating as number) >= 1 &&
      (rating as number) <= 5
        ? (rating as number)
        : null,
    body: typeof body === "string" ? body.slice(0, 2000) : "",
    aspects: cleanAspects,
    savedAt,
  };
}

export function saveReviewDraft(
  draft: Omit<ReviewDraft, "savedAt">,
  now: number = Date.now(),
): boolean {
  return writeJson(local(), DRAFT_KEY, { ...draft, savedAt: now });
}

/** The draft, if any, still fresh — for any profile. */
export function readAnyReviewDraft(
  now: number = Date.now(),
): ReviewDraft | null {
  const storage = local();
  const draft = validDraft(readJson(storage, DRAFT_KEY), now);

  if (!draft) {
    // Expired or unreadable: do not keep it around.
    remove(storage, DRAFT_KEY);
  }

  return draft;
}

/** The draft for this profile, if any. */
export function readReviewDraft(
  kind: ReviewKind,
  slug: string,
  now: number = Date.now(),
): ReviewDraft | null {
  const draft = readAnyReviewDraft(now);

  return draft && draft.kind === kind && draft.slug === slug ? draft : null;
}

export function clearReviewDraft(kind?: ReviewKind, slug?: string): void {
  const storage = local();

  if (kind && slug) {
    const draft = validDraft(readJson(storage, DRAFT_KEY), Date.now());

    if (draft && (draft.kind !== kind || draft.slug !== slug)) {
      return;
    }
  }

  remove(storage, DRAFT_KEY);
}

/* ---------------------------------------------------- prompt after search */

/** Set while the visitor is on search results (this tab only). */
export const SEARCH_VISIT_KEY = "z360:search-visit:v1";

/** The doctor last opened from the results (this tab only). */
export const SEARCH_RETURN_KEY = "z360:search-return:v1";

/** Shown this session (this tab only). */
export const SEARCH_PROMPT_SESSION_KEY = "z360:search-prompt-shown:v1";

/** Per device: when it was last shown, and the profiles never to ask about. */
export const SEARCH_PROMPT_KEY = "z360:search-prompt:v1";

/** A profile opened this long after the results still counts as „from search“. */
export const SEARCH_ORIGIN_TTL_MS = 30 * 60 * 1000;

/** At most once per this many days per device. */
export const SEARCH_PROMPT_INTERVAL_DAYS = 7;

/** Remembered „never ask about this one“ profiles. */
export const SEARCH_PROMPT_MAX_SKIPS = 200;

export function markSearchVisit(now: number = Date.now()): void {
  writeJson(session(), SEARCH_VISIT_KEY, now);
}

/**
 * On a doctor profile: if the visitor came from the results, remember this
 * doctor so the results can ask about it once they are back.
 */
export function noteProfileFromSearch(
  slug: string,
  name: string,
  now: number = Date.now(),
): void {
  const storage = session();
  const visit = readJson(storage, SEARCH_VISIT_KEY);

  if (typeof visit !== "number" || now - visit > SEARCH_ORIGIN_TTL_MS) {
    return;
  }

  writeJson(storage, SEARCH_RETURN_KEY, { slug, name, at: now });
}

export function forgetSearchReturn(): void {
  remove(session(), SEARCH_RETURN_KEY);
}

type PromptState = { last: number | null; skip: string[] };

function readPromptState(storage: StorageLike | null): PromptState {
  const value = readJson(storage, SEARCH_PROMPT_KEY);

  if (!isRecord(value)) {
    return { last: null, skip: [] };
  }

  return {
    last: typeof value.last === "number" ? value.last : null,
    skip: Array.isArray(value.skip)
      ? value.skip.filter(
          (hash): hash is string =>
            typeof hash === "string" && /^[0-9a-f]{8}$/.test(hash),
        )
      : [],
  };
}

function writePromptState(storage: StorageLike | null, state: PromptState) {
  writeJson(storage, SEARCH_PROMPT_KEY, {
    last: state.last,
    skip: state.skip.slice(-SEARCH_PROMPT_MAX_SKIPS),
  });
}

/** Never ask about this profile again on this device (dismissed or reviewed). */
export function skipSearchPrompt(kind: ReviewKind, slug: string): void {
  const storage = local();
  const state = readPromptState(storage);
  const hash = profileHash(kind, slug);

  if (!state.skip.includes(hash)) {
    writePromptState(storage, { ...state, skip: [...state.skip, hash] });
  }
}

export type SearchPromptCandidate = { slug: string; name: string };

/**
 * The doctor to ask about on the results, or null. Asks only when: the
 * visitor came back from a profile they opened from the results, nothing was
 * shown this session nor in the last SEARCH_PROMPT_INTERVAL_DAYS on this
 * device, they never dismissed or reviewed that doctor here, and the
 * profile's own prompt has not already asked (`profilePromptAllowed`).
 */
export function searchPromptCandidate(
  profilePromptAllowed: (slug: string) => boolean,
  now: number = Date.now(),
): SearchPromptCandidate | null {
  const tab = session();
  const device = local();

  if (!tab || !device) {
    return null;
  }

  const returned = readJson(tab, SEARCH_RETURN_KEY);

  if (
    !isRecord(returned) ||
    typeof returned.slug !== "string" ||
    typeof returned.name !== "string" ||
    typeof returned.at !== "number" ||
    now - returned.at > SEARCH_ORIGIN_TTL_MS
  ) {
    return null;
  }

  if (readJson(tab, SEARCH_PROMPT_SESSION_KEY) !== null) {
    return null;
  }

  const state = readPromptState(device);

  if (
    state.last !== null &&
    now - state.last < SEARCH_PROMPT_INTERVAL_DAYS * DAY_MS
  ) {
    return null;
  }

  if (state.skip.includes(profileHash("doctor", returned.slug))) {
    return null;
  }

  if (!profilePromptAllowed(returned.slug)) {
    return null;
  }

  // Without a working device storage the cap could not hold: stay quiet.
  if (!writeJson(device, `${SEARCH_PROMPT_KEY}:probe`, 1)) {
    return null;
  }
  remove(device, `${SEARCH_PROMPT_KEY}:probe`);

  return { slug: returned.slug, name: returned.name };
}

/** Counts the prompt as shown: for this session and for the weekly cap. */
export function markSearchPromptShown(now: number = Date.now()): void {
  writeJson(session(), SEARCH_PROMPT_SESSION_KEY, now);
  forgetSearchReturn();

  const device = local();
  writePromptState(device, { ...readPromptState(device), last: now });
}

/* ------------------------------------------------------------ sticky CTA */

export type StickyViewer = {
  canWrite: boolean;
  ownProfile: boolean;
  mustChooseUsername: boolean;
};

/**
 * Whether a doctor profile adds „Напишете рецензија“ to its bottom bar on
 * phones: the admin switch is on, nothing is published yet (the first
 * review is the point), and the visitor can actually write one — not the
 * doctor themself, not a member who already sent one, not a member who
 * still has to choose a username. A signed-out visitor can (`viewer` with
 * canWrite, as the form is open to them).
 */
export function showStickyReviewCta({
  enabled,
  publishedReviews,
  viewer,
}: {
  enabled: boolean;
  publishedReviews: number;
  viewer: StickyViewer | null;
}): boolean {
  return (
    enabled &&
    publishedReviews === 0 &&
    viewer !== null &&
    viewer.canWrite &&
    !viewer.ownProfile &&
    !viewer.mustChooseUsername
  );
}
