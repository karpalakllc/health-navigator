import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { Pagination } from "@/components/directory/pagination";
import { t } from "@/i18n/t";

describe("Pagination", () => {
  it("is a navigation landmark named in Macedonian", () => {
    render(
      <Pagination
        basePath="/doctors"
        currentPage={2}
        lastPage={3}
        total={30}
        searchParams={{ city: "Скопје" }}
      />,
    );

    expect(
      screen.getByRole("navigation", { name: t("pagination.label") }),
    ).toBeInTheDocument();
    expect(t("pagination.label")).toBe("Страници со резултати");
    expect(
      screen.getByRole("link", { name: t("pagination.next") }),
    ).toHaveAttribute(
      "href",
      `/doctors?city=${encodeURIComponent("Скопје")}&page=3`,
    );
  });
});
