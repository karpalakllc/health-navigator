import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ComponentProps } from "react";
import { describe, expect, it } from "vitest";
import { ReviewsPanel } from "@/components/reviews/reviews-panel";
import { t } from "@/i18n/t";
import { mockFetch } from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

const EMPTY = {
  data: [],
  meta: { current_page: 1, per_page: 10, total: 0, last_page: 1 },
};

function props(
  overrides: Partial<ComponentProps<typeof ReviewsPanel>> = {},
): ComponentProps<typeof ReviewsPanel> {
  return {
    kind: "doctor",
    slug: "ana-petrovska",
    initial: EMPTY,
    viewerReview: null,
    isLoggedIn: false,
    page: 1,
    sort: "newest",
    rating: "",
    ...overrides,
  };
}

describe("ReviewsPanel", () => {
  it("asks a member with a temporary name to choose one instead of offering the form", () => {
    render(
      <ReviewsPanel
        {...props({ isLoggedIn: true, mustChooseUsername: true })}
      />,
    );

    // The API refuses the review until a username is chosen: do not let the
    // member write a whole review first.
    expect(
      screen.queryByRole("button", { name: t("reviews.submit") }),
    ).toBeNull();
    expect(
      screen.getByRole("link", { name: t("usernames.accountNoticeCta") }),
    ).toHaveAttribute(
      "href",
      `/account/username?redirect=${encodeURIComponent("/doctors/ana-petrovska#reviews")}`,
    );
    // „Напиши рецензија“ (#review-form) lands on the note.
    expect(document.getElementById("review-form")).toHaveTextContent(
      t("usernames.accountNotice"),
    );
  });

  it("sends a signed-out visitor to login and back to these reviews", () => {
    render(<ReviewsPanel {...props()} />);

    // Login used to land on the home page, so the visitor had to find the
    // doctor again to write the review.
    expect(screen.getByRole("link", { name: t("nav.login") })).toHaveAttribute(
      "href",
      `/login?redirect=${encodeURIComponent("/doctors/ana-petrovska#reviews")}`,
    );
  });

  it("keeps the login link inside the sentence", () => {
    render(<ReviewsPanel {...props()} />);

    const link = screen.getByRole("link", { name: t("nav.login") });
    // One paragraph holds the link and the rest of the sentence, so on a
    // phone the words wrap with the link instead of under it.
    expect(link.parentElement?.tagName).toBe("P");
    expect(link.parentElement).toHaveTextContent(
      `${t("nav.login")} ${t("reviews.loginToSubmit")}`,
    );
  });

  it("names the one-star filter option in the singular", () => {
    render(<ReviewsPanel {...props({ rating: "1" })} />);

    // "1 ѕвезди" was the plural after 1; Macedonian takes the singular.
    expect(
      screen.getByRole("option", { name: "1 ѕвезда" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("option", { name: "2 ѕвезди" }),
    ).toBeInTheDocument();
    expect(screen.queryByRole("option", { name: "1 ѕвезди" })).toBeNull();
  });

  it("moves the viewport and focus to the pending card after sending", async () => {
    mockFetch({ status: 201, body: { data: { status: "pending" } } });
    const user = userEvent.setup();
    const { rerender } = render(
      <ReviewsPanel {...props({ isLoggedIn: true })} />,
    );

    await user.click(screen.getByRole("radio", { name: "4 ѕвезди од 5" }));
    await user.click(screen.getByRole("button", { name: t("reviews.submit") }));
    await waitFor(() => expect(router.refresh).toHaveBeenCalled());

    // The refresh brings the viewer's review back as pending.
    rerender(
      <ReviewsPanel
        {...props({
          isLoggedIn: true,
          viewerReview: {
            id: 7,
            rating: 4,
            body: null,
            status: "pending",
            created_at: null,
            published_at: null,
          },
        })}
      />,
    );

    const card = screen.getByRole("article", {
      name: t("reviews.pendingTitle"),
    });
    expect(card).toHaveFocus();
  });

  it("does not grab focus for a review that was already pending", () => {
    render(
      <ReviewsPanel
        {...props({
          isLoggedIn: true,
          viewerReview: {
            id: 7,
            rating: 4,
            body: null,
            status: "pending",
            created_at: null,
            published_at: null,
          },
        })}
      />,
    );

    expect(
      screen.getByRole("article", { name: t("reviews.pendingTitle") }),
    ).not.toHaveFocus();
  });

  const rejected = {
    id: 9,
    rating: 2,
    body: "Првиот текст со име на друг пациент.",
    status: "rejected" as const,
    rejection_note: "Наведовте име на друг пациент.",
    removed: false,
    can_resubmit: true,
    aspects: { communication: 2 },
    created_at: null,
    published_at: null,
  };

  it("offers a refused review once more, prefilled, with the reason and the last-chance note", async () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    render(
      <ReviewsPanel {...props({ isLoggedIn: true, viewerReview: rejected })} />,
    );

    expect(
      screen.getByRole("heading", { name: t("reviews.resubmitTitle") }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(t("reviews.resubmitLastChance")),
    ).toBeInTheDocument();
    expect(
      screen.getByText("Наведовте име на друг пациент."),
    ).toBeInTheDocument();
    expect(screen.getByLabelText(t("reviews.body"))).toHaveValue(
      "Првиот текст со име на друг пациент.",
    );
    // The overall stars come first; the unfolded aspects repeat the names.
    expect(
      screen.getAllByRole("radio", { name: "2 ѕвезди од 5" })[0],
    ).toHaveAttribute("aria-checked", "true");

    const user = userEvent.setup();
    const body = screen.getByLabelText(t("reviews.body"));
    await user.clear(body);
    await user.type(body, "Изменет текст без лични податоци.");
    await user.click(
      screen.getByRole("button", { name: t("reviews.resubmitSubmit") }),
    );

    await waitFor(() => expect(router.refresh).toHaveBeenCalled());
    const sent = JSON.parse(String(fetch.mock.calls[0]?.[1]?.body));
    expect(sent).toMatchObject({
      rating: 2,
      body: "Изменет текст без лични податоци.",
      aspects: { communication: 2 },
    });
  });

  it("says a second refusal is final and offers no form", () => {
    render(
      <ReviewsPanel
        {...props({
          isLoggedIn: true,
          viewerReview: {
            ...rejected,
            can_resubmit: false,
            rejection_note: "Второ одбивање.",
          },
        })}
      />,
    );

    expect(screen.queryByRole("form")).not.toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: t("reviews.resubmitSubmit") }),
    ).not.toBeInTheDocument();
    expect(screen.getByText(t("reviews.finalBody"))).toBeInTheDocument();
    expect(screen.getByText("Второ одбивање.")).toBeInTheDocument();
  });

  it("offers nothing to resend for a review removed after publication", () => {
    render(
      <ReviewsPanel
        {...props({
          isLoggedIn: true,
          viewerReview: {
            ...rejected,
            removed: true,
            can_resubmit: false,
          },
        })}
      />,
    );

    expect(
      screen.queryByRole("heading", { name: t("reviews.submitTitle") }),
    ).not.toBeInTheDocument();
    expect(screen.queryByText(t("reviews.finalBody"))).not.toBeInTheDocument();
  });
});
