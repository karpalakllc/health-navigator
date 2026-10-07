import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ForumCategoryToolbar } from "@/components/forum/forum-category-toolbar";
import { ForumTopicList } from "@/components/forum/forum-topic-row";
import { ForumViewChips } from "@/components/forum/forum-view-chips";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

describe("„Без одговор“ rows", () => {
  it("offer an „Одговори“ button to the reply box instead of the count", async () => {
    const { container } = render(
      <ForumTopicList
        label={t("help.listTitle")}
        topics={[
          {
            href: "/forum/opsto/son",
            title: "Лош сон",
            authorName: "ana_m",
            repliesCount: 0,
            lastActivityAt: new Date().toISOString(),
            answerHref: "/forum/opsto/son#forum-reply",
          },
        ]}
      />,
    );

    const row = screen.getByRole("listitem");
    expect(within(row).getByRole("link", { name: "Лош сон" })).toHaveAttribute(
      "href",
      "/forum/opsto/son",
    );
    expect(
      within(row).getByRole("link", {
        name: `${t("help.answer")} на темата „Лош сон“`,
      }),
    ).toHaveAttribute("href", "/forum/opsto/son#forum-reply");
    expect(within(row).queryByText(t("forum.unanswered"))).toBeNull();
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("keep the reply count when no answer link is given", () => {
    render(
      <ForumTopicList
        topics={[
          {
            href: "/forum/opsto/son",
            title: "Лош сон",
            authorName: "ana_m",
            repliesCount: 0,
            lastActivityAt: null,
          },
        ]}
      />,
    );

    expect(screen.getByText(t("forum.unanswered"))).toBeInTheDocument();
    expect(
      screen.queryByRole("link", { name: new RegExp(t("help.answer")) }),
    ).toBeNull();
  });
});

describe("ForumViewChips", () => {
  it("switches between recent and unanswered, marking the current one", () => {
    render(<ForumViewChips current="unanswered" unansweredCount={4} />);

    const nav = screen.getByRole("navigation", { name: t("help.viewLabel") });
    expect(
      within(nav).getByRole("link", { name: t("help.viewLatest") }),
    ).toHaveAttribute("href", "/forum");
    const unanswered = within(nav).getByRole("link", {
      name: "Без одговор (4)",
    });
    expect(unanswered).toHaveAttribute("href", "/forum?view=unanswered");
    expect(unanswered).toHaveAttribute("aria-current", "page");
  });

  it("leaves the count out when it is unknown", () => {
    render(<ForumViewChips current="recent" />);

    expect(
      screen.getByRole("link", { name: t("help.viewUnanswered") }),
    ).not.toHaveAttribute("aria-current");
  });
});

describe("ForumCategoryToolbar", () => {
  it("adds „Без одговор“ and keeps the search", () => {
    render(
      <ForumCategoryToolbar
        categorySlug="opsto"
        currentSort="unanswered"
        searchQuery="сон"
      />,
    );

    const chip = screen.getByRole("link", { name: t("help.viewUnanswered") });
    expect(chip).toHaveAttribute(
      "href",
      `/forum/opsto?q=${encodeURIComponent("сон")}&sort=unanswered`,
    );
    expect(chip).toHaveAttribute("aria-current", "page");
    expect(
      screen.getByRole("link", { name: t("forum.sortLatest") }),
    ).not.toHaveAttribute("aria-current");
  });
});
