import { apiGetPaginated, type ApiCacheOptions } from "@/lib/api/client";
import { apiGetServer } from "@/lib/api/server";
import type { FacilityDetail, FacilityListItem } from "@/lib/api/types";

export type FacilityListParams = {
  type?: string;
  city?: string;
  q?: string;
  has_emergency?: boolean | string;
  department?: string;
  featured?: boolean | string;
  page?: number;
  per_page?: number;
};

function toQuery(params: FacilityListParams): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === "") {
      continue;
    }
    if (value === false) {
      continue;
    }
    if (typeof value === "boolean") {
      search.set(key, value ? "1" : "0");
      continue;
    }
    search.set(key, String(value));
  }

  const query = search.toString();

  return query ? `?${query}` : "";
}

export async function fetchFacilities(
  params: FacilityListParams = {},
  options?: ApiCacheOptions,
) {
  return apiGetPaginated<FacilityListItem>(
    `/facilities${toQuery(params)}`,
    options,
  );
}

export async function fetchFacility(slug: string): Promise<FacilityDetail> {
  return apiGetServer<FacilityDetail>(`/facilities/${slug}`);
}
