import { apiUrl } from "@/lib/config";
import type { ApiEnvelope, PaginatedEnvelope } from "@/lib/api/types";

/**
 * This UI is Macedonian-only, so we ask for Macedonian explicitly rather than
 * letting the API negotiate from the visitor's browser — otherwise a user with
 * an English-configured browser sees English API errors inside a Macedonian page.
 */
export const API_LANGUAGE_HEADER = { "Accept-Language": "mk" } as const;

/**
 * Per-call caching. Pages want fresh data ("no-store"); the sitemap wants its own
 * route-level revalidation to actually apply, and a fetch that forces "no-store"
 * silently opts the whole route out of caching regardless of what it exported.
 */
export type ApiCacheOptions = { revalidate?: number };

function cacheInit({ revalidate }: ApiCacheOptions = {}): RequestInit {
  return revalidate === undefined
    ? { cache: "no-store" }
    : { next: { revalidate } };
}

/**
 * Server-side, every call carries the web tier's credentials and the visitor's
 * address (lib/api/client-ip.ts) so the API meters each visitor separately
 * rather than the whole site as one client.
 *
 * This module cannot import client-ip.ts, not even dynamically: it is reachable
 * from a client component (via lib/api/guidance.ts), and client-ip.ts is
 * `server-only` and reads next/headers, both build errors in the client graph.
 * A `typeof window` guard does not help, because client components are also
 * rendered on the server. So client-ip.ts registers its provider here when the
 * server loads it (lib/api/server.ts imports it, and the root layout imports
 * server.ts through lib/api/settings.ts). The registry lives on globalThis
 * because the server and client-SSR graphs hold separate copies of this module.
 */
export type ServerHeadersProvider = (options: {
  forwardVisitor: boolean;
}) => Promise<Record<string, string>>;

const SERVER_HEADERS_PROVIDER = Symbol.for("zdravje.api.serverHeaders");

type ProviderRegistry = { [SERVER_HEADERS_PROVIDER]?: ServerHeadersProvider };

export function registerServerHeaders(provider: ServerHeadersProvider): void {
  (globalThis as ProviderRegistry)[SERVER_HEADERS_PROVIDER] = provider;
}

let warnedUnregistered = false;

async function serverSideHeaders(
  options: ApiCacheOptions = {},
): Promise<Record<string, string>> {
  // Never in the browser: there is no secret there, and no visitor to vouch for.
  if (typeof window !== "undefined") {
    return {};
  }

  const provider = (globalThis as ProviderRegistry)[SERVER_HEADERS_PROVIDER];

  if (!provider) {
    if (!warnedUnregistered) {
      warnedUnregistered = true;
      console.warn(
        "lib/api/client: client-ip.ts is not loaded; server-side API calls are not identifying the visitor.",
      );
    }
    return {};
  }

  return provider({ forwardVisitor: options.revalidate === undefined });
}

export async function apiGet<T>(
  path: string,
  options?: ApiCacheOptions,
): Promise<T> {
  const response = await fetch(apiUrl(path), {
    ...cacheInit(options),
    headers: { ...API_LANGUAGE_HEADER, ...(await serverSideHeaders(options)) },
  });

  if (!response.ok) {
    throw new Error(`API request failed (${response.status})`);
  }

  const body = (await response.json()) as ApiEnvelope<T>;

  return body.data;
}

export async function apiGetPaginated<T>(
  path: string,
  options?: ApiCacheOptions,
): Promise<PaginatedEnvelope<T>> {
  const response = await fetch(apiUrl(path), {
    ...cacheInit(options),
    headers: { ...API_LANGUAGE_HEADER, ...(await serverSideHeaders(options)) },
  });

  if (!response.ok) {
    throw new Error(`API request failed (${response.status})`);
  }

  return (await response.json()) as PaginatedEnvelope<T>;
}
