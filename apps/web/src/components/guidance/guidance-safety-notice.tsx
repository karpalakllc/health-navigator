import { Icon } from "@/components/ui/icons";
import { Notice, NoticeTelLink } from "@/components/ui/notice";
import { t } from "@/i18n/t";

/*
 * „Не е дијагноза“. The full note sits on the unavailable state and the
 * results; `compact` is the one-line 194/112 safety-netting under a result's
 * „watch for“ list. The intro and the steps carry neither (owner decision
 * 2026-10-23; the intro's consent checkbox is the pre-start gate).
 */
export function GuidanceSafetyNotice({
  compact = false,
}: {
  compact?: boolean;
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
      <p className="mt-2">{t("guidance.emergencyDelay")}</p>
    </Notice>
  );
}
