import { apiGet, apiGetPaginated, directoryCache } from "@/lib/api/client";
import type { ProductDetail, ProductListItem } from "@/lib/api/types";
import { pathSegment } from "@/lib/api/path";

export type ProductListParams = {
  q?: string;
  category?: string;
  pharmacy?: string;
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

export async function fetchProducts(params: ProductListParams = {}) {
  // Only the unfiltered pages are shared: category is free text in the API
  // (an ILIKE match), and pharmacy is a slug with no cached list to check it
  // against, so either makes the listing per-visitor.
  return apiGetPaginated<ProductListItem>(
    `/products${toQuery(params)}`,
    directoryCache(params),
  );
}

export async function fetchProduct(slug: string): Promise<ProductDetail> {
  return apiGet<ProductDetail>(`/products/${pathSegment(slug)}`);
}
