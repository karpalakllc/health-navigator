/**
 * Strips credentials and search terms from Sentry events before they leave the
 * process.
 *
 * Sentry records URLs in several places — the request itself, its query
 * string, the Referer header, and every navigation and fetch breadcrumb — and
 * the query string is where this site's sensitive values live: a password-reset
 * link carries `?token=…&email=…`, and a directory or search URL carries what
 * the visitor typed (`?q=кардиолог`, a city, a specialty) — health intent.
 * Query strings are therefore dropped wholesale (origin and path are kept, which
 * is enough to locate a bug), and request bodies lose credential keys. Shared
 * by the browser and server configs.
 */

import { isSettingsUnavailable } from "@/lib/api/public-settings";

const FILTERED = "[Filtered]";
const SENSITIVE_KEYS = ["password", "password_confirmation", "token", "email"];

function isSensitive(key: string): boolean {
  return SENSITIVE_KEYS.includes(key.toLowerCase());
}

/**
 * Drops the query string and fragment, keeping origin and path. A relative URL
 * stays relative; an absolute one also loses any user:password@.
 */
export function scrubUrl(url: string): string {
  const path = url.split(/[?#]/, 1)[0];

  try {
    const parsed = new URL(path);
    return parsed.origin === "null"
      ? path
      : `${parsed.origin}${parsed.pathname}`;
  } catch {
    return path;
  }
}

type QueryParams = string | Record<string, string> | Array<[string, string]>;

type ScrubbableEvent = {
  request?: {
    url?: string;
    query_string?: QueryParams;
    data?: unknown;
    headers?: Record<string, string>;
  };
  breadcrumbs?: Array<{ data?: Record<string, unknown> }>;
};

/** Breadcrumb fields that hold a URL: navigation (`from`/`to`), fetch/xhr (`url`). */
const BREADCRUMB_URL_KEYS = ["url", "from", "to"];

export function scrubEvent<T extends ScrubbableEvent>(event: T): T {
  const request = event.request;

  if (request) {
    if (typeof request.url === "string") {
      request.url = scrubUrl(request.url);
    }

    if (request.query_string !== undefined) {
      delete request.query_string;
    }

    if (request.data && typeof request.data === "object") {
      const data = { ...request.data } as Record<string, unknown>;
      for (const key of Object.keys(data)) {
        if (isSensitive(key)) {
          data[key] = FILTERED;
        }
      }
      request.data = data;
    }

    if (request.headers) {
      for (const key of Object.keys(request.headers)) {
        if (key.toLowerCase() === "referer") {
          request.headers[key] = scrubUrl(request.headers[key]);
        }
      }
    }
  }

  for (const breadcrumb of event.breadcrumbs ?? []) {
    const data = breadcrumb.data;

    if (!data) {
      continue;
    }

    for (const key of BREADCRUMB_URL_KEYS) {
      const value = data[key];
      if (typeof value === "string") {
        data[key] = scrubUrl(value);
      }
    }
  }

  return event;
}

type FilterableEvent = ScrubbableEvent & {
  exception?: { values?: Array<{ type?: string }> };
};

/**
 * The `beforeSend` both Sentry configs use: drops the per-page-view
 * SettingsUnavailableError (onRequestError on the server, the error boundary in
 * the browser) — settings.ts already reports the outage behind it once a
 * minute — and scrubs everything else.
 */
export function beforeSendFilter<T extends FilterableEvent>(
  event: T,
  hint?: { originalException?: unknown },
): T | null {
  if (
    isSettingsUnavailable(hint?.originalException) ||
    event.exception?.values?.some(
      (value) => value.type === "SettingsUnavailableError",
    )
  ) {
    return null;
  }

  return scrubEvent(event);
}
