import Link from "next/link";
import type { ForumTopicSearchItem } from "@/lib/api/forum";
import { formatForumLastActivity, formatForumReplyCount } from "@/lib/format";
import { t } from "@/i18n/t";

export function ForumSearchTopicCard({ topic }: { topic: ForumTopicSearchItem }) {
  return (
    <Link
      href={`/forum/${topic.category.slug}/${topic.slug}`}
      className="group block"
    >
      <article className="directory-card card-lift rounded-[1.375rem] p-4 sm:p-5">
        <p className="directory-tag directory-tag-teal">{topic.category.name}</p>
        <h2 className="mt-2 text-base font-bold text-foreground group-hover:text-primary sm:text-lg">
          {topic.title}
        </h2>
        {topic.excerpt ? (
          <p className="mt-2 line-clamp-2 text-sm leading-relaxed text-muted-foreground">
            {topic.excerpt}
          </p>
        ) : null}
        <p className="mt-2 text-sm text-muted-foreground">
          <span className="font-medium text-foreground/90">{topic.author_name}</span>
          <span aria-hidden> · </span>
          {formatForumReplyCount(topic.replies_count)}
        </p>
        <p className="mt-1 text-xs text-muted-foreground">
          {t("forum.lastActivity")}:{" "}
          {formatForumLastActivity(topic.last_post_at ?? topic.published_at)}
        </p>
      </article>
    </Link>
  );
}
