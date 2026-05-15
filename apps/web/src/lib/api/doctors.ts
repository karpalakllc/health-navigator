import { apiGet, apiGetPaginated } from "@/lib/api/client";
import type { DoctorDetail, DoctorListItem } from "@/lib/api/types";

export type DoctorListParams = {
  specialty?: string;
  city?: string;
  q?: string;
  featured?: boolean;
  page?: number;
  per_page?: number;
};

function toQuery(params: DoctorListParams): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "") {
      search.set(key, String(value));
    }
  }

  const query = search.toString();

  return query ? `?${query}` : "";
}

export async function fetchDoctors(params: DoctorListParams = {}) {
  return apiGetPaginated<DoctorListItem>(`/doctors${toQuery(params)}`);
}

export async function fetchDoctor(slug: string): Promise<DoctorDetail> {
  return apiGet<DoctorDetail>(`/doctors/${slug}`);
}
