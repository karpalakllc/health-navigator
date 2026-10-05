import { tFormat } from "@/i18n/t";

/**
 * Accessible text for a star rating, e.g. "4,5 / 5".
 *
 * The stars themselves are drawn rounded to whole stars, but the label must not
 * be: a 4.5 average announced as "5 / 5" overstates it. One decimal, with the
 * Macedonian decimal comma, and no trailing ",0".
 */
export function ratingLabel(value: number, max = 5): string {
  const clamped = Math.min(max, Math.max(0, value));
  const rounded = Math.round(clamped * 10) / 10;
  const text = Number.isInteger(rounded)
    ? String(rounded)
    : rounded.toFixed(1).replace(".", ",");

  return `${text} / ${max}`;
}

/** A submitted review rating: a whole number of stars from 1 to 5. */
export function isValidReviewRating(value: number | null): value is number {
  return value !== null && Number.isInteger(value) && value >= 1 && value <= 5;
}

/**
 * Accessible name of one star in the rating picker: "1 ѕвезда од 5",
 * "3 ѕвезди од 5". Macedonian takes the singular for numbers ending in 1
 * except 11 (CLDR `one`), the plural form otherwise.
 */
export function starRatingLabel(stars: number): string {
  const one = stars % 10 === 1 && stars % 100 !== 11;

  return tFormat(
    one ? "reviews.ratingStarLabelOne" : "reviews.ratingStarLabelOther",
    { stars: String(stars) },
  );
}
