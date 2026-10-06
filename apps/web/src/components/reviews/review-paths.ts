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
