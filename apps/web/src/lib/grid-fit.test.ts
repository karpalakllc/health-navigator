import { describe, expect, it } from "vitest";
import { fitToColumns } from "@/lib/grid-fit";

describe("fitToColumns", () => {
  it.each([
    // total, columns, max → shown
    [8, 2, 6, 6],
    [5, 2, 6, 4],
    [7, 2, 6, 6],
    [1, 2, 6, 1],
    [5, 4, 8, 4],
    [8, 4, 8, 8],
    [9, 4, 8, 8],
    [3, 4, 8, 3],
    [7, 3, 8, 6],
    [0, 2, 6, 0],
  ])("%i cards, %i columns, max %i → %i", (total, columns, max, shown) => {
    expect(fitToColumns(total, columns, max)).toBe(shown);
  });
});
