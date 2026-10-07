import {
  apiGet,
  apiGetPaginated,
  DIRECTORY_REVALIDATE_SECONDS,
  type ApiCacheOptions,
} from "@/lib/api/client";
import type {
  UrgentCareCity,
  UrgentCarePlace,
  UrgentService,
} from "@/lib/urgent-care";

export type UrgentCareList = {
  data: UrgentCarePlace[];
  meta: {
    city: string | null;
    type: UrgentService | null;
    total: number;
    /** No source for on-duty pharmacies yet: the page shows a placeholder. */
    on_duty_pharmacies: { available: boolean };
  };
};

/**
 * GET /urgent-care. `cacheable` is for a city from the static territorial
 * list (or none): a free-text city is fetched per request, like the
 * directory's free-text filters.
 */
export async function fetchUrgentCare(
  params: { city?: string; type?: UrgentService },
  cacheable: boolean,
): Promise<UrgentCareList> {
  const search = new URLSearchParams();
  if (params.city?.trim()) search.set("city", params.city.trim());
  if (params.type) search.set("type", params.type);
  const query = search.toString();
  const options: ApiCacheOptions = cacheable
    ? { revalidate: DIRECTORY_REVALIDATE_SECONDS }
    : {};

  return (await apiGetPaginated<UrgentCarePlace>(
    `/urgent-care${query ? `?${query}` : ""}`,
    options,
  )) as unknown as UrgentCareList;
}

/** Cities with at least one urgent-care place (city pages, sitemap). */
export async function fetchUrgentCareCities(
  options: ApiCacheOptions = { revalidate: DIRECTORY_REVALIDATE_SECONDS },
): Promise<UrgentCareCity[]> {
  return apiGet<UrgentCareCity[]>("/urgent-care/cities", options);
}
