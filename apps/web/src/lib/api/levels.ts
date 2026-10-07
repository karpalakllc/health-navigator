import { apiGet } from "@/lib/api/client";
import { apiGetServer } from "@/lib/api/server";

/** W8-C: which title ladder a level belongs to (docs/levels.md). */
export type LevelLadder = "review" | "forum";

type NextStep<CountKey extends string> = {
  level: number;
  missing_points: number;
} & Record<CountKey, number>;

/** GET /me/levels: the member's own levels and what the next ones need. */
export type MyLevels = {
  /** False for staff, suspended accounts and temporary usernames. */
  shown_publicly: boolean;
  reviews: {
    level: number;
    points: number;
    reviews: number;
    helpful: number;
    next: NextStep<"missing_reviews"> | null;
  };
  forum: {
    level: number;
    points: number;
    topics: number;
    replies: number;
    helpful: number;
    next: NextStep<"missing_posts"> | null;
  };
};

export type ReviewerRow = {
  username: string;
  level: number;
  points: number;
  reviews: number;
  helpful: number;
};

export type ForumMemberRow = {
  username: string;
  level: number;
  points: number;
  topics: number;
  replies: number;
  helpful: number;
};

/** The rules behind the levels, as the API applies them. */
export type LevelRulesInfo = {
  reviews: {
    review_points: number;
    helpful_points: number;
    removed_penalty: number;
    reviews_per_day: number;
    ladder: Array<{ level: number; reviews: number; points: number }>;
  };
  forum: {
    topic_points: number;
    reply_points: number;
    helpful_points: number;
    removed_penalty: number;
    topics_per_day: number;
    replies_per_day: number;
    ladder: Array<{ level: number; posts: number; points: number }>;
  };
  helpful_per_item: number;
  helpful_per_voter_per_author: number;
  leaderboard_size: number;
};

/** GET /community/leaderboards: last calendar month's top lists. */
export type Leaderboards = {
  /** YYYY-MM, Macedonian time. */
  month: string;
  reviewers: ReviewerRow[];
  /** Null while the forum module is off. */
  forum: ForumMemberRow[] | null;
  rules: LevelRulesInfo;
};

/** The API caches the lists for hours; ten minutes here is plenty. */
export const LEADERBOARDS_REVALIDATE_SECONDS = 600;

/** Null when the API cannot answer: the page then says so calmly. */
export async function fetchLeaderboards(): Promise<Leaderboards | null> {
  try {
    return await apiGet<Leaderboards>("/community/leaderboards", {
      revalidate: LEADERBOARDS_REVALIDATE_SECONDS,
    });
  } catch {
    return null;
  }
}

/** The signed-in member's levels, or null when the API cannot answer. */
export async function fetchMyLevels(): Promise<MyLevels | null> {
  try {
    return await apiGetServer<MyLevels>("/me/levels");
  } catch {
    return null;
  }
}
