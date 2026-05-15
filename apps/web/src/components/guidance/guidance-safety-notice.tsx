import { t } from "@/i18n/t";

export function GuidanceSafetyNotice({ compact = false }: { compact?: boolean }) {
  if (compact) {
    return (
      <p className="text-xs text-zinc-500">
        {t("forum.safetyEmergency")}
      </p>
    );
  }

  return (
    <aside className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
      <p className="font-medium">{t("guidance.notDiagnosis")}</p>
      <p className="mt-2">{t("guidance.notDiagnosisBody")}</p>
      <p className="mt-2">{t("guidance.emergencyDelay")}</p>
    </aside>
  );
}
