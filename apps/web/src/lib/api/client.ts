import { apiUrl } from "@/lib/config";
import type { ApiEnvelope, PaginatedEnvelope } from "@/lib/api/types";

export async function apiGet<T>(path: string): Promise<T> {
  const response = await fetch(apiUrl(path), { cache: "no-store" });

  if (!response.ok) {
    throw new Error(`API request failed (${response.status})`);
  }

  const body = (await response.json()) as ApiEnvelope<T>;

  return body.data;
}

export async function apiGetPaginated<T>(
  path: string,
): Promise<PaginatedEnvelope<T>> {
  const response = await fetch(apiUrl(path), { cache: "no-store" });

  if (!response.ok) {
    throw new Error(`API request failed (${response.status})`);
  }

  return (await response.json()) as PaginatedEnvelope<T>;
}
