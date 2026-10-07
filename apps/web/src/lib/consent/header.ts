/** Sent with every statistics request; the relays and the API require it. */
export const CONSENT_HEADER = "X-Z360-Consent";
export const CONSENT_HEADER_VALUE = "statistics";

/** The headers a statistics request carries. */
export function statisticsHeaders(): Record<string, string> {
  return { [CONSENT_HEADER]: CONSENT_HEADER_VALUE };
}

/** Server side: did the browser send `X-Z360-Consent: statistics`? */
export function hasConsentHeader(headers: Headers): boolean {
  return (
    headers.get(CONSENT_HEADER)?.trim().toLowerCase() === CONSENT_HEADER_VALUE
  );
}
