import { apiGet, TAXONOMY_CACHE } from "@/lib/api/client";

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
 * GET /home/highlights: identical for every visitor, cached by the API for at
 * most five minutes and marked `public, max-age=300`, so the web tier shares
 * one copy for the same window.
 */
export async function fetchHomeHighlights(): Promise<HomeHighlights> {
  return apiGet<HomeHighlights>("/home/highlights", TAXONOMY_CACHE);
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
