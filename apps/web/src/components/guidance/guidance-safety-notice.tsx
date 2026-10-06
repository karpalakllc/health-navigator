import { Icon } from "@/components/ui/icons";
import { Notice } from "@/components/ui/notice";
import { t } from "@/i18n/t";

/*
 * „Не е дијагноза“. The full note sits on the intro, the unavailable state and
 * the results; `compact` is the one-line 194/112 reminder every question step
 * carries (docs/triage-safety.md, „Every step“).
 */
export function GuidanceSafetyNotice({
  compact = false,
}: {
  compact?: boolean;
}) {
  if (compact) {
    return (
      <p className="flex items-start gap-2 type-meta text-ink-2">
        <Icon name="info" size={20} className="mt-0.5 shrink-0" />
        <span>{t("forum.safetyEmergency")}</span>
      </p>
    );
  }

  return (
    <Notice tone="safety" title={t("guidance.notDiagnosis")}>
      <p>{t("guidance.notDiagnosisBody")}</p>
      <p className="mt-2">{t("guidance.emergencyDelay")}</p>
    </Notice>
  );
}
