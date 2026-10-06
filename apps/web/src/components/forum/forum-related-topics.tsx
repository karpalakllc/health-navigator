import Link from "next/link";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import type { ForumTopicListItem } from "@/lib/api/forum";
import { formatForumReplyCount } from "@/lib/format";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

/** „Слични теми“: title + reply count rows with a chevron. */
export function ForumRelatedTopics({
  topics,
  categorySlug,
  className,
}: {
  topics: ForumTopicListItem[];
  categorySlug: string;
  className?: string;
}) {
  return (
    <Card
      as="section"
      padding="md"
      aria-labelledby="forum-related-heading"
      className={cn("flex flex-col gap-2", className)}
    >
      <h2 id="forum-related-heading" className="type-h3 text-ink">
        {t("forum.relatedTopics")}
      </h2>
      {topics.length === 0 ? (
        <p className="type-meta text-ink-2">{t("forum.noRelatedTopics")}</p>
      ) : (
        <ul>
          {topics.map((topic) => (
            <li
              key={topic.slug}
              className="relative flex min-h-14 items-center gap-3 border-t border-line py-3 first:border-t-0"
            >
              <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <Link
                  href={`/forum/${categorySlug}/${topic.slug}`}
                  className="font-ui text-[1.0625rem] leading-6 font-semibold text-ink decoration-coral decoration-2 underline-offset-4 after:absolute after:inset-0 after:content-[''] hover:underline"
                >
                  {topic.title}
                </Link>
                <p className="flex items-center gap-1.5 type-meta text-ink-2">
                  <Icon name="message-circle" size={16} />
                  {formatForumReplyCount(topic.replies_count)}
                </p>
              </div>
              <Icon name="chevron-right" size={20} className="text-ink" />
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}
