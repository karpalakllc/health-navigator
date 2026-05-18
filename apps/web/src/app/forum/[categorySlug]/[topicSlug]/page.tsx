import { notFound } from "next/navigation";
import { ForumPostCard } from "@/components/forum/forum-post-card";
import { ForumTopicModerationToolbar } from "@/components/forum/forum-topic-moderation-toolbar";
import { ReplyForm } from "@/components/forum/reply-form";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { PageHeader } from "@/components/directory/page-header";
import { Pagination } from "@/components/directory/pagination";
import { Badge } from "@/components/ui/badge";
import { LoginPrompt } from "@/components/ui/login-prompt";
import { PageSection } from "@/components/ui/page-section";
import { PageShell } from "@/components/ui/page-shell";
import { getSessionToken } from "@/lib/auth/session";
import { fetchForumTopicPage, type ForumPost } from "@/lib/api/forum";
import { ApiRequestError } from "@/lib/api/server";
import { formatForumLastActivity, formatForumReplyCount } from "@/lib/format";
import { t } from "@/i18n/t";

type TopicDetailPageProps = {
  params: Promise<{ categorySlug: string; topicSlug: string }>;
  searchParams: Promise<{ page?: string }>;
};

export default async function TopicDetailPage({
  params,
  searchParams,
}: TopicDetailPageProps) {
  const { categorySlug, topicSlug } = await params;
  const query = await searchParams;
  const page = query.page ? Number(query.page) : 1;
  const token = await getSessionToken();

  let data;

  try {
    data = await fetchForumTopicPage(
      categorySlug,
      topicSlug,
      Number.isFinite(page) ? page : 1,
    );
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

    throw error;
  }

  const { topic, posts, meta } = data;

  const originalPost: ForumPost = {
    id: 0,
    body: topic.body,
    author_name: topic.author_name,
    author: topic.author,
    published_at: topic.published_at,
  };

  return (
    <PageShell className="gap-6 pb-16">
      <Breadcrumbs
        items={[
          { label: t("common.home"), href: "/" },
          { label: t("forum.title"), href: "/forum" },
          { label: topic.category.name, href: `/forum/${categorySlug}` },
          { label: topic.title },
        ]}
      />

      <PageHeader
        title={topic.title}
        description={formatForumReplyCount(topic.replies_count)}
      />
      {!topic.viewer?.can_moderate ? (
        <div className="flex flex-wrap gap-2">
          {topic.is_pinned ? <Badge variant="primary">{t("forum.pinned")}</Badge> : null}
          {topic.is_locked ? <Badge variant="secondary">{t("forum.locked")}</Badge> : null}
        </div>
      ) : null}

      {topic.viewer?.can_moderate ? (
        <ForumTopicModerationToolbar
          categorySlug={categorySlug}
          topicSlug={topicSlug}
          isPinned={topic.is_pinned}
          isLocked={topic.is_locked}
        />
      ) : null}

      <ForumPostCard post={originalPost} isOriginalPost />

      <PageSection title={t("forum.replies")}>
        {posts.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t("forum.noReplies")}</p>
        ) : (
          <ul className="grid gap-3">
            {posts.map((post) => (
              <li key={post.id}>
                <ForumPostCard post={post} />
              </li>
            ))}
          </ul>
        )}
        <Pagination
          basePath={`/forum/${categorySlug}/${topicSlug}`}
          currentPage={meta.current_page}
          lastPage={meta.last_page}
          total={meta.total}
          searchParams={{}}
        />
      </PageSection>

      {topic.is_locked ? (
        <p className="text-sm text-muted-foreground">{t("forum.topicLocked")}</p>
      ) : token ? (
        <ReplyForm categorySlug={categorySlug} topicSlug={topicSlug} />
      ) : (
        <LoginPrompt suffix={t("forum.loginToReply")} />
      )}

      <p className="text-xs text-muted-foreground">
        {t("forum.lastActivity")}: {formatForumLastActivity(topic.published_at)}
      </p>
    </PageShell>
  );
}
