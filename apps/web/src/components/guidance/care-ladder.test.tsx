import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import {
  CARE_LEVELS,
  CareLadder,
  careLevelForOutcome,
} from "@/components/guidance/care-ladder";
import { t } from "@/i18n/t";

describe("careLevelForOutcome", () => {
  it.each([
    ["general_information", "home"],
    ["self_care", "home"],
    ["pharmacy", "pharmacy"],
    ["seek_care_soon", "gp"],
    ["see_gp", "gp"],
    ["emergency", "emergency"],
  ] as const)("places %s on the %s rung", (code, level) => {
    expect(careLevelForOutcome(code)).toBe(level);
  });

  it.each(["custom_admin", "", "toString", "__proto__", "constructor"])(
    "places an unknown code (%j) on no rung rather than guessing",
    (code) => {
      expect(careLevelForOutcome(code)).toBeNull();
    },
  );

  it("orders the rungs from the least to the most urgent", () => {
    expect(CARE_LEVELS).toEqual(["home", "pharmacy", "gp", "emergency"]);
  });
});

describe("CareLadder", () => {
  it("gives every rung an icon-independent name and marks one current step", () => {
    render(<CareLadder current="pharmacy" pharmaciesOn />);

    const list = screen.getByRole("list", { name: t("guidance.ladderTitle") });
    const rungs = within(list).getAllByRole("listitem");
    expect(rungs).toHaveLength(4);
    expect(rungs[1]).toHaveAttribute("aria-current", "step");
    expect(rungs[1]).toHaveTextContent(t("guidance.ladderYourResult"));
    for (const rung of [rungs[0], rungs[2], rungs[3]]) {
      expect(rung).not.toHaveAttribute("aria-current");
    }
    expect(
      within(rungs[1]).getByRole("link", {
        name: t("guidance.ladderPharmacyLink"),
      }),
    ).toHaveAttribute("href", "/pharmacies");
  });
});
