import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import NotFound from "@/app/not-found";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../test/axe";

describe("NotFound", () => {
  it("names the problem and offers ways on", async () => {
    const { container } = render(<NotFound />);

    expect(
      screen.getByRole("heading", { level: 1, name: t("notFound.title") }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: t("notFound.home") }),
    ).toHaveAttribute("href", "/");
    expect(
      screen.getByRole("link", { name: t("nav.doctors") }),
    ).toHaveAttribute("href", "/doctors");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("has a search that works without JavaScript", () => {
    render(<NotFound />);

    const search = screen.getByRole("search");
    expect(search).toHaveAttribute("action", "/search");
    expect(search).toHaveAttribute("method", "get");
    expect(screen.getByLabelText(t("notFound.searchHint"))).toHaveAttribute(
      "name",
      "q",
    );
    expect(
      screen.getByRole("button", { name: t("nav.searchSubmit") }),
    ).toHaveAttribute("type", "submit");
  });
});
