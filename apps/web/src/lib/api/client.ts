import { apiUrl } from "@/lib/config";
import type { ApiEnvelope, PaginatedEnvelope } from "@/lib/api/types";

/**
 * This UI is Macedonian-only, so we ask for Macedonian explicitly rather than
 * letting the API negotiate from the visitor's browser — otherwise a user with
 * an English-configured browser sees English API errors inside a Macedonian page.
 */
export const API_LANGUAGE_HEADER = { "Accept-Language": "mk" } as const;

export async function apiGet<T>(path: string): Promise<T> {
  const response = await fetch(apiUrl(path), {
    cache: "no-store",
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
): Promise<PaginatedEnvelope<T>> {
  const response = await fetch(apiUrl(path), {
    cache: "no-store",
    headers: { ...API_LANGUAGE_HEADER },
  });

  if (!response.ok) {
    throw new Error(`API request failed (${response.status})`);
  }

  return (await response.json()) as PaginatedEnvelope<T>;
}
