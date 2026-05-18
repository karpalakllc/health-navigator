import type { PublicSettings } from "@/lib/api/settings";
import { t } from "@/i18n/t";

type ForumCommunityRulesProps = {
  compact?: boolean;
  settings?: Pick<
    PublicSettings,
    "forum_rules_enabled" | "forum_rules_title" | "forum_rules_body"
  >;
};

export function ForumCommunityRules({ compact = false, settings }: ForumCommunityRulesProps) {
  if (settings && !settings.forum_rules_enabled) {
    return null;
  }

  const customRules =
    settings?.forum_rules_body
      ?.split("\n")
      .map((line) => line.trim())
      .filter(Boolean) ?? [];

  const rules =
    customRules.length > 0
      ? customRules
      : [
          t("forum.rulesRespect"),
          t("forum.rulesNoDiagnosis"),
          t("forum.rulesModeration"),
          t("forum.rulesLimits"),
          t("forum.rulesEmergency"),
        ];

  const title = settings?.forum_rules_title?.trim() || t("forum.rulesTitle");

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
        {title}
      </h2>
      <ul className="mt-3 list-disc space-y-2 pl-5 text-muted-foreground">
        {rules.map((rule) => (
          <li key={rule}>{rule}</li>
        ))}
      </ul>
    </section>
  );
}
