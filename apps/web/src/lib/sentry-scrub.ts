/**
 * Strips credentials from Sentry events before they leave the process.
 *
 * Request bodies were already filtered, but URLs were not: a password-reset link
 * carries `?token=…&email=…`, and Sentry records URLs in several places — the
 * request itself, its query string, the Referer header, and every navigation and
 * fetch breadcrumb. Shared by the browser and server configs.
 */

const FILTERED = "[Filtered]";
const SENSITIVE_KEYS = ["password", "password_confirmation", "token", "email"];

function isSensitive(key: string): boolean {
  return SENSITIVE_KEYS.includes(key.toLowerCase());
}

function scrubParams(params: URLSearchParams): boolean {
  let changed = false;

  for (const key of new Set(params.keys())) {
    if (isSensitive(key)) {
      params.set(key, FILTERED);
      changed = true;
    }
  }

  return changed;
}

const PROBE_ORIGIN = "https://scrub.invalid";

/** Filters sensitive query parameters, keeping the URL absolute or relative as given. */
export function scrubUrl(url: string): string {
  let parsed: URL;

  try {
    parsed = new URL(url, PROBE_ORIGIN);
  } catch {
    return url;
  }

  if (!scrubParams(parsed.searchParams)) {
    return url;
  }

  return parsed.origin === PROBE_ORIGIN && !url.startsWith(PROBE_ORIGIN)
    ? `${parsed.pathname}${parsed.search}${parsed.hash}`
    : parsed.toString();
}

type QueryParams = string | Record<string, string> | Array<[string, string]>;

function scrubQuery(query: QueryParams): QueryParams {
  if (typeof query === "string") {
    const params = new URLSearchParams(query);
    return scrubParams(params) ? params.toString() : query;
  }

  if (Array.isArray(query)) {
    return query.map(([key, value]): [string, string] => [
      key,
      isSensitive(key) ? FILTERED : value,
    ]);
  }

  return Object.fromEntries(
    Object.entries(query).map(([key, value]) => [
      key,
      isSensitive(key) ? FILTERED : value,
    ]),
  );
}

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
      request.query_string = scrubQuery(request.query_string);
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
