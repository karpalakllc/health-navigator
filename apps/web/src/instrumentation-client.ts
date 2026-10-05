/*
 * Next 16 loads browser instrumentation from `instrumentation-client.ts`.
 * This lived in `sentry.client.config.ts`, which nothing imported — so
 * client-side errors were never reported and the Sentry dashboard looked
 * quiet because nothing was being sent, not because nothing was breaking.
 */
import * as Sentry from "@sentry/nextjs";
import { scrubEvent } from "@/lib/sentry-scrub";

const dsn = process.env.NEXT_PUBLIC_SENTRY_DSN;

if (dsn) {
  Sentry.init({
    dsn,
    environment:
      process.env.NEXT_PUBLIC_SENTRY_ENVIRONMENT ?? process.env.NODE_ENV,
    tracesSampleRate: 0,
    sendDefaultPii: false,
    // Filters credentials from request bodies AND from URLs: the request URL,
    // query string, Referer and navigation/fetch breadcrumbs.
    beforeSend: (event) => scrubEvent(event),
  });
}
