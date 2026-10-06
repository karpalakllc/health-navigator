import Link from "next/link";
import { Icon } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import { Monogram } from "@/components/ui/user-avatar";
import type { ForumTopicSearchItem } from "@/lib/api/forum";
import { formatForumLastActivity, formatForumReplyCount } from "@/lib/format";
import { t } from "@/i18n/t";

/**
 * A forum topic teaser (home „Од заедницата“, /search forum results): category
 * tag, title as the link, author monogram + time, and the reply count — or
 * „Без одговор“ for a topic nobody has answered yet.
 */
export function CommunityTopicCard({
  topic,
  headingLevel = 3,
  showExcerpt = false,
}: {
  topic: ForumTopicSearchItem;
  headingLevel?: 2 | 3;
  showExcerpt?: boolean;
}) {
  const Heading = headingLevel === 2 ? "h2" : "h3";
  const href = `/forum/${topic.category.slug}/${topic.slug}`;

  return (
    <article className="card relative p-4 lg:p-5">
      <Tag>{topic.category.name}</Tag>
      <Heading className="mt-3 font-ui text-lg font-semibold leading-6 text-ink lg:text-[1.1875rem]">
        {/* The stretched link makes the whole card clickable; the title is
            its accessible name. */}
        <Link
          href={href}
          className="after:absolute after:inset-0 after:rounded-card hover:underline hover:decoration-coral hover:decoration-2 hover:underline-offset-4"
        >
          {topic.title}
        </Link>
      </Heading>
      {showExcerpt && topic.excerpt ? (
        <p className="mt-1 line-clamp-2 type-meta text-ink-2">
          {topic.excerpt}
        </p>
      ) : null}
      <div className="mt-3 flex items-center gap-2">
        <Monogram name={topic.author_name} size={28} />
        <p className="type-meta min-w-0 flex-1 truncate text-ink-2">
          {topic.author_name}
          <span aria-hidden="true"> · </span>
          <span className="sr-only">, </span>
          {formatForumLastActivity(topic.last_post_at ?? topic.published_at)}
        </p>
        {topic.replies_count > 0 ? (
          <span className="inline-flex items-center gap-1 font-ui font-semibold text-ink">
            <Icon name="message-circle" size={20} />
            <span aria-hidden="true">{topic.replies_count}</span>
            <span className="sr-only">
              {formatForumReplyCount(topic.replies_count)}
            </span>
          </span>
        ) : (
          <Tag tone="tint">{t("home.communityUnanswered")}</Tag>
        )}
      </div>
    </article>
  );
}
