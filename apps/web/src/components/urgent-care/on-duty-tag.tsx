import { Icon } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import { cn } from "@/lib/cn";
import type { OnDutyToday } from "@/lib/api/types";
import { t } from "@/i18n/t";

/**
 * „Дежурна денес · 24 часа“ from ФЗОМ's on-duty schedule
 * (docs/urgent-care.md): on a pharmacy profile, and — `short`, „· по повик“ —
 * on directory and search cards. Nothing when the pharmacy is not on duty.
 */
export function OnDutyTag({
  duty,
  short = false,
  className,
}: {
  duty: OnDutyToday | null | undefined;
  short?: boolean;
  className?: string;
}) {
  if (!duty) {
    return null;
  }

  const detail =
    duty.mode === "all_day"
      ? t("onDuty.allDay")
      : duty.mode === "on_call"
        ? t(short ? "onDuty.onCallShort" : "onDuty.onCall")
        : duty.mode === "hours"
          ? duty.hours_text
          : null;

  return (
    <Tag
      tone="care"
      icon="clock"
      title={t("onDuty.source")}
      className={className}
    >
      {t("onDuty.today")}
      {detail ? ` · ${detail}` : null}
    </Tag>
  );
}

/**
 * „Според распоредот на ФЗОМ — проверете со телефонски повик пред да
 * тргнете.“ — once above a list with on-duty cards, never per card.
 */
export function OnDutySourceNote({ className }: { className?: string }) {
  return (
    <p className={cn("flex items-start gap-2 type-meta text-ink-2", className)}>
      <Icon name="clock" size={20} className="mt-0.5 shrink-0" />
      {t("urgentCare.pharmaciesSource")}
    </p>
  );
}
