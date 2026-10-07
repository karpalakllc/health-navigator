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

export type OnDutyPharmacy = {
  name: string;
  municipality: string | null;
  address: string | null;
  phone: string | null;
  mode: "all_day" | "hours" | "on_call" | "unknown";
  hours_text: string | null;
  /** Set when the pharmacy has a published profile. */
  slug: string | null;
  latitude: number | null;
  longitude: number | null;
};

export type OnDutyPharmacies = {
  available: boolean;
  /** The schedule day („tonight“; before 07:00 the previous day). */
  date?: string;
  source_url?: string;
  items?: OnDutyPharmacy[] | null;
};

export type UrgentCareList = {
  data: UrgentCarePlace[];
  meta: {
    city: string | null;
    type: UrgentService | null;
    total: number;
    /**
     * Tonight's on-duty pharmacies from ФЗОМ's schedule: `items` only when a
     * city is chosen; `available` false = the month is not imported (the
     * page shows a placeholder).
     */
    on_duty_pharmacies: OnDutyPharmacies;
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
