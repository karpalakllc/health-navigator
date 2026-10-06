import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { HeaderSearch } from "@/components/layout/header-search";
import { t } from "@/i18n/t";

describe("HeaderSearch", () => {
  it("is a named search landmark (pages with their own search add a second one)", () => {
    render(<HeaderSearch />);

    expect(
      screen.getByRole("search", { name: t("nav.searchLandmark") }),
    ).toHaveAttribute("action", "/search");
  });
});
