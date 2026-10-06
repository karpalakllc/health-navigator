import { ForumCategorySummary } from "@/components/forum/forum-category-card";
import { ForumRelatedTopics } from "@/components/forum/forum-related-topics";
import { ForumRulesCard } from "@/components/forum/forum-rules-band";
import type { ForumCategory, ForumTopicListItem } from "@/lib/api/forum";
import type { PublicSettings } from "@/lib/api/settings";

type ForumTopicSidebarProps = {
  category: Pick<ForumCategory, "name" | "topics_count">;
  categorySlug: string;
  relatedTopics: ForumTopicListItem[];
  settings: Pick<
    PublicSettings,
    "forum_rules_enabled" | "forum_rules_title" | "forum_rules_body"
  >;
};

/**
 * Thread aside (4 of 12 columns, sticky on desktop): the category, the rules
 * and „Слични теми“. On mobile it follows the thread; the category card is
 * left out there because the back pill above the title already leads to it.
 */
export function ForumTopicSidebar({
  category,
  categorySlug,
  relatedTopics,
  settings,
}: ForumTopicSidebarProps) {
  return (
    <aside className="flex flex-col gap-5 lg:sticky lg:top-[calc(var(--header-h)+1.5rem)] lg:self-start">
      <div className="hidden lg:block">
        <ForumCategorySummary category={category} slug={categorySlug} />
      </div>
      <ForumRulesCard
        settings={settings}
        className="order-last lg:order-none"
      />
      <ForumRelatedTopics topics={relatedTopics} categorySlug={categorySlug} />
    </aside>
  );
}
