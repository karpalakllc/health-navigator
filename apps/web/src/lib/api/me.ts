import { apiGetPaginatedServer, apiGetServer } from "@/lib/api/server";

export type ProfileAvatarMeta = {
  min_messages: number;
  message_count: number;
  can_change: boolean;
};

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: string;
  community_roles: string[];
  avatar_url: string | null;
  avatar_initials: string;
  profile_avatar: ProfileAvatarMeta;
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
