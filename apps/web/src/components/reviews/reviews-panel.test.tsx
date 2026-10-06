import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ReviewsPanel } from "@/components/reviews/reviews-panel";
import { t } from "@/i18n/t";

describe("ReviewsPanel", () => {
  it("sends a signed-out visitor to login and back to these reviews", () => {
    render(
      <ReviewsPanel
        kind="doctor"
        slug="ana-petrovska"
        initial={{
          data: [],
          meta: { current_page: 1, per_page: 10, total: 0, last_page: 1 },
        }}
        viewerReview={null}
        isLoggedIn={false}
        page={1}
        sort="newest"
        rating=""
      />,
    );

    // Login used to land on the home page, so the visitor had to find the
    // doctor again to write the review.
    expect(screen.getByRole("link", { name: t("nav.login") })).toHaveAttribute(
      "href",
      `/login?redirect=${encodeURIComponent("/doctors/ana-petrovska#reviews")}`,
    );
  });
});
