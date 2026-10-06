import { SKOPJE_TIME_ZONE } from "@/lib/office-hours";
import { t, tCount } from "@/i18n/t";

const DAY_PARTS = new Intl.DateTimeFormat("en-US", {
  day: "numeric",
  month: "numeric",
  year: "numeric",
  timeZone: SKOPJE_TIME_ZONE,
});

/** The calendar day in Skopje as a UTC midnight timestamp, for day arithmetic. */
function skopjeDay(date: Date): number {
  const parts = DAY_PARTS.formatToParts(date);
  const get = (type: string) =>
    Number(parts.find((p) => p.type === type)?.value);

  return Date.UTC(get("year"), get("month") - 1, get("day"));
}

/**
 * „денес“, „вчера“, „пред 3 дена“, „пред 2 недели“, „пред 5 месеци“, „пред
 * 1 година“ — counted in Skopje calendar days, so a review from 23:50 is
 * „вчера“ ten minutes later. Null for a missing or unreadable date; a date in
 * the future (clock skew) reads as today.
 */
export function formatRelativeDay(
  iso: string | null | undefined,
  now: Date = new Date(),
): string | null {
  if (!iso) return null;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return null;

  const days = Math.round((skopjeDay(now) - skopjeDay(date)) / 86_400_000);

  if (days <= 0) return t("homeSections.agoToday");
  if (days === 1) return t("homeSections.agoYesterday");
  if (days < 7) return tCount("homeSections.agoDays", days);
  if (days < 30) return tCount("homeSections.agoWeeks", Math.floor(days / 7));
  if (days < 365) {
    return tCount("homeSections.agoMonths", Math.max(1, Math.floor(days / 30)));
  }

  return tCount("homeSections.agoYears", Math.floor(days / 365));
}
