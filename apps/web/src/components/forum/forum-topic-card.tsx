import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import type { ForumTopicListItem } from "@/lib/api/forum";
import { formatForumReplyCount } from "@/lib/format";
import { t } from "@/i18n/t";

export function ForumTopicCard({
  topic,
  categorySlug,
}: {
  topic: ForumTopicListItem;
  categorySlug: string;
}) {
  return (
    <Link href={`/forum/${categorySlug}/${topic.slug}`} className="block">
      <Card className="card-hover p-4">
        <div className="flex flex-wrap items-start gap-2">
          {topic.is_pinned ? (
            <Badge variant="primary">{t("forum.pinned")}</Badge>
          ) : null}
        </div>
        <h2 className="mt-2 font-semibold text-foreground">{topic.title}</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          {topic.author_name} · {formatForumReplyCount(topic.replies_count)}
        </p>
      </Card>
    </Link>
  );
}
