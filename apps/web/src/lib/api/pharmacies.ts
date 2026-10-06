import { apiGet, apiGetPaginated, directoryCache } from "@/lib/api/client";
import type {
  PharmacyDetail,
  PharmacyListItem,
  PharmacyShelfProduct,
} from "@/lib/api/types";
import { pathSegment } from "@/lib/api/path";

export type PharmacyListParams = {
  city?: string;
  q?: string;
  page?: number;
  per_page?: number;
};

function toQuery(params: Record<string, string | number | undefined>): string {
  const search = new URLSearchParams();

  for (const [key, raw] of Object.entries(params)) {
    // Whitespace-only text means "no filter" (see doctors.ts toQuery).
    const value = typeof raw === "string" ? raw.trim() : raw;
    if (value !== undefined && value !== "") {
      search.set(key, String(value));
    }
  }

  const query = search.toString();

  return query ? `?${query}` : "";
}

export async function fetchPharmacies(params: PharmacyListParams = {}) {
  return apiGetPaginated<PharmacyListItem>(
    `/pharmacies${toQuery(params)}`,
    directoryCache(params),
  );
}

export async function fetchPharmacy(slug: string): Promise<PharmacyDetail> {
  return apiGet<PharmacyDetail>(`/pharmacies/${pathSegment(slug)}`);
}

export async function fetchPharmacyProducts(
  slug: string,
  params: { q?: string; category?: string; page?: number } = {},
) {
  return apiGetPaginated<PharmacyShelfProduct>(
    `/pharmacies/${pathSegment(slug)}/products${toQuery(params)}`,
  );
}
