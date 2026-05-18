import type { PublicSettings } from "@/lib/api/settings";
import { t } from "@/i18n/t";

type ForumRulesBandProps = {
  settings?: Pick<
    PublicSettings,
    "forum_rules_enabled" | "forum_rules_title" | "forum_rules_body"
  >;
};

export function ForumRulesBand({ settings }: ForumRulesBandProps) {
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
          t("forum.rulesEmergency"),
        ];

  const title = settings?.forum_rules_title?.trim() || t("forum.rulesTitle");

  return (
    <section
      className="content-card rounded-[1.625rem] border-l-4 border-l-primary p-6"
      aria-labelledby="forum-rules-band-heading"
    >
      <h2 id="forum-rules-band-heading" className="text-lg font-black tracking-tight text-foreground">
        {title}
      </h2>
      <ul className="mt-4 grid gap-2 sm:grid-cols-2">
        {rules.map((rule) => (
          <li key={rule} className="flex gap-2.5 text-sm leading-relaxed text-muted-foreground">
            <span className="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full icon-soft-teal text-xs font-bold">
              ✓
            </span>
            <span>{rule}</span>
          </li>
        ))}
      </ul>
    </section>
  );
}
