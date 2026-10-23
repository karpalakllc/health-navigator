/** The fragment that opens a profile at its review form (also the share link's). */
export const REVIEW_ANCHOR = "napishi-recenzija";

/**
 * The inline „Напишете рецензија“ of a profile's review summary: the mobile
 * sticky review action steps aside while it is on screen (one CTA at a time).
 */
export const REVIEW_CTA_ID = "napishi-recenzija-kopce";

/** The profile page that holds a review list. */
export function reviewsBasePath(
  kind: "doctor" | "facility" | "pharmacy",
  slug: string,
): string {
  return kind === "doctor"
    ? `/doctors/${slug}`
    : kind === "pharmacy"
      ? `/pharmacies/${slug}`
      : `/facilities/${slug}`;
}
