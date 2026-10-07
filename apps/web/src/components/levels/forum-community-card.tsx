import { TextLink } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

/**
 * Forum hub aside: a quiet pointer to „Заедница“ (/community), the monthly
 * top lists and how titles are earned. No figures here, so the forum page
 * does not wait on the leaderboard call.
 */
export function ForumCommunityCard() {
  return (
    <Card
      as="section"
      tone="sand"
      padding="md"
      aria-labelledby="forum-community-heading"
      className="flex flex-col gap-2"
    >
      <h2
        id="forum-community-heading"
        className="flex items-center gap-2.5 type-h3 text-ink"
      >
        <Icon name="award" size={24} />
        {t("levels.pageTitle")}
      </h2>
      <p className="type-meta text-ink-2">{t("levels.forumHubLead")}</p>
      <TextLink href="/community" trailingIcon="arrow-right">
        {t("levels.forumHubLink")}
      </TextLink>
    </Card>
  );
}
