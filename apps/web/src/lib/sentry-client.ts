/**
 * Browser error reporting, loaded off the critical path.
 *
 * `import * as Sentry from "@sentry/nextjs"` in instrumentation-client.ts and
 * the error boundaries put the whole SDK (~150 KB of the largest shared chunk)
 * into every page's first load — even with no DSN configured, when it does
 * nothing at all. Now the SDK is a separate chunk, fetched only when a DSN is
 * set, after the page has loaded (or at once if an error needs reporting).
 * Errors thrown before it arrives are buffered and sent when it does.
 */
type SentryModule = typeof import("@sentry/nextjs");

const dsn = process.env.NEXT_PUBLIC_SENTRY_DSN;

let loading: Promise<SentryModule> | null = null;

export function sentryEnabled(): boolean {
  return Boolean(dsn);
}

/** Loads and initialises the SDK once; null without a DSN. */
export function loadSentry(): Promise<SentryModule> | null {
  if (!dsn) {
    return null;
  }

  loading ??= Promise.all([
    import("@sentry/nextjs"),
    import("@/lib/sentry-scrub"),
  ]).then(([Sentry, { beforeSendFilter }]) => {
    Sentry.init({
      dsn,
      environment:
        process.env.NEXT_PUBLIC_SENTRY_ENVIRONMENT ?? process.env.NODE_ENV,
      tracesSampleRate: 0,
      sendDefaultPii: false,
      // Drops query strings (reset tokens, search terms) from the request
      // URL, query string, Referer and navigation/fetch breadcrumbs, and
      // credential keys from request bodies. Also drops the per-view
      // SettingsUnavailableError (see beforeSendFilter).
      beforeSend: (event, hint) => beforeSendFilter(event, hint),
    });

    return Sentry;
  });

  return loading;
}

/** Reports an exception (loading the SDK if needed); a no-op without a DSN. */
export function reportException(error: unknown): void {
  void loadSentry()
    ?.then((Sentry) => Sentry.captureException(error))
    .catch(() => {
      // Reporting must never become an error of its own.
    });
}
