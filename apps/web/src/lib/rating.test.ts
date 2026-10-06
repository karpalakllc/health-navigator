import { describe, expect, it } from "vitest";
import {
  formatRating,
  isValidReviewRating,
  ratingLabel,
  starRatingLabel,
} from "@/lib/rating";

describe("ratingLabel", () => {
  it("keeps one decimal instead of rounding 4.5 up to 5", () => {
    expect(ratingLabel(4.5)).toBe("4,5 / 5");
    expect(ratingLabel(4.46)).toBe("4,5 / 5");
  });

  it("always shows one decimal, like every other rating on the site", () => {
    expect(ratingLabel(4)).toBe("4,0 / 5");
    expect(ratingLabel(3.96)).toBe("4,0 / 5");
  });

  it("clamps to the scale", () => {
    expect(ratingLabel(7)).toBe("5,0 / 5");
    expect(ratingLabel(-1)).toBe("0,0 / 5");
  });
});

describe("formatRating", () => {
  it("writes one decimal with a comma, including whole numbers", () => {
    expect(formatRating(5)).toBe("5,0");
    expect(formatRating(4.66)).toBe("4,7");
    expect(formatRating(4.04)).toBe("4,0");
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

describe("starRatingLabel", () => {
  it("names the unit with the right Macedonian number", () => {
    expect(starRatingLabel(1)).toBe("1 ѕвезда од 5");
    expect(starRatingLabel(2)).toBe("2 ѕвезди од 5");
    expect(starRatingLabel(5)).toBe("5 ѕвезди од 5");
  });
});
