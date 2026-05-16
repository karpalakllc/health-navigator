import { apiGet } from "@/lib/api/client";
import type { UnifiedSearchResult } from "@/lib/api/types";

export type SearchParams = {
  q?: string;
  city?: string;
  per_page?: number;
};

function toQuery(params: SearchParams): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "") {
      search.set(key, String(value));
    }
  }

  const query = search.toString();

  return query ? `?${query}` : "";
}

export async function fetchUnifiedSearch(params: SearchParams): Promise<UnifiedSearchResult> {
  return apiGet<UnifiedSearchResult>(`/search${toQuery(params)}`);
}
