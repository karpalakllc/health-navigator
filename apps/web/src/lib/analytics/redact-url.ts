/**
 * What Plausible may see of a page address.
 *
 * Query strings here carry what people searched for (`/search?q=…`), and on a
 * health platform that is often a symptom; filters (`?city=…&specialty=…`) are
 * not much better. So only origin + path are reported, plus the parameters
 * listed here. Add one only after checking it can never carry free text or
 * anything about the visitor.
 *
 * The four campaign tags are the exception we make: they are written by
 * whoever shares a link („viber“, „newsletter“, „esen-2026“), not typed by a
 * visitor, and they let the owner see which posts and mails bring people in
 * (Plausible's „Campaigns“ report). `utm_term` is left out on purpose: it is
 * meant for search keywords, i.e. what someone looked for. Even the allowed
 * tags pass only as short slugs (see isSafeCampaignValue), so a hand-edited
 * link cannot smuggle a sentence, an e-mail address or a phone number through.
 */
export const ANALYTICS_ALLOWED_PARAMS: readonly string[] = [
  "utm_source",
  "utm_medium",
  "utm_campaign",
  "utm_content",
];

const MAX_CAMPAIGN_VALUE_LENGTH = 64;

/**
 * A campaign tag value worth keeping: 1–64 letters (any script), digits, `.`,
 * `_` or `-`, and no run of six or more digits (a phone number, an ID).
 * Anything else — spaces, `@`, `+`, `/`, `%`… — drops the parameter.
 */
export function isSafeCampaignValue(value: string): boolean {
  return (
    value.length > 0 &&
    value.length <= MAX_CAMPAIGN_VALUE_LENGTH &&
    /^[\p{L}\p{N}._-]+$/u.test(value) &&
    !/\d{6,}/.test(value)
  );
}

/**
 * Pages that send no pageview at all. The unsubscribe page is reached only
 * from a member's e-mail, so even its bare path would say „a member opened
 * an opt-out link“ — nothing the statistics need.
 */
export const ANALYTICS_EXCLUDED_PATHS: readonly string[] = ["/unsubscribe"];

export function isAnalyticsExcludedPath(pathname: string): boolean {
  const path =
    pathname.length > 1 && pathname.endsWith("/")
      ? pathname.slice(0, -1)
      : pathname;

  return ANALYTICS_EXCLUDED_PATHS.includes(path);
}

export function redactPageUrl(
  href: string,
  allowedParams: readonly string[] = ANALYTICS_ALLOWED_PARAMS,
): string {
  let url: URL;

  try {
    url = new URL(href);
  } catch {
    // Not an absolute URL: report nothing rather than something unredacted.
    return "";
  }

  const kept = new URLSearchParams();

  for (const name of allowedParams) {
    for (const value of url.searchParams.getAll(name)) {
      if (isSafeCampaignValue(value)) {
        kept.append(name, value);
      }
    }
  }

  const query = kept.toString();

  // The fragment is dropped too: it never reaches a server, so a link could put
  // anything in it.
  return `${url.origin}${url.pathname}${query ? `?${query}` : ""}`;
}

/**
 * Only Plausible's "manual" script variants leave pageviews to us. Any other
 * build reports `location.href` — query string included — by itself on load
 * and on every client-side navigation, so it is refused rather than loaded.
 */
export function isManualPlausibleScript(scriptUrl: string): boolean {
  let pathname: string;

  try {
    pathname = new URL(scriptUrl).pathname;
  } catch {
    return false;
  }

  const file = pathname.split("/").pop() ?? "";

  return (
    /^script(\.[a-z-]+)*\.js$/.test(file) && file.split(".").includes("manual")
  );
}
