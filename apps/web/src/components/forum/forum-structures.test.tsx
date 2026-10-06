import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import {
  ForumPostCard,
  isSameAuthor,
} from "@/components/forum/forum-post-card";
import { ForumSafetyNotice } from "@/components/forum/forum-safety-notice";
import { ModerationStatusTag } from "@/components/account/moderation-status-tag";
import { ForumTopicList } from "@/components/forum/forum-topic-row";
import type { ForumAuthor, ForumPost } from "@/lib/api/forum";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

const member: ForumAuthor = {
  name: "Ана М.",
  member_since: "2025-03-01T10:00:00+00:00",
  topics_count: 2,
  posts_count: 5,
  is_team_member: false,
  is_forum_moderator: false,
};

function post(
  author: ForumAuthor,
  body = "Мерам наутро и навечер.",
): ForumPost {
  return {
    id: 1,
    body,
    author_name: author.name,
    author,
    published_at: new Date(Date.now() - 5 * 3600 * 1000).toISOString(),
  };
}

describe("ForumSafetyNotice", () => {
  it("says support, not advice, with 194 and 112 as tel: links", () => {
    render(<ForumSafetyNotice />);

    expect(screen.getByText(/не за медицински совет/)).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "194" })).toHaveAttribute(
      "href",
      "tel:194",
    );
    expect(screen.getByRole("link", { name: "112" })).toHaveAttribute(
      "href",
      "tel:112",
    );
  });

  it("is never an emergency-red surface", () => {
    const { container } = render(<ForumSafetyNotice />);

    const markup = container.innerHTML;
    expect(markup).not.toMatch(/emergency|destructive|bg-red|text-red/);
    expect(container.firstElementChild).toHaveClass("bg-chip-tint");
  });
});

describe("ForumTopicList", () => {
  const topics = [
    {
      href: "/forum/srce/pritisok",
      title: "Колку често да мерам притисок дома?",
      categoryName: "Срце и крвен притисок",
      authorName: "Ана М.",
      repliesCount: 4,
      lastActivityAt: new Date(Date.now() - 2 * 86400 * 1000).toISOString(),
    },
    {
      href: "/forum/srce/sol",
      title: "Сол во исхраната",
      authorName: "Петар Д.",
      repliesCount: 1,
      lastActivityAt: null,
      isPinned: true,
    },
    {
      href: "/forum/srce/aparat",
      title: "Кој апарат е поточен?",
      authorName: "Снежана В.",
      repliesCount: 0,
      lastActivityAt: null,
      isLocked: true,
    },
  ];

  it("is a labelled list with one item and one heading link per topic", () => {
    render(<ForumTopicList topics={topics} label="Неодамнешни дискусии" />);

    const list = screen.getByRole("list", { name: "Неодамнешни дискусии" });
    const items = within(list).getAllByRole("listitem");
    expect(items).toHaveLength(3);

    const heading = within(items[0]!).getByRole("heading", { level: 3 });
    expect(within(heading).getByRole("link")).toHaveAttribute(
      "href",
      "/forum/srce/pritisok",
    );
    expect(heading).toHaveAccessibleName("Колку често да мерам притисок дома?");
    expect(items[0]).toHaveTextContent("Срце и крвен притисок");
    expect(items[0]).toHaveTextContent("Ана М.");
    expect(items[0]).toHaveTextContent("пред 2 дена");
  });

  it("shows the reply count in the right number, or „Без одговор“", () => {
    render(<ForumTopicList topics={topics} />);
    const [many, one, none] = screen.getAllByRole("listitem");

    expect(many).toHaveTextContent(/4\s*одговори/);
    expect(one).toHaveTextContent(/1\s*одговор(?!и)/);
    expect(none).toHaveTextContent(t("forum.unanswered"));
    expect(within(none!).queryByText("0")).not.toBeInTheDocument();
  });

  it("marks pinned and locked topics", () => {
    render(<ForumTopicList topics={topics} />);
    const [, pinned, locked] = screen.getAllByRole("listitem");

    expect(pinned).toHaveTextContent(t("forum.pinned"));
    expect(locked).toHaveTextContent(t("forum.locked"));
  });

  it("can render the titles as h2 (a list straight under the page h1)", () => {
    render(<ForumTopicList topics={topics} headingLevel={2} />);

    expect(screen.getAllByRole("heading", { level: 2 })).toHaveLength(3);
  });

  it("has no serious accessibility violations", async () => {
    const { container } = render(
      <ForumTopicList topics={topics} label="Теми" />,
    );

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});

describe("ForumPostCard", () => {
  it("shows the public name, a monogram from it and the relative time", () => {
    render(<ForumPostCard post={post(member)} />);

    const article = screen.getByRole("article");
    expect(article).toHaveTextContent("Ана М.");
    expect(article).toHaveTextContent("АМ");
    expect(within(article).getByText("пред 5 часа")).toHaveAttribute(
      "datetime",
    );
  });

  it("shows the member-since year and the post count under the name", () => {
    render(<ForumPostCard post={post(member)} />);

    // 2 topics + 5 replies = 7 posts.
    expect(screen.getByText("Член од 2025 · 7 објави")).toBeInTheDocument();
  });

  it("uses the singular for one post and drops a missing join date", () => {
    render(
      <ForumPostCard
        post={post({
          ...member,
          member_since: null,
          topics_count: 0,
          posts_count: 21,
        })}
      />,
    );

    expect(screen.getByText("21 објава")).toBeInTheDocument();
    expect(screen.queryByText(/Член од/)).not.toBeInTheDocument();
  });

  it("reads the join year in Skopje time (new year's eve UTC)", () => {
    render(
      <ForumPostCard
        post={post({
          ...member,
          member_since: "2024-12-31T23:30:00+00:00",
          topics_count: 1,
          posts_count: 0,
        })}
      />,
    );

    expect(screen.getByText("Член од 2025 · 1 објава")).toBeInTheDocument();
  });

  it("highlights a team member's reply with the care border and tag", () => {
    render(<ForumPostCard post={post({ ...member, is_team_member: true })} />);

    const article = screen.getByRole("article");
    expect(article).toHaveClass("border", "border-care");
    expect(article).toHaveTextContent(t("forum.authorTeam"));
  });

  it("highlights a forum moderator's reply too", () => {
    render(
      <ForumPostCard post={post({ ...member, is_forum_moderator: true })} />,
    );

    const article = screen.getByRole("article");
    expect(article).toHaveClass("border-care");
    expect(article).toHaveTextContent(t("forum.authorForumModerator"));
  });

  it("leaves a member's reply plain", () => {
    render(<ForumPostCard post={post(member)} />);

    const article = screen.getByRole("article");
    expect(article).not.toHaveClass("border-care");
    expect(article).not.toHaveTextContent(t("forum.authorTeam"));
    expect(article).not.toHaveTextContent(t("forum.authorBadge"));
  });

  it("tags the topic's author and edges the opening post", () => {
    render(<ForumPostCard post={post(member)} isOriginalPost isTopicAuthor />);

    const article = screen.getByRole("article");
    expect(article).toHaveClass("card-edge");
    expect(article).toHaveTextContent(t("forum.authorBadge"));
  });
});

describe("isSameAuthor", () => {
  it("needs the same public name and the same account creation time", () => {
    expect(isSameAuthor(member, { ...member })).toBe(true);
    expect(
      isSameAuthor(member, {
        ...member,
        member_since: "2025-04-01T10:00:00+00:00",
      }),
    ).toBe(false);
    expect(isSameAuthor(member, { ...member, name: "Ана Т." })).toBe(false);
    expect(
      isSameAuthor(
        { ...member, member_since: null },
        { ...member, member_since: null },
      ),
    ).toBe(false);
  });
});

describe("ModerationStatusTag (forum activity)", () => {
  it.each([
    ["pending", t("account.statusPending")],
    ["approved", t("account.statusApproved")],
    ["rejected", t("account.statusRejected")],
  ])("labels %s", (status, label) => {
    render(<ModerationStatusTag status={status} />);

    expect(screen.getByText(label)).toBeInTheDocument();
  });
});
