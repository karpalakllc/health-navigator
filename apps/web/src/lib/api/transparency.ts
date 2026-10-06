import { apiGet } from "@/lib/api/client";
import type { RemovalCategory } from "@/lib/api/types";

/** One month's figures for reviews, or for the forum (topics and replies). */
export type TransparencyContentMonth = {
  received: number;
  published: number;
  /** Refused before ever being published. */
  rejected: number;
  /** Taken down after publication. */
  removed: number;
  removed_by_category: Record<RemovalCategory, number>;
  /** Moderator decisions the average covers. */
  moderated: number;
  average_moderation_hours: number | null;
};

export type TransparencyMonth = {
  /** YYYY-MM (UTC). */
  month: string;
  reviews: TransparencyContentMonth;
  forum: TransparencyContentMonth;
  reports: {
    received: number;
    resolved: number;
    removed: number;
    kept: number;
  };
};

export type TransparencyStats = {
  generated_at: string;
  /** Twelve months, newest first. */
  months: TransparencyMonth[];
};

/** The API caches the figures for an hour; so does the web tier. */
export const TRANSPARENCY_REVALIDATE_SECONDS = 3600;

/**
 * GET /transparency, or null when the API cannot answer: the page still
 * explains how moderation works without the figures.
 */
export async function fetchTransparencyStats(): Promise<TransparencyStats | null> {
  try {
    return await apiGet<TransparencyStats>("/transparency", {
      revalidate: TRANSPARENCY_REVALIDATE_SECONDS,
    });
  } catch {
    return null;
  }
}

export type TransparencyTotals = {
  received: number;
  published: number;
  rejected: number;
  removed: number;
  removedByCategory: Record<RemovalCategory, number>;
  /** Weighted by the number of decisions in each month; null without any. */
  averageModerationHours: number | null;
};

const CATEGORIES: RemovalCategory[] = [
  "spam",
  "abuse",
  "false_information",
  "personal_data",
  "illegal",
  "other",
];

/** Twelve-month totals of one content kind. */
export function contentTotals(
  months: TransparencyMonth[],
  kind: "reviews" | "forum",
): TransparencyTotals {
  const removedByCategory = Object.fromEntries(
    CATEGORIES.map((category) => [category, 0]),
  ) as Record<RemovalCategory, number>;
  let received = 0;
  let published = 0;
  let rejected = 0;
  let removed = 0;
  let decisions = 0;
  let hours = 0;

  for (const month of months) {
    const row = month[kind];
    received += row.received;
    published += row.published;
    rejected += row.rejected;
    removed += row.removed;

    for (const category of CATEGORIES) {
      removedByCategory[category] += row.removed_by_category[category] ?? 0;
    }

    if (row.average_moderation_hours !== null && row.moderated > 0) {
      decisions += row.moderated;
      hours += row.average_moderation_hours * row.moderated;
    }
  }

  return {
    received,
    published,
    rejected,
    removed,
    removedByCategory,
    averageModerationHours: decisions > 0 ? hours / decisions : null,
  };
}

/** Twelve-month report totals. */
export function reportTotals(months: TransparencyMonth[]) {
  return months.reduce(
    (sum, month) => ({
      received: sum.received + month.reports.received,
      resolved: sum.resolved + month.reports.resolved,
      removed: sum.removed + month.reports.removed,
      kept: sum.kept + month.reports.kept,
    }),
    { received: 0, resolved: 0, removed: 0, kept: 0 },
  );
}
