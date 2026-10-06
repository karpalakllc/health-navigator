/**
 * What Plausible may see of a page address.
 *
 * Query strings here carry what people searched for (`/search?q=…`), and on a
 * health platform that is often a symptom; filters (`?city=…&specialty=…`) are
 * not much better. So only origin + path are reported, plus any parameter
 * explicitly listed as harmless. None is, by default — add one only after
 * checking it can never carry free text or anything about the visitor.
 */
export const ANALYTICS_ALLOWED_PARAMS: readonly string[] = [];

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
      kept.append(name, value);
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
