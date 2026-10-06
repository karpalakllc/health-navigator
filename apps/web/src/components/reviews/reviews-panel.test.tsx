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
});
