import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ReviewList } from "@/components/reviews/review-list";
import type { PublicReview } from "@/lib/api/types";
import { tFormat } from "@/i18n/t";
import { router } from "../../../test/next-navigation";

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
    let fail: (response: Response) => void = () => {};
    vi.stubGlobal(
      "fetch",
      vi.fn(
        () =>
          new Promise<Response>((resolve) => {
            fail = resolve;
          }),
      ),
    );
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

    // Optimistic: un-voted at once, before the server answers.
    expect(button).toHaveAttribute("aria-pressed", "false");
    expect(button).toHaveTextContent(
      tFormat("reviews.helpfulCount", { count: 1 }),
    );

    fail(
      new Response(JSON.stringify({ message: "x" }), {
        status: 500,
        headers: { "Content-Type": "application/json" },
      }),
    );

    await vi.waitFor(() =>
      expect(screen.getByRole("status")).toHaveTextContent("x"),
    );
    expect(button).toHaveAttribute("aria-pressed", "true");
    expect(button).toHaveTextContent(
      tFormat("reviews.helpfulCount", { count: 2 }),
    );
  });

  it("sends an expired session to sign-in and back", async () => {
    mockFetch(401, { message: "Unauthenticated." });
    const user = userEvent.setup();
    render(
      <ReviewList
        reviews={[review()]}
        isLoggedIn
        returnTo="/doctors/ana#reviews"
      />,
    );

    await user.click(
      screen.getByRole("button", {
        name: tFormat("reviews.helpfulCount", { count: 2 }),
      }),
    );

    await vi.waitFor(() =>
      expect(router.push).toHaveBeenCalledWith(
        `/login?redirect=${encodeURIComponent("/doctors/ana#reviews")}`,
      ),
    );
    expect(screen.queryByRole("status")).toBeEmptyDOMElement();
  });

  it("offers neither a vote nor a report on the viewer's own review", () => {
    render(
      <ReviewList
        reviews={[review(), review({ id: 12, author_name: "Петар Г." })]}
        isLoggedIn
        viewerReviewId={11}
      />,
    );

    expect(
      screen.queryByRole("group", {
        name: tFormat("reviews.actions", { name: "Ана П." }),
      }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("button", {
        name: tFormat("reports.actionReview", { name: "Ана П." }),
      }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("button", {
        name: tFormat("reports.actionReview", { name: "Петар Г." }),
      }),
    ).toBeInTheDocument();
  });
});
