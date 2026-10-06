import Link from "next/link";
import { ForumReplyCount } from "@/components/forum/forum-topic-row";
import type { ForumTopicSearchItem } from "@/lib/api/forum";
import { formatForumLastActivity } from "@/lib/format";

/**
 * A single topic as a standalone card (site-wide search results). Inside the
 * forum, topics are listed with ForumTopicList's compact rows instead.
 */
export function ForumSearchTopicCard({
  topic,
}: {
  topic: ForumTopicSearchItem;
}) {
  const lastActivity = topic.last_post_at ?? topic.published_at;

  return (
    <article className="card relative flex items-start gap-4 p-4 hover:bg-cream sm:p-5">
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <h2 className="line-clamp-2 font-ui text-[1.1875rem] leading-[1.625rem] font-semibold text-ink">
          <Link
            href={`/forum/${topic.category.slug}/${topic.slug}`}
            className="decoration-coral decoration-2 underline-offset-4 after:absolute after:inset-0 after:content-[''] hover:underline"
          >
            {topic.title}
          </Link>
        </h2>
        {topic.excerpt ? (
          <p className="line-clamp-2 type-body text-ink-2">{topic.excerpt}</p>
        ) : null}
        <p className="type-meta text-ink-2">
          {topic.category.name}
          <span aria-hidden="true"> · </span>
          {topic.author_name}
          {lastActivity ? (
            <>
              <span aria-hidden="true"> · </span>
              <time dateTime={lastActivity}>
                {formatForumLastActivity(lastActivity)}
              </time>
            </>
          ) : null}
        </p>
      </div>
      <ForumReplyCount count={topic.replies_count} />
    </article>
  );
}
