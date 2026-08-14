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

export async function apiGet<T>(path: string, options?: ApiCacheOptions): Promise<T> {
  const response = await fetch(apiUrl(path), {
    ...cacheInit(options),
    headers: { ...API_LANGUAGE_HEADER },
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
    headers: { ...API_LANGUAGE_HEADER },
  });

  if (!response.ok) {
    throw new Error(`API request failed (${response.status})`);
  }

  return (await response.json()) as PaginatedEnvelope<T>;
}
