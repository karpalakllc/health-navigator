import { Tag } from "@/components/ui/tag";
import type { PharmacyDetail } from "@/lib/api/types";
import { t } from "@/i18n/t";

/**
 * „Дежурна денес · 24 часа“ on a pharmacy profile, from ФЗОМ's on-duty
 * schedule (docs/urgent-care.md). Nothing when the pharmacy is not on duty.
 */
export function OnDutyTag({ duty }: { duty: PharmacyDetail["on_duty_today"] }) {
  if (!duty) {
    return null;
  }

  const detail =
    duty.mode === "all_day"
      ? t("onDuty.allDay")
      : duty.mode === "on_call"
        ? t("onDuty.onCall")
        : duty.mode === "hours"
          ? duty.hours_text
          : null;

  return (
    <Tag tone="care" icon="clock" title={t("onDuty.source")}>
      {t("onDuty.today")}
      {detail ? ` · ${detail}` : null}
    </Tag>
  );
}
