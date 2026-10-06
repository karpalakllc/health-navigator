import { describe, expect, it } from "vitest";
import { parseReviewQuery } from "@/lib/review-query";

/**
 * The API answers 422 to anything outside these values (ListReviewsRequest),
 * and that 422 used to take the whole detail page down with it.
 */
describe("parseReviewQuery", () => {
  it("defaults when nothing is given", () => {
    expect(parseReviewQuery()).toEqual({
      page: 1,
      sort: "newest",
      rating: undefined,
    });
  });

  it("passes valid values through", () => {
    expect(
      parseReviewQuery({
        review_page: "3",
        review_sort: "rating_low",
        review_rating: "4",
      }),
    ).toEqual({ page: 3, sort: "rating_low", rating: 4 });
  });

  it.each(["9", "0", "-1", "4.5", "abc", "1e1"])(
    "drops review_rating=%s",
    (value) => {
      expect(parseReviewQuery({ review_rating: value }).rating).toBeUndefined();
    },
  );

  it.each(["rating", "NEWEST", "", "newest;drop"])(
    "drops review_sort=%s",
    (value) => {
      expect(parseReviewQuery({ review_sort: value }).sort).toBe("newest");
    },
  );

  it.each(["0", "-2", "1.5", "x", "1e21"])("drops review_page=%s", (value) => {
    expect(parseReviewQuery({ review_page: value }).page).toBe(1);
  });

  it("ignores a parameter repeated in the URL", () => {
    expect(
      parseReviewQuery({ review_rating: ["3", "4"], review_sort: ["oldest"] }),
    ).toEqual({ page: 1, sort: "newest", rating: undefined });
  });
});
