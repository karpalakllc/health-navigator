import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";
import { describe, expect, it, vi } from "vitest";
import { StarRatingInput } from "@/components/reviews/star-rating-input";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

function Controlled({
  initial = null,
  onChange = () => {},
}: {
  initial?: number | null;
  onChange?: (value: number) => void;
}) {
  const [value, setValue] = useState<number | null>(initial);

  return (
    <StarRatingInput
      value={value}
      onChange={(next) => {
        setValue(next);
        onChange(next);
      }}
    />
  );
}

function checked() {
  return screen
    .getAllByRole("radio")
    .filter((radio) => radio.getAttribute("aria-checked") === "true")
    .map((radio) => radio.getAttribute("aria-label"));
}

describe("StarRatingInput", () => {
  it("is a named, required radio group of five stars", () => {
    render(<Controlled />);

    const group = screen.getByRole("radiogroup", { name: t("reviews.rating") });
    expect(group).toHaveAttribute("aria-required", "true");
    expect(screen.getAllByRole("radio")).toHaveLength(5);
  });

  it("uses Macedonian singular and plural star labels", () => {
    render(<Controlled />);

    expect(
      screen.getByRole("radio", { name: "1 ѕвезда од 5" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("radio", { name: "2 ѕвезди од 5" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("radio", { name: "5 ѕвезди од 5" }),
    ).toBeInTheDocument();
  });

  it("starts with nothing selected and the first star as the only tab stop", () => {
    render(<Controlled />);

    expect(checked()).toEqual([]);
    const radios = screen.getAllByRole("radio");
    expect(radios.map((r) => r.tabIndex)).toEqual([0, -1, -1, -1, -1]);
    expect(screen.getByText(t("reviews.ratingNone"))).toBeInTheDocument();
  });

  it("selects on click and moves the tab stop to the choice", async () => {
    const onChange = vi.fn();
    render(<Controlled onChange={onChange} />);

    await userEvent
      .setup()
      .click(screen.getByRole("radio", { name: "3 ѕвезди од 5" }));

    expect(onChange).toHaveBeenCalledWith(3);
    expect(checked()).toEqual(["3 ѕвезди од 5"]);
    expect(screen.getAllByRole("radio").map((r) => r.tabIndex)).toEqual([
      -1, -1, 0, -1, -1,
    ]);
  });

  it("tabs into the group once, then arrow keys move and select with wrap", async () => {
    const user = userEvent.setup();
    render(
      <>
        <button type="button">пред</button>
        <Controlled />
        <button type="button">после</button>
      </>,
    );

    await user.click(screen.getByRole("button", { name: "пред" }));
    await user.tab();
    expect(screen.getByRole("radio", { name: "1 ѕвезда од 5" })).toHaveFocus();
    // Focus alone does not choose a rating.
    expect(checked()).toEqual([]);

    await user.keyboard("{ArrowRight}");
    expect(checked()).toEqual(["2 ѕвезди од 5"]);
    expect(screen.getByRole("radio", { name: "2 ѕвезди од 5" })).toHaveFocus();

    await user.keyboard("{ArrowUp}");
    expect(checked()).toEqual(["3 ѕвезди од 5"]);

    await user.keyboard("{ArrowLeft}{ArrowDown}");
    expect(checked()).toEqual(["1 ѕвезда од 5"]);

    await user.keyboard("{ArrowLeft}");
    expect(checked()).toEqual(["5 ѕвезди од 5"]);
    await user.keyboard("{ArrowRight}");
    expect(checked()).toEqual(["1 ѕвезда од 5"]);

    // One tab stop: Tab leaves the group.
    await user.tab();
    expect(screen.getByRole("button", { name: "после" })).toHaveFocus();
  });

  it("jumps to the ends with Home and End", async () => {
    const user = userEvent.setup();
    render(<Controlled initial={3} />);

    screen.getByRole("radio", { name: "3 ѕвезди од 5" }).focus();
    await user.keyboard("{End}");
    expect(checked()).toEqual(["5 ѕвезди од 5"]);
    expect(screen.getByRole("radio", { name: "5 ѕвезди од 5" })).toHaveFocus();

    await user.keyboard("{Home}");
    expect(checked()).toEqual(["1 ѕвезда од 5"]);
    expect(screen.getByRole("radio", { name: "1 ѕвезда од 5" })).toHaveFocus();
  });

  it("points the invalid group at its error message", () => {
    render(
      <>
        <StarRatingInput
          value={null}
          onChange={() => {}}
          invalid
          errorId="err"
        />
        <p id="err">{t("reviews.ratingRequired")}</p>
      </>,
    );

    const group = screen.getByRole("radiogroup");
    expect(group).toHaveAttribute("aria-invalid", "true");
    expect(group).toHaveAccessibleDescription(t("reviews.ratingRequired"));
  });

  it("has no serious accessibility violations", async () => {
    const { container } = render(<Controlled initial={4} />);

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
