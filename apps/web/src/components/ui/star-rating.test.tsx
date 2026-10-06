import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { StarRating } from "@/components/ui/star-rating";

function fills(container: HTMLElement): string[] {
  return Array.from(
    container.querySelectorAll<HTMLElement | SVGElement>(
      '[role="img"] > [data-fill]',
    ),
  ).map((star) => star.getAttribute("data-fill") ?? "");
}

describe("StarRating", () => {
  it("draws a 4.5 average as four and a half stars, not five", () => {
    const { container } = render(<StarRating value={4.5} />);

    expect(fills(container)).toEqual(["full", "full", "full", "full", "half"]);
    expect(screen.getByRole("img")).toHaveAccessibleName("4,5 / 5");
  });

  it.each([
    [4.2, ["full", "full", "full", "full", "empty"]],
    [3.8, ["full", "full", "full", "full", "empty"]],
    [3.3, ["full", "full", "full", "half", "empty"]],
    [5, ["full", "full", "full", "full", "full"]],
    [0, ["empty", "empty", "empty", "empty", "empty"]],
  ])("rounds %d to the nearest half star", (value, expected) => {
    const { container } = render(<StarRating value={value} />);

    expect(fills(container)).toEqual(expected);
  });
});
