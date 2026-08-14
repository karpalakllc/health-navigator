import { apiGet, apiGetPaginated } from "@/lib/api/client";
import type {
  PharmacyDetail,
  PharmacyListItem,
  PharmacyShelfProduct,
} from "@/lib/api/types";

export type PharmacyListParams = {
  city?: string;
  q?: string;
  page?: number;
  per_page?: number;
};

function toQuery(params: Record<string, string | number | undefined>): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "") {
      search.set(key, String(value));
    }
  }

  const query = search.toString();

  return query ? `?${query}` : "";
}

export async function fetchPharmacies(params: PharmacyListParams = {}) {
  return apiGetPaginated<PharmacyListItem>(`/pharmacies${toQuery(params)}`);
}

export async function fetchPharmacy(slug: string): Promise<PharmacyDetail> {
  return apiGet<PharmacyDetail>(`/pharmacies/${slug}`);
}

export async function fetchPharmacyProducts(
  slug: string,
  params: { q?: string; category?: string; page?: number } = {},
) {
  return apiGetPaginated<PharmacyShelfProduct>(
    `/pharmacies/${slug}/products${toQuery(params)}`,
  );
}
