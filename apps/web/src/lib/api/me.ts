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
  /** Unique; shown publicly next to reviews and forum posts. */
  username: string;
  /** Still a temporary „clen-…“ name: ask the member to choose one. */
  must_choose_username: boolean;
  username_changed_at: string | null;
  /** From when the member may change it again (once every 90 days); null = now. */
  username_change_available_at: string | null;
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
