import { Icon } from "@/components/ui/icons";
import { Notice, NoticeTelLink } from "@/components/ui/notice";
import { t } from "@/i18n/t";

/*
 * „Не е дијагноза“. The full note sits on the intro, the unavailable state and
 * the results; `compact` is the one-line 194/112 reminder every step carries
 * (docs/triage-safety.md, „Every step“), with both numbers as tel: links.
 */
export function GuidanceSafetyNotice({
  compact = false,
  withoutEmergencyLine = false,
}: {
  compact?: boolean;
  /** The intro already carries the 194/112 line and the urgent-help button. */
  withoutEmergencyLine?: boolean;
}) {
  if (compact) {
    return (
      <p
        data-guidance-compact-emergency
        className="flex items-start gap-2 type-meta text-ink-2"
      >
        <Icon name="info" size={20} className="mt-0.5 shrink-0" />
        <span>
          {t("guidance.compactLead")} <NoticeTelLink number="194" />{" "}
          {t("guidance.compactOr")} <NoticeTelLink number="112" />.
        </span>
      </p>
    );
  }

  return (
    <Notice tone="safety" title={t("guidance.notDiagnosis")}>
      <p>{t("guidance.notDiagnosisBody")}</p>
      {withoutEmergencyLine ? null : (
        <p className="mt-2">{t("guidance.emergencyDelay")}</p>
      )}
    </Notice>
  );
}
