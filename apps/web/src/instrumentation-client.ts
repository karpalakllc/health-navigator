/*
 * Next 16 loads browser instrumentation from `instrumentation-client.ts`.
 * This lived in `sentry.client.config.ts`, which nothing imported — so
 * client-side errors were never reported and the Sentry dashboard looked
 * quiet because nothing was being sent, not because nothing was breaking.
 *
 * The SDK itself is loaded lazily (lib/sentry-client.ts) so it is not part of
 * every page's first-load JavaScript. Until it arrives, uncaught errors and
 * rejections are kept here and reported once it has.
 */
import {
  loadSentry,
  reportException,
  sentryEnabled,
} from "@/lib/sentry-client";

if (sentryEnabled() && typeof window !== "undefined") {
  const early: unknown[] = [];
  const onError = (event: ErrorEvent) => early.push(event.error ?? event);
  const onRejection = (event: PromiseRejectionEvent) =>
    early.push(event.reason);

  window.addEventListener("error", onError);
  window.addEventListener("unhandledrejection", onRejection);

  const start = () => {
    void loadSentry()
      ?.then(() => {
        // The SDK installs its own global handlers from here on.
        window.removeEventListener("error", onError);
        window.removeEventListener("unhandledrejection", onRejection);
        early.splice(0).forEach(reportException);
      })
      .catch(() => {});
  };

  if (document.readyState === "complete") {
    start();
  } else {
    window.addEventListener("load", start, { once: true });
  }
}

/*
 * Next calls this on every client navigation. With tracing off
 * (tracesSampleRate 0) the SDK does nothing with it, but exporting the hook
 * satisfies Sentry's build check without putting the SDK into the first-load
 * bundle: it only forwards once a DSN is set, loading the lazy chunk on demand.
 */
export const onRouterTransitionStart = (
  href: string,
  navigationType: "push" | "replace" | "traverse",
): void => {
  void loadSentry()
    ?.then((Sentry) =>
      Sentry.captureRouterTransitionStart(href, navigationType),
    )
    .catch(() => {});
};
