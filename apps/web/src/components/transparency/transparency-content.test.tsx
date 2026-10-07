import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { TransparencyContent } from "@/components/transparency/transparency-content";
import {
  contentTotals,
  type TransparencyContentMonth,
  type TransparencyMonth,
  type TransparencyStats,
} from "@/lib/api/transparency";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

function content(
  overrides: Partial<TransparencyContentMonth> = {},
): TransparencyContentMonth {
  return {
    received: 0,
    published: 0,
    rejected: 0,
    removed: 0,
    removed_by_category: {
      spam: 0,
      abuse: 0,
      false_information: 0,
      personal_data: 0,
      illegal: 0,
      other: 0,
    },
    moderated: 0,
    average_moderation_hours: null,
    ...overrides,
  };
}

function month(
  key: string,
  reviews: Partial<TransparencyContentMonth> = {},
  forum: Partial<TransparencyContentMonth> = {},
): TransparencyMonth {
  return {
    month: key,
    reviews: content(reviews),
    forum: content(forum),
    reports: { received: 2, resolved: 1, removed: 1, kept: 0 },
  };
}

const stats: TransparencyStats = {
  generated_at: "2026-10-06T12:00:00+00:00",
  months: [
    month(
      "2026-10",
      {
        received: 10,
        published: 8,
        rejected: 1,
        removed: 2,
        removed_by_category: {
          ...content().removed_by_category,
          abuse: 1,
          personal_data: 1,
        },
        moderated: 9,
        average_moderation_hours: 2,
      },
      {
        received: 4,
        removed: 1,
        removed_by_category: { ...content().removed_by_category, spam: 1 },
      },
    ),
    month("2026-09", {
      received: 5,
      published: 5,
      moderated: 1,
      average_moderation_hours: 12,
    }),
  ],
};

describe("contentTotals", () => {
  it("weights the moderation time by the decisions in each month", () => {
    const totals = contentTotals(stats.months, "reviews");

    expect(totals.received).toBe(15);
    expect(totals.removed).toBe(2);
    // (9 × 2 h + 1 × 12 h) / 10 decisions, not the mean of 2 h and 12 h.
    expect(totals.averageModerationHours).toBe(3);
  });
});

describe("TransparencyContent", () => {
  it("shows the twelve-month figures, removals by reason and monthly tables", async () => {
    const user = userEvent.setup();
    const { container } = render(<TransparencyContent stats={stats} />);

    expect(
      screen.getByRole("heading", { level: 1, name: t("integrity.heroTitle") }),
    ).toBeInTheDocument();

    const tile = (key: Parameters<typeof t>[0]) =>
      screen.getByText(t(key)).closest("li");
    expect(tile("integrity.statReviewsReceived")).toHaveTextContent("15");
    expect(tile("integrity.statReviewsRemoved")).toHaveTextContent("2");
    expect(tile("integrity.statModerationTime")).toHaveTextContent("3,0 ч.");
    expect(tile("integrity.statReportsReceived")).toHaveTextContent("4");

    const categories = screen.getByRole("region", {
      name: t("integrity.removedByCategoryTitle"),
    });
    expect(
      within(categories).getByText("навреда").closest("li"),
    ).toHaveTextContent("1");
    expect(
      within(categories).getByText("спам").closest("li"),
    ).toHaveTextContent("1");

    await user.click(
      screen.getByText(t("integrity.reviewsTable"), {
        selector: "summary span",
      }),
    );
    const table = screen.getByRole("table", {
      name: t("integrity.reviewsTable"),
    });
    const rows = within(table).getAllByRole("row");
    expect(rows).toHaveLength(3);
    expect(
      within(rows[1]).getByRole("rowheader", { name: "октомври 2026" }),
    ).toBeInTheDocument();
    expect(rows[1]).toHaveTextContent("10812");
    expect(rows[2]).toHaveTextContent("12,0 ч.");

    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("explains moderation, payment and ordering, and marks the criteria as a draft", () => {
    render(<TransparencyContent stats={stats} />);

    for (const [id, key] of [
      ["moderacija", "integrity.moderationTitle"],
      ["plakanje", "integrity.paymentTitle"],
      ["redosled", "integrity.orderTitle"],
      ["kriteriumi", "integrity.criteriaTitle"],
    ] as const) {
      const section = screen.getByRole("region", { name: new RegExp(t(key)) });
      expect(section).toHaveAttribute("id", id);
    }

    const ordering = screen.getByRole("region", {
      name: t("integrity.orderTitle"),
    });
    expect(ordering).toHaveTextContent(t("disclosure.featuredInfo"));
    expect(ordering).toHaveTextContent(t("disclosure.sponsoredInfo"));

    const criteria = screen.getByRole("region", {
      name: t("integrity.criteriaTitle"),
    });
    expect(
      within(criteria).getByText(t("integrity.criteriaDraft")),
    ).toBeVisible();
    expect(criteria).toHaveTextContent(t("integrity.criteriaDraftNote"));
    expect(within(criteria).getAllByRole("listitem")).toHaveLength(5);
  });

  it("still explains moderation when the figures are unavailable", () => {
    render(<TransparencyContent stats={null} />);

    expect(screen.getByText(t("integrity.statsUnavailable"))).toBeVisible();
    expect(screen.queryByRole("table")).not.toBeInTheDocument();
    expect(
      screen.getByRole("region", { name: t("integrity.moderationTitle") }),
    ).toBeInTheDocument();
  });

  it("names the data sources, what is not taken, and how to get an error fixed", async () => {
    const { container } = render(<TransparencyContent stats={null} />);

    const sources = screen.getByRole("region", {
      name: t("dataSources.title"),
    });
    expect(sources).toHaveAttribute("id", "izvori");
    expect(sources).toHaveTextContent("ФЗОМ");
    expect(sources).toHaveTextContent("Лекарска комора");
    expect(sources).toHaveTextContent(t("dataSources.excluded3"));
    // Logos and cover photos come from the institutions' own websites and
    // are removed on request.
    expect(sources).toHaveTextContent(t("dataSources.websitesName"));
    expect(sources).toHaveTextContent("на барање на установата");
    // The statutory 15 days (ЗЗЛП чл. 20) — a deadline, not "as a rule".
    expect(sources).toHaveTextContent("во рок од 15 дена");
    expect(sources).not.toHaveTextContent("по правило");
    expect(sources).toHaveTextContent("30 дена");
    expect(
      within(sources).getByRole("link", { name: t("dataSources.privacyLink") }),
    ).toHaveAttribute("href", "/privacy#zdravstveni-rabotnici");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("explains what „Верифициран“ and „Неверифициран“ mean where the badges link", async () => {
    const { container } = render(<TransparencyContent stats={null} />);

    const section = screen.getByRole("region", {
      name: t("verification.sectionTitle"),
    });
    // VERIFICATION_MORE_HREF points here.
    expect(section).toHaveAttribute("id", "verifikacija");
    expect(section).toHaveTextContent(t("verification.howDoctor"));
    expect(section).toHaveTextContent(t("verification.howFacility"));
    expect(section).toHaveTextContent(t("verification.unverifiedBody"));
    // Not a quality rating, and it does not move anyone up the lists.
    expect(section).toHaveTextContent("не влијае на редоследот");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
