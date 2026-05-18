import { apiGetPaginated } from "@/lib/api/client";
import { apiGetServer } from "@/lib/api/server";
import type { DoctorDetail, DoctorListItem } from "@/lib/api/types";

export type DoctorListParams = {
  specialty?: string;
  city?: string;
  q?: string;
  featured?: boolean;
  sort?: "name" | "rating";
  min_reviews?: number;
  page?: number;
  per_page?: number;
};

function toQuery(params: DoctorListParams): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === "") {
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

export async function fetchDoctors(params: DoctorListParams = {}) {
  return apiGetPaginated<DoctorListItem>(`/doctors${toQuery(params)}`);
}

export async function fetchDoctor(slug: string): Promise<DoctorDetail> {
  return apiGetServer<DoctorDetail>(`/doctors/${slug}`);
}
