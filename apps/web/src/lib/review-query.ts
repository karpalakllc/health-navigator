/**
 * Review list query parameters, as they arrive in a detail page's URL.
 *
 * They are visitor-controlled and the API validates them strictly (422 on an
 * unknown sort or a rating outside 1–5), and a 422 there used to take the whole
 * doctor/facility/pharmacy page down with it. Anything unrecognised is dropped
 * back to the default instead — the same thing the UI's own controls produce.
 */

export const REVIEW_SORTS = [
  "newest",
  "oldest",
  "rating_high",
  "rating_low",
] as const;

export type ReviewSort = (typeof REVIEW_SORTS)[number];

/** Next hands over an array when a parameter is repeated in the URL. */
type SearchParamValue = string | string[] | undefined;

export type ReviewQueryInput = {
  review_page?: SearchParamValue;
  review_sort?: SearchParamValue;
  review_rating?: SearchParamValue;
};

export type ReviewQuery = {
  page: number;
  sort: ReviewSort;
  /** 1–5, or undefined for "all ratings". */
  rating: number | undefined;
};

function isReviewSort(value: string): value is ReviewSort {
  return (REVIEW_SORTS as readonly string[]).includes(value);
}

function single(value: SearchParamValue): string {
  return typeof value === "string" ? value.trim() : "";
}

export function parseReviewQuery(input: ReviewQueryInput = {}): ReviewQuery {
  const page = Number(single(input.review_page));
  const rating = Number(single(input.review_rating));
  const sort = single(input.review_sort);

  return {
    page: Number.isSafeInteger(page) && page > 0 ? page : 1,
    sort: isReviewSort(sort) ? sort : "newest",
    rating:
      Number.isInteger(rating) && rating >= 1 && rating <= 5
        ? rating
        : undefined,
  };
}
