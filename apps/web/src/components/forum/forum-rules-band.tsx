import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import type { PublicSettings } from "@/lib/api/settings";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

type RulesSettings = Pick<
  PublicSettings,
  "forum_rules_enabled" | "forum_rules_title" | "forum_rules_body"
>;

type ForumRulesCardProps = {
  settings?: RulesSettings;
  /** Unique per page: the section is labelled by its heading. */
  headingId?: string;
  headingLevel?: 2 | 3;
  className?: string;
};

/** The admin's rules (one per line), or the default set. */
export function forumRules(settings?: RulesSettings): string[] {
  const customRules =
    settings?.forum_rules_body
      ?.split("\n")
      .map((line) => line.trim())
      .filter(Boolean) ?? [];

  return customRules.length > 0
    ? customRules
    : [
        t("forum.rulesRespect"),
        t("forum.rulesNoDiagnosis"),
        t("forum.rulesModeration"),
        t("forum.rulesEmergency"),
      ];
}

/** „Правила на заедницата“: a sand card with check rows. */
export function ForumRulesCard({
  settings,
  headingId = "forum-rules-heading",
  headingLevel = 2,
  className,
}: ForumRulesCardProps) {
  if (settings && !settings.forum_rules_enabled) {
    return null;
  }

  const rules = forumRules(settings);
  const title = settings?.forum_rules_title?.trim() || t("forum.rulesTitle");
  const Heading = headingLevel === 2 ? "h2" : "h3";

  return (
    <Card
      as="section"
      tone="sand"
      padding="md"
      aria-labelledby={headingId}
      className={cn("flex flex-col gap-3", className)}
    >
      <Heading
        id={headingId}
        className="flex items-center gap-2.5 type-h3 text-ink"
      >
        <Icon name="shield-check" size={24} />
        {title}
      </Heading>
      <ul className="flex flex-col gap-2.5">
        {rules.map((rule) => (
          <li key={rule} className="flex gap-2.5 type-meta text-ink">
            <Icon name="check" size={20} className="mt-0.5 text-care" />
            <span>{rule}</span>
          </li>
        ))}
      </ul>
    </Card>
  );
}
