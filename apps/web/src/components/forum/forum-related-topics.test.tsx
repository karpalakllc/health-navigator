import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ForumRelatedTopics } from "@/components/forum/forum-related-topics";
import { ForumTagRow } from "@/components/forum/forum-tag-row";
import { seriousA11yViolations } from "../../../test/axe";

describe("ForumRelatedTopics", () => {
  it("links each topic into its own category, naming other categories", () => {
    render(
      <ForumRelatedTopics
        categorySlug="hirurgija"
        topics={[
          {
            slug: "laser-ili-operacija",
            title: "Ласер или операција",
            replies_count: 3,
            category: { slug: "kardiologija", name: "Кардиологија" },
          },
          { slug: "chorapi", title: "Чорапи за вени", replies_count: 1 },
        ]}
      />,
    );

    expect(
      screen.getByRole("link", { name: "Ласер или операција" }),
    ).toHaveAttribute("href", "/forum/kardiologija/laser-ili-operacija");
    expect(screen.getByText(/Кардиологија/)).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Чорапи за вени" }),
    ).toHaveAttribute("href", "/forum/hirurgija/chorapi");
  });

  it("renders nothing on a profile without linked topics", () => {
    const { container } = render(
      <ForumRelatedTopics topics={[]} title="Од форумот" hideWhenEmpty />,
    );

    expect(container).toBeEmptyDOMElement();
  });

  it("is an accessible, labelled section", async () => {
    const { container } = render(
      <ForumRelatedTopics
        title="Од форумот"
        lead="Искуства на членови."
        headingId="doctor-forum-heading"
        topics={[
          {
            slug: "a",
            title: "Тема",
            replies_count: 0,
            category: { slug: "c", name: "Ц" },
          },
        ]}
      />,
    );

    expect(
      screen.getByRole("region", { name: "Од форумот" }),
    ).toBeInTheDocument();
    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});

describe("ForumTagRow", () => {
  it("lists keywords as links to their tag pages", () => {
    render(
      <ForumTagRow
        tags={[
          {
            name: "проширени вени",
            slug: "prosireni-veni",
            latin: "prosireni veni",
          },
        ]}
      />,
    );

    expect(
      screen.getByRole("navigation", { name: "Клучни зборови" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "проширени вени" }),
    ).toHaveAttribute("href", "/forum/tags/prosireni-veni");
  });

  it("renders nothing without keywords", () => {
    const { container } = render(<ForumTagRow tags={[]} />);

    expect(container).toBeEmptyDOMElement();
  });
});
