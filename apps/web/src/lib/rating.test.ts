import { describe, expect, it } from "vitest";
import { isValidReviewRating, ratingLabel } from "@/lib/rating";

describe("ratingLabel", () => {
  it("keeps one decimal instead of rounding 4.5 up to 5", () => {
    expect(ratingLabel(4.5)).toBe("4,5 / 5");
    expect(ratingLabel(4.46)).toBe("4,5 / 5");
  });

  it("drops a trailing ,0", () => {
    expect(ratingLabel(4)).toBe("4 / 5");
    expect(ratingLabel(3.96)).toBe("4 / 5");
  });

  it("clamps to the scale", () => {
    expect(ratingLabel(7)).toBe("5 / 5");
    expect(ratingLabel(-1)).toBe("0 / 5");
  });
});

describe("isValidReviewRating", () => {
  it("requires an explicit whole-star choice", () => {
    expect(isValidReviewRating(null)).toBe(false);
    expect(isValidReviewRating(0)).toBe(false);
    expect(isValidReviewRating(6)).toBe(false);
    expect(isValidReviewRating(2.5)).toBe(false);
    expect(isValidReviewRating(1)).toBe(true);
    expect(isValidReviewRating(5)).toBe(true);
  });
});
