import { t } from "@/i18n/t";

export function ForumSafetyNotice({ compact = false }: { compact?: boolean }) {
  if (compact) {
    return (
      <p className="text-xs text-muted-foreground">
        {t("forum.safetyEmergency")}
      </p>
    );
  }

  return (
    <aside className="rounded-xl border border-warning/35 bg-warning/10 p-4 text-sm text-foreground">
      <p className="font-semibold">{t("forum.safetyTitle")}</p>
      <p className="mt-2 text-muted-foreground">{t("forum.safetyBody")}</p>
      <p className="mt-2 text-muted-foreground">{t("forum.safetyEmergency")}</p>
    </aside>
  );
}
