import { act, render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it } from "vitest";
import { ForumAcceptButton } from "@/components/forum/forum-accept-button";
import { ForumAcceptedAnswerCard } from "@/components/forum/forum-accepted-answer";
import { ForumFollowButton } from "@/components/forum/forum-follow-button";
import { ForumNewTopicComposer } from "@/components/forum/forum-new-topic-composer";
import { ForumPostCard } from "@/components/forum/forum-post-card";
import { ForumQuoteButton } from "@/components/forum/forum-quote-button";
import {
  ForumNewActivityTag,
  ForumNewReplyTag,
  ForumTopicVisit,
} from "@/components/forum/forum-seen-markers";
import { ReplyForm } from "@/components/forum/reply-form";
import type { ForumPost } from "@/lib/api/forum";
import { saveDraft } from "@/lib/forum/drafts";
import { markTopicSeen } from "@/lib/forum/seen-topics";
import { notificationLine } from "@/lib/notification-text";
import { t, tFormat } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { mockFetch, requestBody } from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

afterEach(() => {
  window.localStorage.clear();
});

const author = {
  name: "ana_m",
  member_since: null,
  topics_count: 1,
  posts_count: 4,
  is_team_member: false,
  is_forum_moderator: false,
};

function post(overrides: Partial<ForumPost> = {}): ForumPost {
  return {
    id: 7,
    body: "> marko напиша:\n> Колку вода?\n\nОколу два литри дневно.",
    author_name: "ana_m",
    author,
    published_at: "2026-10-22T10:00:00Z",
    ...overrides,
  };
}

describe("forum post upgrades", () => {
  it("shows quotes as a quote block and pasted profiles as cards", async () => {
    const { container } = render(
      <ForumPostCard
        post={post({
          profile_cards: [
            {
              kind: "doctor",
              slug: "ana-petrova",
              name: "д-р Ана Петрова",
              path: "/doctors/ana-petrova",
              subtitle: "Кардиологија",
              city: "Битола",
            },
            {
              kind: "pharmacy",
              slug: "x",
              name: "Лоша",
              path: "//evil.example/x",
              subtitle: null,
              city: null,
            },
          ],
        })}
        isAccepted
      />,
    );

    const quote = container.querySelector("blockquote");
    expect(quote).toHaveTextContent("marko напиша: Колку вода?");
    expect(screen.getByText("Околу два литри дневно.")).toBeInTheDocument();

    const cards = screen.getByRole("list", {
      name: t("forum.profileCardsLabel"),
    });
    const links = within(cards).getAllByRole("link");
    // Only same-site paths become links.
    expect(links).toHaveLength(1);
    expect(links[0]).toHaveAttribute("href", "/doctors/ana-petrova");
    expect(links[0]).toHaveTextContent("Лекар · Кардиологија · Битола");

    expect(screen.getByText(t("forum.acceptedAnswer"))).toBeInTheDocument();
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("puts the most helpful answer at the top with a link to it", async () => {
    const { container } = render(
      <ForumAcceptedAnswerCard
        answer={{
          id: 9,
          excerpt: "Околу 30 ml на килограм.",
          author_name: "pomosnik",
          path: "/forum/opsto/voda?page=2#post-9",
        }}
      />,
    );

    expect(
      screen.getByRole("heading", { name: t("forum.acceptedAnswer") }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(tFormat("forum.acceptedAnswerBy", { name: "pomosnik" })),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: t("forum.acceptedAnswerOpen") }),
    ).toHaveAttribute("href", "/forum/opsto/voda?page=2#post-9");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("marks and unmarks the answer, then refreshes the thread", async () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    render(
      <ForumAcceptButton
        categorySlug="opsto"
        topicSlug="voda"
        postId={9}
        accepted={false}
      />,
    );

    await userEvent
      .setup()
      .click(screen.getByRole("button", { name: t("forum.markAccepted") }));

    expect(fetch.mock.calls[0][0]).toBe("/api/forum/topics/accepted-answer");
    expect(fetch.mock.calls[0][1]?.method).toBe("PUT");
    expect(requestBody(fetch)).toEqual({
      categorySlug: "opsto",
      topicSlug: "voda",
      postId: 9,
    });
    expect(router.refresh).toHaveBeenCalled();
  });
});

describe("following a topic", () => {
  it("toggles with aria-pressed and rolls back on a refusal", async () => {
    const fetch = mockFetch(
      { status: 200, body: { data: { following: true } } },
      { status: 500, body: {} },
    );
    const { container } = render(
      <ForumFollowButton
        categorySlug="opsto"
        topicSlug="voda"
        following={false}
        returnTo="/forum/opsto/voda"
      />,
    );
    const user = userEvent.setup();
    const button = screen.getByRole("button", { name: t("forum.follow") });

    expect(button).toHaveAttribute("aria-pressed", "false");
    expect(button).toHaveAccessibleDescription(t("forum.followHint"));
    expect(await seriousA11yViolations(container)).toEqual([]);

    await user.click(button);
    expect(button).toHaveAttribute("aria-pressed", "true");
    expect(fetch.mock.calls[0][1]?.method).toBe("PUT");

    await user.click(button);
    expect(fetch.mock.calls[1][1]?.method).toBe("DELETE");
    // The server refused: still following.
    expect(button).toHaveAttribute("aria-pressed", "true");
    expect(screen.getByRole("status")).toHaveTextContent(
      t("forum.followError"),
    );
  });

  it("names the topic in the notification line and links to the reply", () => {
    expect(
      notificationLine({
        id: 1,
        type: "forum_reply",
        data: { title: "Вода", path: "/forum/opsto/voda#post-3", count: 1 },
        read: false,
        created_at: null,
      }),
    ).toEqual({
      text: tFormat("notifications.item.forumReply", { title: "Вода" }),
      href: "/forum/opsto/voda#post-3",
    });
    expect(
      notificationLine({
        id: 2,
        type: "forum_reply",
        data: { title: "Вода", path: "https://evil.example", count: 3 },
        read: false,
        created_at: null,
      }),
    ).toEqual({
      text: tFormat("notifications.item.forumReplies", {
        title: "Вода",
        count: 3,
      }),
      href: null,
    });
  });
});

describe("drafts and quoting", () => {
  it("brings back an unsent reply, adds quotes and forgets it once sent", async () => {
    saveDraft("reply:/forum/srce/pritisok", { body: "Мојот незавршен" });
    const fetch = mockFetch({
      status: 201,
      body: { data: { status: "approved" } },
    });
    render(
      <>
        <ForumQuoteButton authorName="marko" body="Пијте вода." />
        <ReplyForm categorySlug="srce" topicSlug="pritisok" />
      </>,
    );
    const user = userEvent.setup();
    const field = screen.getByRole("textbox");

    expect(field).toHaveValue("Мојот незавршен");
    expect(screen.getByText(t("forum.draftRestored"))).toBeInTheDocument();

    await user.click(
      screen.getByRole("button", {
        name: tFormat("forum.quoteAria", { name: "marko" }),
      }),
    );
    expect(field).toHaveValue(
      "Мојот незавршен\n\n> marko напиша:\n> Пијте вода.\n\n",
    );
    expect(field).toHaveFocus();

    await user.click(
      screen.getByRole("button", { name: t("forum.replySubmit") }),
    );
    expect(requestBody(fetch).body).toContain("> Пијте вода.");
    expect(field).toHaveValue("");
    expect(window.localStorage.getItem("z360:forum-drafts:v1")).toBeNull();
  });

  it("keeps a new topic while it is written and can discard it", async () => {
    saveDraft("topic:new", { title: "Наслов од претходно", body: "Текст" });
    render(
      <ForumNewTopicComposer
        categories={[{ slug: "opsto", name: "Општо", description: null }]}
        settings={{
          forum_rules_enabled: false,
          forum_rules_title: null,
          forum_rules_body: null,
        }}
      />,
    );

    expect(screen.getByDisplayValue("Наслов од претходно")).toBeInTheDocument();

    await userEvent
      .setup()
      .click(screen.getByRole("button", { name: t("forum.draftDiscard") }));
    expect(screen.queryByDisplayValue("Наслов од претходно")).toBeNull();
    expect(window.localStorage.getItem("z360:forum-drafts:v1")).toBeNull();
  });
});

describe("new since the last visit", () => {
  it("marks replies after the previous visit and topics active since", () => {
    markTopicSeen("/forum/opsto/voda", "2026-10-21T10:00:00Z");

    const { unmount } = render(
      <>
        <ForumTopicVisit
          path="/forum/opsto/voda"
          lastActivityAt="2026-10-22T12:00:00Z"
        />
        <ForumNewReplyTag
          topicPath="/forum/opsto/voda"
          publishedAt="2026-10-20T10:00:00Z"
        />
        <ForumNewReplyTag
          topicPath="/forum/opsto/voda"
          publishedAt="2026-10-22T12:00:00Z"
        />
      </>,
    );

    // Only the reply newer than the previous visit.
    expect(screen.getAllByText(t("forum.newSinceVisit"))).toHaveLength(1);
    act(() => unmount());

    // This visit is remembered: the same activity is no longer new.
    render(
      <ForumNewActivityTag
        path="/forum/opsto/voda"
        lastActivityAt="2026-10-22T12:00:00Z"
      />,
    );
    expect(screen.queryByText(t("forum.newSinceVisit"))).toBeNull();
  });
});
