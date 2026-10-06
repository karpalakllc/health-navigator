import { cache } from "react";
import { apiGetPaginatedServer, apiGetServer } from "@/lib/api/server";

export type ProfileAvatarMeta = {
  min_messages: number;
  message_count: number;
  can_change: boolean;
};

export type AuthUser = {
  id: number;
  /** Private: the account page only. Never render it next to public content. */
  name: string;
  /** Shown publicly next to reviews and forum posts. */
  display_name: string;
  email: string;
  role: string;
  community_roles: string[];
  avatar_url: string | null;
  avatar_initials: string;
  profile_avatar: ProfileAvatarMeta;
  /** „Мој профил“: the doctor profile staff linked to this account, if any. */
  managed_doctor?: ManagedDoctorSummary | null;
};

export type ManagedDoctorSummary = {
  slug: string;
  full_name: string;
  is_published: boolean;
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

/**
 * One /me per request for the parts of a page that only need to know whether
 * the account manages a doctor profile (the account nav), however many of
 * them ask. Null when signed out or the API is unreachable: the nav then
 * simply leaves the entry out.
 */
export const fetchManagedDoctor = cache(
  async (): Promise<ManagedDoctorSummary | null> => {
    try {
      return (await fetchMe()).managed_doctor ?? null;
    } catch {
      return null;
    }
  },
);

export async function fetchMyReviews(page = 1) {
  return apiGetPaginatedServer<MyReview>(`/me/reviews?page=${page}`);
}
