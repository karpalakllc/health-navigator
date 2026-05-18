import Link from "next/link";
import { ProfileContentCard } from "@/components/design/profile-content-card";
import type { ForumTopicListItem } from "@/lib/api/forum";
import { formatForumReplyCount } from "@/lib/format";
import { t } from "@/i18n/t";

export function ForumRelatedTopics({
  topics,
  categorySlug,
}: {
  topics: ForumTopicListItem[];
  categorySlug: string;
}) {
  return (
    <ProfileContentCard title={t("forum.relatedTopics")}>
      {topics.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t("forum.noRelatedTopics")}</p>
      ) : (
      <ul className="space-y-3">
        {topics.map((topic) => (
          <li key={topic.slug}>
            <Link
              href={`/forum/${categorySlug}/${topic.slug}`}
              className="block rounded-xl border border-border/80 bg-muted/20 px-3 py-2.5 transition hover:border-primary/30 hover:bg-primary/5"
            >
              <p className="font-semibold text-foreground">{topic.title}</p>
              <p className="mt-1 text-xs text-muted-foreground">
                {formatForumReplyCount(topic.replies_count)}
              </p>
            </Link>
          </li>
        ))}
      </ul>
      )}
    </ProfileContentCard>
  );
}
