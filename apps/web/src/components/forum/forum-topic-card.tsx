import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import type { ForumTopicListItem } from "@/lib/api/forum";
import { formatForumLastActivity, formatForumReplyCount } from "@/lib/format";
import { t } from "@/i18n/t";

export function ForumTopicCard({
  topic,
  categorySlug,
}: {
  topic: ForumTopicListItem;
  categorySlug: string;
}) {
  return (
    <Link href={`/forum/${categorySlug}/${topic.slug}`} className="group block">
      <Card className="card-hover p-4 sm:p-5">
        <div className="flex flex-wrap items-center gap-2">
          {topic.is_pinned ? <Badge variant="primary">{t("forum.pinned")}</Badge> : null}
          {topic.is_locked ? <Badge variant="secondary">{t("forum.locked")}</Badge> : null}
        </div>
        <h2 className="mt-2 text-base font-semibold text-foreground group-hover:text-primary sm:text-lg">
          {topic.title}
        </h2>
        <p className="mt-2 text-sm text-muted-foreground">
          <span className="font-medium text-foreground/90">{topic.author_name}</span>
          <span aria-hidden> · </span>
          {formatForumReplyCount(topic.replies_count)}
        </p>
        <p className="mt-1 text-xs text-muted-foreground">
          {t("forum.lastActivity")}: {formatForumLastActivity(topic.last_post_at ?? topic.published_at)}
        </p>
      </Card>
    </Link>
  );
}
