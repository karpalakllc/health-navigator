import "server-only";
import { webTierRequestHeaders } from "@/lib/api/client-ip";
import { apiUrl } from "@/lib/config";
import {
  isCacheableDirectoryQuery,
  type DirectoryCacheRule,
} from "@/lib/api/directory-cache-policy";
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

/**
 * Taxonomies (specialties, departments, forum categories) are identical for
 * every visitor and change rarely; the API marks them `public, max-age=300` and
 * busts its own copy on edit.
 */
export const TAXONOMY_CACHE: ApiCacheOptions = { revalidate: 300 };

/** Directory listings: shared by everyone, tolerant of a minute's staleness. */
export const DIRECTORY_REVALIDATE_SECONDS = 60;

/**
 * Data-cache policy for an anonymous directory list: cached for a minute only
 * when every parameter is in the rule's known-safe set
 * (lib/api/directory-cache-policy.ts); otherwise per-request, with the
 * visitor's address forwarded (serverSideHeaders).
 *
 * Token-bearing reads go through lib/api/server.ts, which is always no-store.
 */
export function directoryCache(
  params: Record<string, unknown>,
  rule?: DirectoryCacheRule,
): ApiCacheOptions {
  return isCacheableDirectoryQuery(params, rule)
    ? { revalidate: DIRECTORY_REVALIDATE_SECONDS }
    : {};
}

function cacheInit({ revalidate }: ApiCacheOptions = {}): RequestInit {
  return revalidate === undefined
    ? { cache: "no-store" }
    : { next: { revalidate } };
}

/**
 * Every call carries the web tier's credentials and the visitor's address
 * (lib/api/client-ip.ts) so the API meters each visitor separately rather than
 * the whole site as one client. Cached fetches send the credentials without
 * the visitor: their response is shared by everyone.
 *
 * This module is server-only (client-ip.ts is): browser-side calls, such as the
 * guidance wizard's in lib/api/guidance.ts, must not import it.
 */
function serverSideHeaders(
  options: ApiCacheOptions = {},
): Promise<Record<string, string>> {
  return webTierRequestHeaders({
    forwardVisitor: options.revalidate === undefined,
  });
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
