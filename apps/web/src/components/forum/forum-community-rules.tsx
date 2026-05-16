import { t } from "@/i18n/t";

export function ForumCommunityRules({ compact = false }: { compact?: boolean }) {
  const rules = [
    t("forum.rulesRespect"),
    t("forum.rulesNoDiagnosis"),
    t("forum.rulesModeration"),
    t("forum.rulesLimits"),
    t("forum.rulesEmergency"),
  ];

  if (compact) {
    return (
      <p className="text-xs text-muted-foreground">
        {t("forum.rulesCompact")}
      </p>
    );
  }

  return (
    <section
      className="rounded-xl border border-border bg-muted/30 p-4 text-sm"
      aria-labelledby="forum-rules-heading"
    >
      <h2 id="forum-rules-heading" className="font-semibold text-foreground">
        {t("forum.rulesTitle")}
      </h2>
      <ul className="mt-3 list-disc space-y-2 pl-5 text-muted-foreground">
        {rules.map((rule) => (
          <li key={rule}>{rule}</li>
        ))}
      </ul>
    </section>
  );
}
