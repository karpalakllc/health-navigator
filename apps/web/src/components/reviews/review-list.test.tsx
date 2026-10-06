import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ReviewList } from "@/components/reviews/review-list";
import type { PublicReview } from "@/lib/api/types";
import { t, tFormat } from "@/i18n/t";

function review(overrides: Partial<PublicReview> = {}): PublicReview {
  return {
    id: 11,
    rating: 4,
    body: "Внимателен преглед.",
    author_name: "Ана П.",
    published_at: "2026-09-01T10:00:00+00:00",
    helpful_count: 2,
    response: null,
    ...overrides,
  };
}

function mockFetch(status: number, body: unknown) {
  const fetchMock = vi.fn().mockResolvedValue(
    new Response(JSON.stringify(body), {
      status,
      headers: { "Content-Type": "application/json" },
    }),
  );
  vi.stubGlobal("fetch", fetchMock);
  return fetchMock;
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("ReviewList official response", () => {
  it("renders the response under the review, labelled with the profile", () => {
    render(
      <ReviewList
        reviews={[
          review({
            response: {
              body: "Ви благодариме.\n<b>Поздрав</b>",
              responder_name: "д-р Ана Петровска",
              responded_at: "2026-09-02T10:00:00+00:00",
            },
          }),
        ]}
      />,
    );

    const heading = screen.getByRole("heading", {
      name: tFormat("reviewResponse.title", { name: "д-р Ана Петровска" }),
    });
    const block = heading.closest("section")!;
    // Plain text: markup in the body is shown, never interpreted.
    expect(block).toHaveTextContent("<b>Поздрав</b>");
    expect(block.querySelector("b")).toBeNull();
  });

  it("shows nothing extra when there is no response", () => {
    render(<ReviewList reviews={[review()]} />);

    expect(screen.queryByRole("heading")).not.toBeInTheDocument();
  });
});

describe("ReviewList „Корисно“", () => {
  it("links signed-out visitors to sign in", () => {
    render(<ReviewList reviews={[review()]} returnTo="/doctors/ana#reviews" />);

    expect(
      screen.getByRole("link", {
        name: tFormat("reviews.helpfulCount", { count: 2 }),
      }),
    ).toHaveAttribute(
      "href",
      `/login?redirect=${encodeURIComponent("/doctors/ana#reviews")}`,
    );
  });

  it("toggles with aria-pressed and settles on the server's count", async () => {
    const fetchMock = mockFetch(200, {
      data: { helpful_count: 5, has_voted_helpful: true },
    });
    const user = userEvent.setup();
    render(<ReviewList reviews={[review()]} isLoggedIn />);

    const button = screen.getByRole("button", {
      name: tFormat("reviews.helpfulCount", { count: 2 }),
    });
    expect(button).toHaveAttribute("aria-pressed", "false");

    await user.click(button);

    expect(fetchMock).toHaveBeenCalledWith(
      "/api/reviews/helpful",
      expect.objectContaining({ method: "PUT", body: '{"id":11}' }),
    );
    expect(button).toHaveAttribute("aria-pressed", "true");
    expect(button).toHaveTextContent(
      tFormat("reviews.helpfulCount", { count: 5 }),
    );
  });

  it("rolls back when the vote fails", async () => {
    mockFetch(500, { message: "x" });
    const user = userEvent.setup();
    render(
      <ReviewList
        reviews={[review({ viewer: { has_voted_helpful: true } })]}
        isLoggedIn
      />,
    );

    const button = screen.getByRole("button", {
      name: tFormat("reviews.helpfulCount", { count: 2 }),
    });
    expect(button).toHaveAttribute("aria-pressed", "true");

    await user.click(button);

    expect(button).toHaveAttribute("aria-pressed", "true");
    expect(button).toHaveTextContent(
      tFormat("reviews.helpfulCount", { count: 2 }),
    );
    expect(await screen.findByRole("status")).toHaveTextContent("x");
  });

  it("offers no vote on the viewer's own review, but still the report link", () => {
    render(<ReviewList reviews={[review()]} isLoggedIn viewerReviewId={11} />);

    const actions = screen.getByRole("group", {
      name: tFormat("reviews.actions", { name: "Ана П." }),
    });
    expect(
      within(actions).queryByRole("button", { name: /Корисно/ }),
    ).not.toBeInTheDocument();
    expect(
      within(actions).getByRole("button", {
        name: tFormat("reports.actionReview", { name: "Ана П." }),
      }),
    ).toHaveTextContent(t("reports.action"));
  });
});
