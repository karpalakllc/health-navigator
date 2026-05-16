import Link from "next/link";
import { Card } from "@/components/ui/card";
import type { ForumTopicSearchItem } from "@/lib/api/forum";
import { formatForumLastActivity, formatForumReplyCount } from "@/lib/format";
import { t } from "@/i18n/t";

export function ForumSearchTopicCard({ topic }: { topic: ForumTopicSearchItem }) {
  return (
    <Link
      href={`/forum/${topic.category.slug}/${topic.slug}`}
      className="group block"
    >
      <Card className="card-hover p-4 sm:p-5">
        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
          {topic.category.name}
        </p>
        <h2 className="mt-1 text-base font-semibold text-foreground group-hover:text-primary sm:text-lg">
          {topic.title}
        </h2>
        <p className="mt-2 text-sm text-muted-foreground">
          <span className="font-medium text-foreground/90">{topic.author_name}</span>
          <span aria-hidden> · </span>
          {formatForumReplyCount(topic.replies_count)}
        </p>
        <p className="mt-1 text-xs text-muted-foreground">
          {t("forum.lastActivity")}:{" "}
          {formatForumLastActivity(topic.last_post_at ?? topic.published_at)}
        </p>
      </Card>
    </Link>
  );
}
