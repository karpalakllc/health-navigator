/*
 * Cookie and statistics consent. Statistics (the anonymous UX tracker,
 * Plausible pageviews, review view counts, guidance step counters) run only
 * after the visitor accepted them in the banner. The choice lives in this
 * browser alone (localStorage); nothing about it is sent anywhere.
 *
 * Bump CONSENT_VERSION when the categories or their meaning change: everyone
 * is asked again.
 */

export const CONSENT_KEY = "z360:consent:v1";
export const CONSENT_VERSION = 1;

const CHANGE_EVENT = "z360:consent-change";
const OPEN_EVENT = "z360:consent-open";

export type Consent = {
  statistics: boolean;
  decidedAt: string;
  version: number;
};

// Only set when localStorage is unavailable, so the banner still closes and a
// „no“ still holds for the rest of this page view.
let memory: Consent | null = null;

export function readRawConsent(): string | null {
  const fallback = memory ? JSON.stringify(memory) : null;

  try {
    return window.localStorage.getItem(CONSENT_KEY) ?? fallback;
  } catch {
    return fallback;
  }
}

export function parseConsent(raw: string | null): Consent | null {
  if (!raw) return null;

  try {
    const value = JSON.parse(raw) as Partial<Consent> | null;

    if (
      value &&
      typeof value.statistics === "boolean" &&
      typeof value.decidedAt === "string" &&
      value.version === CONSENT_VERSION
    ) {
      return value as Consent;
    }
  } catch {
    // Treated as no decision.
  }

  return null;
}

/** The stored decision, or null when there is none (or it is for an older version). */
export function readConsent(): Consent | null {
  if (typeof window === "undefined") return null;

  return parseConsent(readRawConsent());
}

/** Live check, made at the moment of sending: true only after an explicit yes. */
export function statisticsAllowed(): boolean {
  return readConsent()?.statistics === true;
}

export function saveConsent(statistics: boolean): void {
  const consent: Consent = {
    statistics,
    decidedAt: new Date().toISOString(),
    version: CONSENT_VERSION,
  };

  try {
    window.localStorage.setItem(CONSENT_KEY, JSON.stringify(consent));
    memory = null;
  } catch {
    memory = consent;
  }

  window.dispatchEvent(new Event(CHANGE_EVENT));
}

export function subscribeConsent(notify: () => void): () => void {
  window.addEventListener(CHANGE_EVENT, notify);
  window.addEventListener("storage", notify);

  return () => {
    window.removeEventListener(CHANGE_EVENT, notify);
    window.removeEventListener("storage", notify);
  };
}

/** Reopens the banner (footer link „Поставки за колачиња“). */
export function openConsentSettings(): void {
  window.dispatchEvent(new Event(OPEN_EVENT));
}

export function onConsentSettingsOpen(listener: () => void): () => void {
  window.addEventListener(OPEN_EVENT, listener);

  return () => window.removeEventListener(OPEN_EVENT, listener);
}
