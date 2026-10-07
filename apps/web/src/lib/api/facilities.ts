import {
  apiGetPaginated,
  directoryCache,
  type ApiCacheOptions,
} from "@/lib/api/client";
import { fetchDepartments } from "@/lib/api/departments";
import { apiGetServer } from "@/lib/api/server";
import type {
  FacilityDetail,
  FacilityListItem,
  FacilityType,
} from "@/lib/api/types";
import { pathSegment } from "@/lib/api/path";

export type FacilityListParams = {
  type?: string;
  city?: string;
  q?: string;
  has_emergency?: boolean | string;
  department?: string;
  featured?: boolean | string;
  /** „Само верификувани“. */
  verified?: boolean | string;
  page?: number;
  per_page?: number;
};

function toQuery(params: FacilityListParams): string {
  const search = new URLSearchParams();

  for (const [key, raw] of Object.entries(params)) {
    // Whitespace-only text means "no filter" (see doctors.ts toQuery).
    const value = typeof raw === "string" ? raw.trim() : raw;
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

const FACILITY_TYPES: readonly FacilityType[] = [
  "clinic",
  "hospital",
  "laboratory",
];

/**
 * A department only keeps a listing cacheable when the (cached) taxonomy
 * knows it; city and q are free text and never do.
 */
async function facilitiesCache(
  params: FacilityListParams,
): Promise<ApiCacheOptions> {
  const departments = params.department
    ? await fetchDepartments().catch(() => [])
    : [];

  return directoryCache(params, {
    oneOf: {
      department: departments.map(({ slug }) => slug),
      type: FACILITY_TYPES,
    },
    booleans: ["has_emergency", "featured", "verified"],
  });
}

export async function fetchFacilities(
  params: FacilityListParams = {},
  options?: ApiCacheOptions,
) {
  return apiGetPaginated<FacilityListItem>(
    `/facilities${toQuery(params)}`,
    options ?? (await facilitiesCache(params)),
  );
}

export async function fetchFacility(slug: string): Promise<FacilityDetail> {
  return apiGetServer<FacilityDetail>(`/facilities/${pathSegment(slug)}`);
}
