import { Icon } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import {
  officeHoursRows,
  openStatus,
  type OpenStatus,
} from "@/lib/office-hours";
import { t, tFormat } from "@/i18n/t";

export function openStatusText(status: OpenStatus): string {
  switch (status.state) {
    case "open":
      return tFormat("directory.openUntil", { time: status.until });
    case "open24":
      return t("directory.open24");
    case "closed":
      return status.todayHours
        ? `${t("directory.closedNow")} · ${tFormat("directory.todayHours", { hours: status.todayHours })}`
        : t("directory.closedToday");
  }
}

/**
 * „Отворено до 14:00“ in care green, or „Сега затворено · Денес: …“ in ink-2.
 * Nothing at all when the hours do not parse — no guessing.
 */
export function OpenStatusLine({
  hours,
  now,
  className,
}: {
  hours: Record<string, string> | unknown[] | null | undefined;
  now?: Date;
  className?: string;
}) {
  const status = openStatus(officeHoursRows(hours, now), now);

  if (!status) {
    return null;
  }

  const open = status.state !== "closed";

  return (
    <p
      className={cn(
        "flex items-center gap-2 type-meta",
        open ? "font-semibold text-care" : "text-ink-2",
        className,
      )}
    >
      <Icon name="clock" size={20} />
      {/* Minutes tick between the server render and hydration. */}
      <span suppressHydrationWarning>{openStatusText(status)}</span>
    </p>
  );
}
