import { apiGetPaginated } from "@/lib/api/client";
import type { PublicReview } from "@/lib/api/types";

export async function fetchDoctorReviews(slug: string, page = 1) {
  return apiGetPaginated<PublicReview>(
    `/doctors/${slug}/reviews?page=${page}`,
  );
}

export async function fetchFacilityReviews(slug: string, page = 1) {
  return apiGetPaginated<PublicReview>(
    `/facilities/${slug}/reviews?page=${page}`,
  );
}
