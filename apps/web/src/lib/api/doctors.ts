import {
  apiGetPaginated,
  directoryCache,
  type ApiCacheOptions,
} from "@/lib/api/client";
import { apiGetServer } from "@/lib/api/server";
import { fetchLanguages } from "@/lib/api/languages";
import { fetchSpecialties } from "@/lib/api/specialties";
import type { DoctorDetail, DoctorListItem } from "@/lib/api/types";
import { pathSegment } from "@/lib/api/path";

export type DoctorListParams = {
  specialty?: string;
  /** A slug from GET /languages; anything else is a 422. */
  language?: string;
  city?: string;
  q?: string;
  featured?: boolean;
  sort?: "name" | "rating";
  min_reviews?: number;
  /** „Само верифицирани“. */
  verified?: boolean;
  page?: number;
  per_page?: number;
};

function toQuery(params: DoctorListParams): string {
  const search = new URLSearchParams();

  for (const [key, raw] of Object.entries(params)) {
    // Whitespace-only text means "no filter"; sending it would also make every
    // variant ("?q=%20", "?q=%20%20", …) its own shared cache entry.
    const value = typeof raw === "string" ? raw.trim() : raw;
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

/**
 * A specialty or language only keeps a listing cacheable when it is one the
 * (cached) taxonomy knows; city and q are free text and never do.
 */
async function doctorsCache(
  params: DoctorListParams,
): Promise<ApiCacheOptions> {
  const [specialties, languages] = await Promise.all([
    params.specialty ? fetchSpecialties().catch(() => []) : [],
    params.language ? fetchLanguages().catch(() => []) : [],
  ]);

  return directoryCache(params, {
    oneOf: {
      specialty: specialties.map(({ slug }) => slug),
      language: languages.map(({ slug }) => slug),
      sort: ["name", "rating"],
    },
    booleans: ["featured", "verified"],
    integers: { min_reviews: 10 },
  });
}

export async function fetchDoctors(
  params: DoctorListParams = {},
  options?: ApiCacheOptions,
) {
  return apiGetPaginated<DoctorListItem>(
    `/doctors${toQuery(params)}`,
    options ?? (await doctorsCache(params)),
  );
}

export async function fetchDoctor(slug: string): Promise<DoctorDetail> {
  return apiGetServer<DoctorDetail>(`/doctors/${pathSegment(slug)}`);
}
