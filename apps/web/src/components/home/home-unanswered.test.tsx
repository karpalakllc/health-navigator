import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { HomeUnanswered } from "@/components/home/home-unanswered";
import { REPLY_FORM_ID } from "@/components/forum/reply-form";
import type { ForumTopicSearchItem } from "@/lib/api/forum";
import { FORUM_REPLY_ANCHOR, forumAnswerHref } from "@/lib/forum/answer-link";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

function topic(slug: string, title = `Прашање ${slug}`): ForumTopicSearchItem {
  return {
    slug,
    title,
    author_name: "ana_m",
    replies_count: 0,
    last_post_at: "2026-10-06T08:00:00Z",
    published_at: "2026-10-06T08:00:00Z",
    category: { slug: "opsto", name: "Општо" },
  };
}

describe("forumAnswerHref", () => {
  it("points at the topic's reply box, whose id the reply form uses", () => {
    expect(FORUM_REPLY_ANCHOR).toBe(REPLY_FORM_ID);
    expect(forumAnswerHref("opsto", "glavobolka")).toBe(
      "/forum/opsto/glavobolka#forum-reply",
    );
  });
});

describe("HomeUnanswered („Помогнете некому“)", () => {
  it("lists up to three questions, each with an „Одговори“ button to its reply box", async () => {
    const { container } = render(
      <HomeUnanswered
        topics={["a", "b", "c", "d"].map((slug) => topic(slug))}
      />,
    );

    const section = screen.getByRole("region", { name: t("help.homeTitle") });
    const items = within(section).getAllByRole("listitem");
    expect(items).toHaveLength(3);

    const first = items[0];
    expect(
      within(first).getByRole("link", { name: "Прашање a" }),
    ).toHaveAttribute("href", "/forum/opsto/a");
    // The visible label starts the accessible name (WCAG 2.5.3), which
    // then says which topic.
    expect(
      within(first).getByRole("link", {
        name: `${t("help.answer")} на темата „Прашање a“`,
      }),
    ).toHaveAttribute("href", "/forum/opsto/a#forum-reply");
    expect(within(first).getByText(/Прашува ana_m/)).toBeInTheDocument();

    expect(
      within(section).getByRole("link", { name: t("help.homeAll") }),
    ).toHaveAttribute("href", "/forum?view=unanswered");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("skips topics the community band already shows", () => {
    render(
      <HomeUnanswered
        topics={[topic("a"), topic("b")]}
        exclude={["opsto/a"]}
      />,
    );

    expect(screen.getAllByRole("listitem")).toHaveLength(1);
    expect(
      screen.queryByRole("link", { name: "Прашање a" }),
    ).not.toBeInTheDocument();
  });

  it("is not rendered when nothing is left to show", () => {
    const { container } = render(
      <HomeUnanswered topics={[topic("a")]} exclude={["opsto/a"]} />,
    );

    expect(container).toBeEmptyDOMElement();
    expect(
      render(<HomeUnanswered topics={[]} />).container,
    ).toBeEmptyDOMElement();
  });
});
