import { apiGet, apiGetPaginated } from "@/lib/api/client";
import type { FacilityDetail, FacilityListItem } from "@/lib/api/types";

export type FacilityListParams = {
  type?: string;
  city?: string;
  q?: string;
  page?: number;
  per_page?: number;
};

function toQuery(params: FacilityListParams): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "") {
      search.set(key, String(value));
    }
  }

  const query = search.toString();

  return query ? `?${query}` : "";
}

export async function fetchFacilities(params: FacilityListParams = {}) {
  return apiGetPaginated<FacilityListItem>(`/facilities${toQuery(params)}`);
}

export async function fetchFacility(slug: string): Promise<FacilityDetail> {
  return apiGet<FacilityDetail>(`/facilities/${slug}`);
}
