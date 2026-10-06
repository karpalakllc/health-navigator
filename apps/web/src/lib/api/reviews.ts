import { apiGetPaginatedServer } from "@/lib/api/server";
import type { PublicReview } from "@/lib/api/types";
import { pathSegment } from "@/lib/api/path";

export type ReviewListParams = {
  page?: number;
  per_page?: number;
  sort?: "newest" | "oldest" | "rating_high" | "rating_low" | "helpful";
  rating?: number;
};

function toQuery(params: ReviewListParams): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined) {
      continue;
    }
    search.set(key, String(value));
  }

  const query = search.toString();

  return query ? `?${query}` : "";
}

export async function fetchDoctorReviews(
  slug: string,
  params: ReviewListParams = {},
) {
  return apiGetPaginatedServer<PublicReview>(
    `/doctors/${pathSegment(slug)}/reviews${toQuery(params)}`,
  );
}

export async function fetchFacilityReviews(
  slug: string,
  params: ReviewListParams = {},
) {
  return apiGetPaginatedServer<PublicReview>(
    `/facilities/${pathSegment(slug)}/reviews${toQuery(params)}`,
  );
}

export async function fetchPharmacyReviews(
  slug: string,
  params: ReviewListParams = {},
) {
  return apiGetPaginatedServer<PublicReview>(
    `/pharmacies/${pathSegment(slug)}/reviews${toQuery(params)}`,
  );
}
