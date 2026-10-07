import Script from "next/script";
import { StatisticsOnly } from "@/components/layout/statistics-only";
import { PlausiblePageviews } from "@/components/layout/plausible-pageviews";
import { isManualPlausibleScript } from "@/lib/analytics/redact-url";

/**
 * Plausible in manual mode: the script never reports a pageview itself, so a
 * query string (`?q=` searches, filters) can never reach it. Pageviews are sent
 * by PlausiblePageviews with the address cut down to origin + path. Both the
 * script and the pageviews wait for the statistics consent (StatisticsOnly).
 */
export function PlausibleAnalytics() {
  const domain = process.env.NEXT_PUBLIC_PLAUSIBLE_DOMAIN;

  if (!domain) {
    return null;
  }

  const scriptUrl =
    process.env.NEXT_PUBLIC_PLAUSIBLE_SCRIPT_URL ??
    "https://plausible.io/js/script.manual.js";

  // A non-manual build would report full URLs on its own. Refuse it rather than
  // silently undo the redaction.
  if (!isManualPlausibleScript(scriptUrl)) {
    if (process.env.NODE_ENV !== "production") {
      console.warn(
        "NEXT_PUBLIC_PLAUSIBLE_SCRIPT_URL must point at a manual script (script.manual.js); analytics disabled.",
      );
    }

    return null;
  }

  // Nothing loads before the visitor accepted statistics; withdrawing removes
  // both at once (no further pageview is sent).
  return (
    <StatisticsOnly>
      <Script
        defer
        data-domain={domain}
        src={scriptUrl}
        strategy="afterInteractive"
      />
      <PlausiblePageviews />
    </StatisticsOnly>
  );
}
