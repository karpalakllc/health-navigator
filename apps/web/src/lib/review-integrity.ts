import type { RemovalCategory } from "@/lib/api/types";
import { formatRating } from "@/lib/rating";
import { t, type MessageKey } from "@/i18n/t";

/** The optional aspect sub-ratings per profile kind, in display order. */
export const REVIEW_ASPECTS = {
  doctor: ["communication", "explanation", "waiting_time", "respect"],
  facility: ["cleanliness", "organisation", "waiting_time", "staff"],
  pharmacy: ["cleanliness", "organisation", "waiting_time", "staff"],
} as const;

export type ReviewAspectKey =
  (typeof REVIEW_ASPECTS)[keyof typeof REVIEW_ASPECTS][number];

const ASPECT_LABELS: Record<ReviewAspectKey, MessageKey> = {
  communication: "integrity.aspectCommunication",
  explanation: "integrity.aspectExplanation",
  waiting_time: "integrity.aspectWaitingTime",
  respect: "integrity.aspectRespect",
  cleanliness: "integrity.aspectCleanliness",
  organisation: "integrity.aspectOrganisation",
  staff: "integrity.aspectStaff",
};

/** The aspect's Macedonian label; null for a code the web does not know. */
export function aspectLabel(key: string): string | null {
  return Object.hasOwn(ASPECT_LABELS, key)
    ? t(ASPECT_LABELS[key as ReviewAspectKey])
    : null;
}

const CATEGORY_LABELS: Record<RemovalCategory, MessageKey> = {
  spam: "integrity.categorySpam",
  abuse: "integrity.categoryAbuse",
  false_information: "integrity.categoryFalseInformation",
  personal_data: "integrity.categoryPersonalData",
  illegal: "integrity.categoryIllegal",
  other: "integrity.categoryOther",
};

export const REMOVAL_CATEGORIES = Object.keys(
  CATEGORY_LABELS,
) as RemovalCategory[];

/** The public removal reason; an unknown or missing code reads „друго“. */
export function removalCategoryLabel(
  category: string | null | undefined,
): string {
  const key =
    category && Object.hasOwn(CATEGORY_LABELS, category)
      ? CATEGORY_LABELS[category as RemovalCategory]
      : CATEGORY_LABELS.other;

  return t(key);
}

/** „ное 2025“ from a YYYY-MM-DD date (no Date parsing: no time zone drift). */
export function shortMonthLabel(isoDate: string): string {
  const [year, month] = isoDate.split("-").map(Number);
  const name = t("integrity.monthShort").split(",")[month - 1] ?? "";

  return `${name} ${year}`;
}

/** „ное 2025 – јан 2026“, or „фев – апр 2026“ within one year. */
export function periodLabel(start: string, end: string): string {
  const from = shortMonthLabel(start);
  const to = shortMonthLabel(end);

  if (start.slice(0, 4) === end.slice(0, 4)) {
    return `${from.split(" ")[0]} – ${to}`;
  }

  return `${from} – ${to}`;
}

/** „октомври 2026“ from YYYY-MM. */
export function monthLabel(yearMonth: string): string {
  const [year, month] = yearMonth.split("-").map(Number);
  const name = t("reviews.monthNames").split(",")[month - 1] ?? "";

  return `${name} ${year}`;
}

/** A decimal with the Macedonian comma, e.g. hours „6,5“. */
export function formatDecimal(value: number): string {
  return formatRating(value);
}

/**
 * Only a plain {string: number} object goes upstream (the API checks which
 * codes the profile type has and the 1–5 range); anything else is dropped,
 * so the review itself still goes through.
 */
export function aspectsForUpstream(
  value: unknown,
): Record<string, number> | undefined {
  if (value === null || typeof value !== "object" || Array.isArray(value)) {
    return undefined;
  }

  const entries = Object.entries(value).filter(
    ([key, rating]) =>
      /^[a-z_]{1,32}$/.test(key) &&
      typeof rating === "number" &&
      Number.isInteger(rating),
  );

  return entries.length > 0 ? Object.fromEntries(entries) : undefined;
}
