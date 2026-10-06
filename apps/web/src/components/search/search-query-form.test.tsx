import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { SearchQueryForm } from "@/components/search/search-query-form";
import { t } from "@/i18n/t";

function queryInput() {
  return screen.getByRole("searchbox", { name: t("search.queryLabel") });
}

describe("SearchQueryForm (/search)", () => {
  it("keeps the query field visible instead of behind the mobile filter toggle", () => {
    render(<SearchQueryForm />);

    // The collapsed FilterForm hid its fields with `hidden lg:block` until
    // "Филтрирај" was pressed, so the hub had nothing to type into on mobile.
    expect(
      screen.queryByRole("button", { name: t("common.filter") }),
    ).not.toBeInTheDocument();
    expect(queryInput().closest(".hidden")).toBeNull();
    expect(
      screen
        .getByRole("button", { name: t("common.search") })
        .closest(".hidden"),
    ).toBeNull();
  });

  it("focuses the query on the hub so typing after the header icon works", () => {
    render(<SearchQueryForm autoFocus />);

    expect(queryInput()).toHaveFocus();
  });

  it("prefills the current query on the results page for refining", () => {
    render(<SearchQueryForm q="кардио" city="Скопје" />);

    expect(queryInput()).toHaveValue("кардио");
    expect(queryInput()).not.toHaveFocus();
    expect(queryInput().closest("form")).toHaveAttribute("action", "/search");
  });
});
