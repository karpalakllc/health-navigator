import { apiGet, DIRECTORY_REVALIDATE_SECONDS } from "@/lib/api/client";

export type HomeSpecialty = {
  slug: string;
  name: string;
  doctors_count: number;
};

export type HomeCity = {
  name: string;
  /** Published doctors; each city links to /doctors?city=. */
  doctors_count: number;
};

export type HomeReviewTarget = {
  kind: "doctor" | "facility" | "pharmacy";
  slug: string;
  name: string;
};

export type HomeReview = {
  id: number;
  rating: number;
  /** At most 160 characters, cut at a word boundary by the API. */
  excerpt: string;
  /** The author's public display name — never the account's real name. */
  author_name: string;
  published_at: string | null;
  target: HomeReviewTarget | null;
};

export type HomeHighlights = {
  specialties: HomeSpecialty[];
  cities: HomeCity[];
  recent_reviews: HomeReview[];
};

export const EMPTY_HOME_HIGHLIGHTS: HomeHighlights = {
  specialties: [],
  cities: [],
  recent_reviews: [],
};

/**
 * GET /home/highlights: identical for every visitor. The API drops its copy
 * whenever a doctor, specialty, facility or review is saved; the web tier
 * re-reads it at most once a minute, like the directory lists, so approving or
 * hiding a review reaches the home page within about a minute.
 */
export async function fetchHomeHighlights(): Promise<HomeHighlights> {
  return apiGet<HomeHighlights>("/home/highlights", {
    revalidate: DIRECTORY_REVALIDATE_SECONDS,
  });
}

/** The public profile path for a review target. */
export function homeReviewTargetPath(target: HomeReviewTarget): string {
  const base =
    target.kind === "doctor"
      ? "/doctors"
      : target.kind === "pharmacy"
        ? "/pharmacies"
        : "/facilities";

  return `${base}/${encodeURIComponent(target.slug)}`;
}
