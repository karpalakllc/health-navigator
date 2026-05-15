import { apiGet, apiGetPaginated } from "@/lib/api/client";
import type { ProductDetail, ProductListItem } from "@/lib/api/types";

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
  return apiGetPaginated<ProductListItem>(`/products${toQuery(params)}`);
}

export async function fetchProduct(slug: string): Promise<ProductDetail> {
  return apiGet<ProductDetail>(`/products/${slug}`);
}
