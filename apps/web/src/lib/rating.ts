import { tFormat } from "@/i18n/t";

/**
 * An average rating as text: always one decimal with the Macedonian decimal
 * comma — „4,7“, „5,0“ — so every rating on the site reads the same way
 * (a bare „5“ next to „4,7“ looked like a different scale).
 */
export function formatRating(value: number): string {
  return (Math.round(value * 10) / 10).toFixed(1).replace(".", ",");
}

/**
 * Accessible text for a star rating, e.g. "4,5 / 5".
 *
 * The stars themselves are drawn to the nearest half star, but the label must
 * not be rounded to them: a 4.5 average announced as "5 / 5" overstates it.
 */
export function ratingLabel(value: number, max = 5): string {
  const clamped = Math.min(max, Math.max(0, value));

  return `${formatRating(clamped)} / ${max}`;
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
