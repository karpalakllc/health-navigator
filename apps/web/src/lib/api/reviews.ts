import { apiGetPaginatedServer } from "@/lib/api/server";
import type { PublicReview } from "@/lib/api/types";

export async function fetchDoctorReviews(slug: string, page = 1) {
  return apiGetPaginatedServer<PublicReview>(
    `/doctors/${slug}/reviews?page=${page}`,
  );
}

export async function fetchFacilityReviews(slug: string, page = 1) {
  return apiGetPaginatedServer<PublicReview>(
    `/facilities/${slug}/reviews?page=${page}`,
  );
}
