import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import {
  FuzzySearchNotice,
  suggestionListHref,
} from "@/components/search/fuzzy-search-notice";
import { t, tFormat } from "@/i18n/t";

describe("FuzzySearchNotice", () => {
  it("announces the approximate results and links the suggestion", () => {
    render(
      <FuzzySearchNotice
        q=" domenika "
        suggestion="Доминика"
        suggestionHref="/doctors?q=Доминика"
      />,
    );

    const status = screen.getByRole("status");
    expect(status).toHaveTextContent(
      tFormat("search.fuzzyNoExact", { q: "domenika" }),
    );
    expect(status).toHaveTextContent(`${t("search.didYouMean")} Доминика?`);
    expect(screen.getByRole("link", { name: "Доминика" })).toHaveAttribute(
      "href",
      "/doctors?q=Доминика",
    );
  });

  it("leaves out „Дали мислевте“ without a suggestion", () => {
    render(<FuzzySearchNotice q="domenika" suggestion={null} />);

    expect(screen.queryByRole("link")).toBeNull();
    expect(
      screen.queryByText(t("search.didYouMean"), { exact: false }),
    ).toBeNull();
  });

  it("keeps the other list filters and drops the page", () => {
    expect(
      suggestionListHref(
        "/doctors",
        { q: "domenika", specialty: "pedijatrija", city: "", sort: "" },
        "Доминика",
      ),
    ).toBe(
      `/doctors?q=${encodeURIComponent("Доминика")}&specialty=pedijatrija`,
    );
  });
});
