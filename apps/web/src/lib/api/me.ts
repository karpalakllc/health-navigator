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
  /** „Мој профил“: the doctor profile staff linked to this account, if any. */
  managed_doctor?: ManagedDoctorSummary | null;
};

export type ManagedDoctorSummary = {
  slug: string;
  full_name: string;
  is_published: boolean;
};

export type MyReview = {
  id?: number;
  rating: number;
  body: string | null;
  status: string;
  rejection_note: string | null;
  /** Refused before publication and not yet resent: one more try allowed. */
  can_resubmit?: boolean;
  created_at: string | null;
  reviewable: {
    kind: "doctor" | "facility" | "pharmacy";
    slug: string;
    name: string;
  } | null;
  /**
   * W8-B, published reviews only: how often the card was on a visitor's
   * screen (at most once a day per network) and its „Корисно“ votes.
   */
  impact?: { views: number; helpful: number } | null;
  /** The profile's reply, once it is public. */
  reply?: {
    body: string;
    source: "doctor" | "staff" | null;
    responded_at: string | null;
  } | null;
};

/** W8-B: totals over the member's published reviews. */
export type MyReviewImpact = {
  views: number;
  helpful: number;
  replies: number;
  published: number;
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
  const envelope = await apiGetPaginatedServer<MyReview>(
    `/me/reviews?page=${page}`,
  );

  return envelope as typeof envelope & {
    meta: typeof envelope.meta & { impact?: MyReviewImpact };
  };
}
