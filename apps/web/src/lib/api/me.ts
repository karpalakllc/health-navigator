import { apiGetPaginatedServer, apiGetServer } from "@/lib/api/server";

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: string;
};

export type MyReview = {
  rating: number;
  body: string | null;
  status: string;
  rejection_note: string | null;
  created_at: string | null;
  reviewable: {
    kind: "doctor" | "facility" | "pharmacy";
    slug: string;
    name: string;
  } | null;
};

export async function fetchMe(): Promise<AuthUser> {
  const data = await apiGetServer<{ user: AuthUser }>("/me");
  return data.user;
}

export async function fetchMyReviews(page = 1) {
  return apiGetPaginatedServer<MyReview>(`/me/reviews?page=${page}`);
}
