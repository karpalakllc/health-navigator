/*
 * Next 16 loads browser instrumentation from `instrumentation-client.ts`.
 * This lived in `sentry.client.config.ts`, which nothing imported — so
 * client-side errors were never reported and the Sentry dashboard looked
 * quiet because nothing was being sent, not because nothing was breaking.
 */
import * as Sentry from "@sentry/nextjs";

const dsn = process.env.NEXT_PUBLIC_SENTRY_DSN;

if (dsn) {
  Sentry.init({
    dsn,
    environment:
      process.env.NEXT_PUBLIC_SENTRY_ENVIRONMENT ?? process.env.NODE_ENV,
    tracesSampleRate: 0,
    sendDefaultPii: false,
    beforeSend(event) {
      if (event.request?.data && typeof event.request.data === "object") {
        const data = { ...event.request.data } as Record<string, unknown>;
        for (const key of ["password", "token", "email"]) {
          if (key in data) {
            data[key] = "[Filtered]";
          }
        }
        event.request.data = data;
      }

      return event;
    },
  });
}
