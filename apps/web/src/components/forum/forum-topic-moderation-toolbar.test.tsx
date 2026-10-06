import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { ForumTopicModerationToolbar } from "@/components/forum/forum-topic-moderation-toolbar";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { mockFetch, requestBody } from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

function renderToolbar(isPinned = false, isLocked = false) {
  return render(
    <ForumTopicModerationToolbar
      categorySlug="srce"
      topicSlug="pritisok"
      isPinned={isPinned}
      isLocked={isLocked}
    />,
  );
}

describe("ForumTopicModerationToolbar", () => {
  it("is a labelled section with the two actions", () => {
    renderToolbar();

    expect(
      screen.getByRole("region", { name: t("forum.moderationTools") }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: t("forum.pinTopic") }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: t("forum.lockTopic") }),
    ).toBeInTheDocument();
  });

  it("pins the topic and then offers to unpin it", async () => {
    const fetch = mockFetch({
      status: 200,
      body: { data: { is_pinned: true, is_locked: false } },
    });
    renderToolbar();

    await userEvent
      .setup()
      .click(screen.getByRole("button", { name: t("forum.pinTopic") }));

    expect(
      await screen.findByRole("button", { name: t("forum.unpinTopic") }),
    ).toBeInTheDocument();
    expect(requestBody(fetch)).toEqual({
      categorySlug: "srce",
      topicSlug: "pritisok",
      is_pinned: true,
    });
    expect(router.refresh).toHaveBeenCalled();
  });

  it("keeps focus on the pressed button and announces the change", async () => {
    let resolve: (value: Response) => void = () => {};
    vi.stubGlobal(
      "fetch",
      vi.fn(
        () =>
          new Promise<Response>((done) => {
            resolve = done;
          }),
      ),
    );
    renderToolbar();
    const user = userEvent.setup();
    const pin = screen.getByRole("button", { name: t("forum.pinTopic") });

    await user.click(pin);

    // While saving, the button is busy but still focused (not disabled).
    expect(pin).toHaveFocus();
    expect(pin).toHaveAttribute("aria-busy", "true");
    expect(pin).not.toBeDisabled();

    resolve(
      new Response(
        JSON.stringify({ data: { is_pinned: true, is_locked: false } }),
        { status: 200, headers: { "Content-Type": "application/json" } },
      ),
    );

    expect(await screen.findByText(t("forum.topicPinnedDone"))).toHaveAttribute(
      "role",
      "status",
    );
    expect(
      screen.getByRole("button", { name: t("forum.unpinTopic") }),
    ).toHaveFocus();
  });

  it("unlocks a locked topic", async () => {
    const fetch = mockFetch({
      status: 200,
      body: { data: { is_pinned: false, is_locked: false } },
    });
    renderToolbar(false, true);

    await userEvent
      .setup()
      .click(screen.getByRole("button", { name: t("forum.unlockTopic") }));

    expect(
      await screen.findByRole("button", { name: t("forum.lockTopic") }),
    ).toBeInTheDocument();
    expect(requestBody(fetch)).toMatchObject({ is_locked: false });
  });

  it("shows a refusal as an alert and keeps the state", async () => {
    mockFetch({ status: 403, body: { message: "Немате дозвола." } });
    renderToolbar();

    await userEvent
      .setup()
      .click(screen.getByRole("button", { name: t("forum.pinTopic") }));

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Немате дозвола.",
    );
    expect(
      screen.getByRole("button", { name: t("forum.pinTopic") }),
    ).toBeInTheDocument();
  });

  it("has no serious accessibility violations", async () => {
    const { container } = renderToolbar(true, true);

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
