import { WEB_URL } from "./env";

/**
 * The cookie banner's stored choice (src/lib/consent/consent.ts), pre-set so
 * the banner never appears and never covers a click. The default for every
 * spec is "decided, statistics off" (playwright.config.ts); specs that test
 * statistics opt in with `test.use({ storageState: consentState(true) })`, and
 * consent.spec.ts starts with nothing stored to exercise the real banner.
 */
export const CONSENT_KEY = "z360:consent:v1";
export const CONSENT_VERSION = 1;

export function consentState(statistics: boolean) {
  return {
    cookies: [],
    origins: [
      {
        origin: WEB_URL,
        localStorage: [
          {
            name: CONSENT_KEY,
            value: JSON.stringify({
              statistics,
              decidedAt: "2026-01-01T00:00:00.000Z",
              version: CONSENT_VERSION,
            }),
          },
        ],
      },
    ],
  };
}

export const noConsentState = { cookies: [], origins: [] };
