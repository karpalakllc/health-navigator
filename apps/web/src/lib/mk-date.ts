import { SKOPJE_TIME_ZONE } from "@/lib/office-hours";
import { t } from "@/i18n/t";

const PARTS = new Intl.DateTimeFormat("en-US", {
  day: "numeric",
  month: "numeric",
  year: "numeric",
  timeZone: SKOPJE_TIME_ZONE,
});

/**
 * „4 октомври 2026“ in Skopje time. Month names come from mk.ts rather than
 * Intl's mk-MK data, which some browsers lack (the server said „октомври“,
 * the browser re-rendered „October“).
 */
export function formatMkDate(iso: string | null | undefined): string | null {
  if (!iso) return null;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return null;
  const parts = PARTS.formatToParts(date);
  const get = (type: string) =>
    Number(parts.find((p) => p.type === type)?.value);
  const month = t("reviews.monthNames").split(",")[get("month") - 1];

  return `${get("day")} ${month} ${get("year")}`;
}
