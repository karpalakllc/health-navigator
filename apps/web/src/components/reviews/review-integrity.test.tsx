import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { AspectRatingInput } from "@/components/reviews/aspect-rating-input";
import { RemovedPlaceholder } from "@/components/reviews/removed-placeholder";
import { ReviewForm } from "@/components/reviews/review-form";
import {
  AspectBars,
  RatingTrend,
  ReviewInsights,
} from "@/components/reviews/review-insights";
import { ReviewList } from "@/components/reviews/review-list";
import type { RemovedItem, ReviewListItem } from "@/lib/api/types";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { mockFetch, requestBody } from "../../../test/fetch";

const removed: RemovedItem = {
  id: 7,
  removed: true,
  removed_at: "2026-10-03T09:00:00+00:00",
  removal_category: "abuse",
};

describe("removal placeholder", () => {
  it("says when and why a review was removed, and nothing of the review", () => {
    const items: ReviewListItem[] = [
      {
        id: 11,
        rating: 5,
        body: "Внимателен преглед.",
        author_name: "Ана П.",
        published_at: "2026-09-01T10:00:00+00:00",
        helpful_count: 2,
        response: null,
      },
      removed,
    ];

    render(<ReviewList reviews={items} isLoggedIn />);

    const rows = screen.getAllByRole("listitem");
    expect(rows).toHaveLength(2);
    expect(rows[1]).toHaveTextContent(
      "Рецензијата е отстранета на 3 октомври 2026 — причина: навреда.",
    );
    // No „Корисно“ or „Пријави“ on a placeholder: there is nothing to act on.
    expect(within(rows[1]).queryAllByRole("button")).toHaveLength(0);
    expect(
      within(rows[1]).getByRole("link", { name: t("integrity.removedHow") }),
    ).toHaveAttribute("href", "/transparency#moderacija");
    expect(within(rows[0]).getAllByRole("button").length).toBeGreaterThan(0);
  });

  it("words a removed forum reply in its own gender and survives a missing date", () => {
    render(
      <RemovedPlaceholder
        item={{ ...removed, removed_at: null, removal_category: "illegal" }}
        kind="reply"
      />,
    );

    expect(
      screen.getByText(
        /Одговорот е отстранет — причина: незаконска содржина\./,
      ),
    ).toBeInTheDocument();
  });

  it("falls back to „друго“ for an unknown category", () => {
    render(
      <RemovedPlaceholder
        item={{
          ...removed,
          removal_category: "new_code" as RemovedItem["removal_category"],
        }}
        kind="review"
      />,
    );

    expect(screen.getByText(/причина: друго\./)).toBeInTheDocument();
  });
});

describe("aspect rating input", () => {
  function Harness({
    onChange,
  }: {
    onChange?: (value: Record<string, number>) => void;
  }) {
    return (
      <AspectRatingInput
        kind="doctor"
        value={{}}
        onChange={(next) => onChange?.(next as Record<string, number>)}
      />
    );
  }

  it("is folded away by default and adds no radio groups while closed", () => {
    render(<Harness />);

    expect(screen.getByText(t("integrity.aspectsToggle"))).toBeVisible();
    expect(screen.queryAllByRole("radiogroup")).toHaveLength(0);
  });

  it("opens to one optional, labelled radio group per aspect without axe violations", async () => {
    const user = userEvent.setup();
    const { container } = render(<Harness />);

    await user.click(screen.getByText(t("integrity.aspectsToggle")));

    const groups = screen.getAllByRole("radiogroup");
    expect(groups.map((group) => group.getAttribute("aria-label"))).toEqual([
      "Комуникација",
      "Објаснување",
      "Време на чекање",
      "Однос и почит",
    ]);
    for (const group of groups) {
      expect(group).not.toHaveAttribute("aria-required");
      for (const radio of within(group).getAllByRole("radio")) {
        expect(radio).toHaveAttribute("aria-checked", "false");
      }
    }
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("shows the facility aspects for a facility", async () => {
    const user = userEvent.setup();
    render(
      <AspectRatingInput kind="facility" value={{}} onChange={() => {}} />,
    );

    await user.click(screen.getByText(t("integrity.aspectsToggle")));

    expect(
      screen
        .getAllByRole("radiogroup")
        .map((group) => group.getAttribute("aria-label")),
    ).toEqual(["Чистота", "Организација", "Време на чекање", "Персонал"]);
  });

  it("is keyboard operable and lets a choice be taken back", async () => {
    const user = userEvent.setup();
    render(<ReviewForm kind="doctor" slug="d-r-ana" />);

    // Stars first (W8-B): the optional extras appear once a star is chosen.
    await user.click(screen.getByRole("radio", { name: "4 ѕвезди од 5" }));
    await user.click(screen.getByText(t("integrity.aspectsToggle")));
    const waiting = screen.getByRole("radiogroup", {
      name: "Време на чекање",
    });
    const first = within(waiting).getAllByRole("radio")[0];
    first.focus();
    await user.keyboard("{End}");

    expect(
      within(waiting).getByRole("radio", { name: "5 ѕвезди од 5" }),
    ).toHaveAttribute("aria-checked", "true");
    expect(
      within(waiting).getByRole("radio", { name: "5 ѕвезди од 5" }),
    ).toHaveFocus();

    await user.keyboard("{ArrowLeft}{ArrowLeft}");
    expect(
      within(waiting).getByRole("radio", { name: "3 ѕвезди од 5" }),
    ).toHaveAttribute("aria-checked", "true");

    const clear = screen.getByRole("button", {
      name: "Отстрани ја оценката за „Време на чекање“",
    });
    // A 48px touch target, like the stars.
    expect(clear).toHaveClass("min-h-12");
    await user.click(clear);
    for (const radio of within(waiting).getAllByRole("radio")) {
      expect(radio).toHaveAttribute("aria-checked", "false");
    }
    expect(within(waiting).getAllByRole("radio")[0]).toHaveFocus();
  });

  it("sends only the rated aspects with the review, and none when skipped", async () => {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    const user = userEvent.setup();
    render(<ReviewForm kind="doctor" slug="d-r-ana" />);

    const overall = screen.getByRole("radiogroup", {
      name: t("reviews.rating"),
    });
    await user.click(
      within(overall).getByRole("radio", { name: "4 ѕвезди од 5" }),
    );
    await user.click(screen.getByText(t("integrity.aspectsToggle")));
    await user.click(
      within(
        screen.getByRole("radiogroup", { name: "Комуникација" }),
      ).getByRole("radio", { name: "5 ѕвезди од 5" }),
    );
    await user.click(screen.getByRole("button", { name: t("reviews.submit") }));
    await waitFor(() => expect(fetch).toHaveBeenCalledTimes(1));

    expect(requestBody(fetch)).toEqual({
      kind: "doctor",
      slug: "d-r-ana",
      rating: 4,
      body: null,
      aspects: { communication: 5 },
    });

    await user.click(
      within(overall).getByRole("radio", { name: "3 ѕвезди од 5" }),
    );
    await user.click(screen.getByRole("button", { name: t("reviews.submit") }));
    await waitFor(() => expect(fetch).toHaveBeenCalledTimes(2));

    expect(requestBody(fetch, 1)).not.toHaveProperty("aspects");
  });
});

describe("aspect bars and trend", () => {
  it("shows aspects with an average and hides those below three ratings", () => {
    render(
      <AspectBars
        aspects={[
          { key: "communication", count: 12, average: 4.3 },
          { key: "explanation", count: 2, average: null },
          { key: "waiting_time", count: 3, average: 2 },
          { key: "respect", count: 0, average: null },
        ]}
      />,
    );

    const rows = screen.getAllByRole("listitem");
    expect(rows).toHaveLength(2);
    expect(rows[0]).toHaveTextContent("Комуникација: 4,3 од 5, 12 оценки");
    expect(rows[1]).toHaveTextContent("Време на чекање: 2,0 од 5, 3 оценки");
    expect(screen.queryByText("Објаснување")).not.toBeInTheDocument();
  });

  it("gives every trend period a text alternative and hides the drawing", async () => {
    const { container } = render(
      <RatingTrend
        trend={[
          { start: "2025-11-01", end: "2026-01-31", count: 2, average: 3 },
          { start: "2026-02-01", end: "2026-04-30", count: 0, average: null },
          { start: "2026-05-01", end: "2026-07-31", count: 1, average: 5 },
          { start: "2026-08-01", end: "2026-10-31", count: 6, average: 4.25 },
        ]}
      />,
    );

    const periods = screen.getAllByRole("listitem");
    expect(
      periods.map((period) => period.querySelector(".sr-only")?.textContent),
    ).toEqual([
      "ное 2025 – јан 2026: просечна оценка 3,0 од 5, 2 рецензии",
      "фев – апр 2026: нема рецензии",
      "мај – јул 2026: просечна оценка 5,0 од 5, 1 рецензија",
      "авг – окт 2026: просечна оценка 4,3 од 5, 6 рецензии",
    ]);
    for (const period of periods) {
      const visible = Array.from(period.children).filter(
        (child) => !child.classList.contains("sr-only"),
      );
      for (const child of visible) {
        expect(child).toHaveAttribute("aria-hidden", "true");
      }
    }
    expect(
      screen.getByRole("heading", { name: t("integrity.trendTitle") }),
    ).toBeInTheDocument();
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("renders nothing when neither aspects nor trend have enough reviews", () => {
    const { container } = render(
      <ReviewInsights
        aspects={[{ key: "communication", count: 2, average: null }]}
        trend={null}
      />,
    );

    expect(container).toBeEmptyDOMElement();
  });
});
