import * as Sentry from "@sentry/nextjs";
import { beforeSendFilter } from "@/lib/sentry-scrub";

const dsn = process.env.SENTRY_DSN ?? process.env.NEXT_PUBLIC_SENTRY_DSN;

if (dsn) {
  Sentry.init({
    dsn,
    environment: process.env.SENTRY_ENVIRONMENT ?? process.env.NODE_ENV,
    tracesSampleRate: 0,
    sendDefaultPii: false,
    // Same filtering as the browser: query strings dropped from URLs, the
    // Referer and breadcrumbs; credential keys filtered from bodies.
    // Also drops the per-view SettingsUnavailableError (see beforeSendFilter).
    beforeSend: (event, hint) => beforeSendFilter(event, hint),
  });
}
