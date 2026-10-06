import Link from "next/link";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import { formatForumReplyCount } from "@/lib/format";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

export type RelatedTopicLink = {
  slug: string;
  title: string;
  replies_count: number;
  /** Present on cross-category results (shared keywords, profiles). */
  category?: { slug: string; name: string };
};

/**
 * „Слични теми“: title + reply count rows with a chevron. Also the „Од
 * форумот“ box on doctor and facility profiles (title/lead props), where an
 * empty list renders nothing rather than an empty card.
 */
export function ForumRelatedTopics({
  topics,
  categorySlug,
  title = t("forum.relatedTopics"),
  lead,
  headingId = "forum-related-heading",
  hideWhenEmpty = false,
  className,
}: {
  topics: RelatedTopicLink[];
  /** Fallback for items without a category (same-category results). */
  categorySlug?: string;
  title?: string;
  lead?: string;
  headingId?: string;
  hideWhenEmpty?: boolean;
  className?: string;
}) {
  const linked = topics.filter((topic) => topic.category?.slug ?? categorySlug);

  if (hideWhenEmpty && linked.length === 0) {
    return null;
  }

  return (
    <Card
      as="section"
      padding="md"
      aria-labelledby={headingId}
      className={cn("flex flex-col gap-2", className)}
    >
      <h2 id={headingId} className="type-h3 text-ink">
        {title}
      </h2>
      {lead ? <p className="type-meta text-ink-2">{lead}</p> : null}
      {linked.length === 0 ? (
        <p className="type-meta text-ink-2">{t("seo.relatedEmpty")}</p>
      ) : (
        <ul>
          {linked.map((topic) => (
            <li
              key={`${topic.category?.slug ?? categorySlug}/${topic.slug}`}
              className="relative flex min-h-14 items-center gap-3 border-t border-line py-3 first:border-t-0"
            >
              <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <Link
                  href={`/forum/${topic.category?.slug ?? categorySlug}/${topic.slug}`}
                  className="type-body font-semibold text-ink link-grow after:absolute after:inset-0 after:content-['']"
                >
                  {topic.title}
                </Link>
                <p className="flex items-center gap-1.5 type-meta text-ink-2">
                  <Icon name="message-circle" size={16} />
                  {formatForumReplyCount(topic.replies_count)}
                  {topic.category && topic.category.slug !== categorySlug ? (
                    <>
                      <span aria-hidden="true">·</span>
                      {topic.category.name}
                    </>
                  ) : null}
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
