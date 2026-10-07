import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ForumPostCard } from "@/components/forum/forum-post-card";
import { AccountLevelProgress } from "@/components/levels/account-level-progress";
import { CommunityContent } from "@/components/levels/community-content";
import { ContributorLevel } from "@/components/levels/contributor-level";
import { ForumReplyHelpfulButton } from "@/components/levels/forum-reply-helpful-button";
import { ReviewList } from "@/components/reviews/review-list";
import type { ForumPost } from "@/lib/api/forum";
import type { Leaderboards, MyLevels } from "@/lib/api/levels";
import type { PublicReview } from "@/lib/api/types";
import { levelTitle, nextStepText } from "@/lib/levels";
import { t, tFormat } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

const RULES: Leaderboards["rules"] = {
  reviews: {
    review_points: 10,
    helpful_points: 2,
    removed_penalty: 20,
    reviews_per_day: 5,
    ladder: [
      { level: 1, reviews: 1, points: 10 },
      { level: 2, reviews: 3, points: 35 },
      { level: 3, reviews: 6, points: 80 },
      { level: 4, reviews: 12, points: 170 },
      { level: 5, reviews: 25, points: 380 },
    ],
  },
  forum: {
    topic_points: 3,
    reply_points: 5,
    helpful_points: 3,
    removed_penalty: 15,
    topics_per_day: 3,
    replies_per_day: 8,
    ladder: [
      { level: 1, posts: 1, points: 3 },
      { level: 2, posts: 5, points: 35 },
      { level: 3, posts: 20, points: 140 },
      { level: 4, posts: 50, points: 380 },
    ],
  },
  helpful_per_item: 10,
  helpful_per_voter_per_author: 3,
  leaderboard_size: 10,
};

function myLevels(overrides: Partial<MyLevels> = {}): MyLevels {
  return {
    shown_publicly: true,
    reviews: {
      level: 1,
      points: 10,
      reviews: 1,
      helpful: 0,
      next: { level: 2, missing_reviews: 2, missing_points: 25 },
    },
    forum: {
      level: 0,
      points: 0,
      topics: 0,
      replies: 0,
      helpful: 0,
      next: { level: 1, missing_posts: 1, missing_points: 3 },
    },
    ...overrides,
  };
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("level titles", () => {
  it("names every level of both ladders and nothing else", () => {
    expect(levelTitle("review", 1)).toBe("Рецензент");
    expect(levelTitle("review", 5)).toBe("Столб на заедницата");
    expect(levelTitle("forum", 2)).toBe("Помошник");
    expect(levelTitle("forum", 4)).toBe("Стожер на форумот");
    expect(levelTitle("forum", 5)).toBeNull();
    expect(levelTitle("review", 0)).toBeNull();
    expect(levelTitle("review", null)).toBeNull();
    expect(levelTitle("review", 1.5)).toBeNull();
  });

  it("says what the next level still needs, in the right number", () => {
    expect(
      nextStepText("review", {
        level: 2,
        missing_reviews: 2,
        missing_points: 25,
      }),
    ).toBe("Уште 2 рецензии и 25 поени до „Активен рецензент“.");
    expect(
      nextStepText("forum", { level: 1, missing_posts: 1, missing_points: 0 }),
    ).toBe("Уште 1 објава до „Соговорник“.");
    expect(
      nextStepText("review", {
        level: 3,
        missing_reviews: 0,
        missing_points: 1,
      }),
    ).toBe("Уште 1 поен до „Посветен рецензент“.");
    expect(nextStepText("review", null)).toBeNull();
  });
});

describe("ContributorLevel chip", () => {
  it("shows the title with a spoken prefix, and nothing without a level", () => {
    const { container, rerender } = render(
      <ContributorLevel ladder="review" level={2} />,
    );

    expect(container).toHaveTextContent(
      `${t("levels.chipPrefix")} Активен рецензент`,
    );

    rerender(<ContributorLevel ladder="review" level={null} />);
    expect(container).toBeEmptyDOMElement();
  });

  it("sits next to the author on reviews and forum posts", () => {
    const review: PublicReview = {
      id: 1,
      rating: 5,
      body: "Одлично.",
      author_name: "ana_p",
      author_level: 3,
      published_at: "2026-09-01T10:00:00+00:00",
      helpful_count: 0,
    };
    render(<ReviewList reviews={[review]} />);
    expect(
      screen.getByRole("article").querySelector("header"),
    ).toHaveTextContent("ana_pЗвање: Посветен рецензент");

    const post: ForumPost = {
      id: 2,
      body: "Пробајте со лекар по општа пракса.",
      author_name: "pomosh",
      author: {
        name: "pomosh",
        member_since: null,
        topics_count: 0,
        posts_count: 6,
        is_team_member: false,
        is_forum_moderator: false,
        level: 2,
      },
      published_at: "2026-09-02T10:00:00+00:00",
    };
    render(<ForumPostCard post={post} />);
    expect(screen.getByText("Помошник")).toBeInTheDocument();
  });
});

describe("ForumReplyHelpfulButton", () => {
  it("toggles a reply's vote and settles on the server's count", async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(
        JSON.stringify({
          data: { helpful_count: 4, has_voted_helpful: true },
        }),
        { status: 200, headers: { "Content-Type": "application/json" } },
      ),
    );
    vi.stubGlobal("fetch", fetchMock);
    const user = userEvent.setup();
    render(
      <ForumReplyHelpfulButton
        postId={9}
        count={3}
        voted={false}
        isLoggedIn
        returnTo="/forum/a/b"
      />,
    );

    const button = screen.getByRole("button", {
      name: tFormat("reviews.helpfulCount", { count: 3 }),
    });
    await user.click(button);

    expect(fetchMock).toHaveBeenCalledWith(
      "/api/forum/posts/helpful",
      expect.objectContaining({ method: "PUT", body: '{"id":9}' }),
    );
    expect(button).toHaveAttribute("aria-pressed", "true");
    expect(button).toHaveTextContent(
      tFormat("reviews.helpfulCount", { count: 4 }),
    );
  });

  it("rolls back and says why when the API refuses", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(
        new Response(JSON.stringify({ errors: { post: ["Не можете."] } }), {
          status: 422,
          headers: { "Content-Type": "application/json" },
        }),
      ),
    );
    const user = userEvent.setup();
    render(
      <ForumReplyHelpfulButton
        postId={9}
        count={0}
        voted={false}
        isLoggedIn
        returnTo="/forum/a/b"
      />,
    );

    const button = screen.getByRole("button", { name: t("reviews.helpful") });
    await user.click(button);

    expect(button).toHaveAttribute("aria-pressed", "false");
    expect(screen.getByRole("status")).toHaveTextContent("Не можете.");
  });

  it("sends a signed-out visitor to sign in", () => {
    render(
      <ForumReplyHelpfulButton
        postId={9}
        count={2}
        voted={false}
        isLoggedIn={false}
        returnTo="/forum/a/b"
      />,
    );

    expect(
      screen.getByRole("link", {
        name: tFormat("reviews.helpfulCount", { count: 2 }),
      }),
    ).toHaveAttribute(
      "href",
      `/login?redirect=${encodeURIComponent("/forum/a/b")}`,
    );
  });
});

describe("AccountLevelProgress", () => {
  it("shows both ladders with points and the next step", async () => {
    const { container } = render(<AccountLevelProgress levels={myLevels()} />);

    const card = screen.getByRole("region", {
      name: t("levels.progressTitle"),
    });
    expect(within(card).getByText("Рецензент")).toBeInTheDocument();
    expect(
      within(card).getByText(
        "Уште 2 рецензии и 25 поени до „Активен рецензент“.",
      ),
    ).toBeInTheDocument();
    expect(within(card).getByText(t("levels.noLevelYet"))).toBeInTheDocument();
    expect(
      within(card).getByText("Уште 1 објава и 3 поени до „Соговорник“."),
    ).toBeInTheDocument();
    expect(
      within(card).getByRole("link", { name: t("levels.howLink") }),
    ).toHaveAttribute("href", "/community#zvanja");
    expect(within(card).queryByText(t("levels.notShown"))).toBeNull();
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("thanks a member at the top and says when the title is not shown", () => {
    render(
      <AccountLevelProgress
        levels={myLevels({
          shown_publicly: false,
          reviews: {
            level: 5,
            points: 500,
            reviews: 30,
            helpful: 80,
            next: null,
          },
        })}
      />,
    );

    expect(screen.getByText(t("levels.topLevel"))).toBeInTheDocument();
    expect(screen.getByText(t("levels.notShown"))).toBeInTheDocument();
  });
});

describe("CommunityContent", () => {
  const boards: Leaderboards = {
    month: "2026-09",
    reviewers: [
      { username: "najdobar", level: 2, points: 44, reviews: 4, helpful: 2 },
      { username: "vtor", level: 1, points: 10, reviews: 1, helpful: 1 },
    ],
    forum: [],
    rules: RULES,
  };

  it("lists last month's members in order, with titles and the real rules", async () => {
    const { container } = render(<CommunityContent boards={boards} />);

    const reviewers = screen.getByRole("region", {
      name: t("levels.reviewersTitle"),
    });
    expect(
      within(reviewers).getByText("За септември 2026"),
    ).toBeInTheDocument();
    const rows = within(reviewers).getAllByRole("listitem");
    expect(rows[0]).toHaveTextContent("najdobar");
    expect(rows[0]).toHaveTextContent("Активен рецензент");
    expect(rows[0]).toHaveTextContent("4 рецензии · 2 пати „Корисно“");
    expect(rows[1]).toHaveTextContent("1 рецензија · 1 пат „Корисно“");
    // The rank is read as text (aria-label on a plain span is not announced).
    expect(
      within(rows[0]).getByText(tFormat("levels.rankLabel", { rank: 1 })),
    ).toHaveClass("sr-only");
    expect(rows[0].querySelector("[aria-label]")).toBeNull();

    const forum = screen.getByRole("region", { name: t("levels.forumTitle") });
    expect(within(forum).getByText(t("levels.emptyForum"))).toBeInTheDocument();

    expect(
      screen.getByText(
        "Објавена рецензија: 10 поени (се бројат до 5 рецензии на ден).",
      ),
    ).toBeInTheDocument();
    const forumLadder = screen.getByRole("region", {
      name: t("levels.ladderForumTitle"),
    });
    expect(
      within(forumLadder).getByRole("row", { name: /Стожер на форумот/ }),
    ).toHaveTextContent("50 објави и 380 поени");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("leaves the forum out while the module is off", () => {
    render(<CommunityContent boards={{ ...boards, forum: null }} />);

    expect(
      screen.queryByRole("region", { name: t("levels.forumTitle") }),
    ).toBeNull();
    expect(
      screen.queryByRole("region", { name: t("levels.ladderForumTitle") }),
    ).toBeNull();
    expect(screen.queryByText(/Одговор во туѓа тема/)).toBeNull();
  });

  it("says so calmly when the lists cannot load", () => {
    render(<CommunityContent boards={null} />);

    expect(screen.getByText(t("levels.unavailable"))).toBeInTheDocument();
    expect(
      screen.getByRole("heading", { level: 1, name: t("levels.heroTitle") }),
    ).toBeInTheDocument();
  });
});
