import { notFound } from "next/navigation";
import { HeroMeshCard } from "@/components/design/hero-mesh-card";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { ForumPostCard } from "@/components/forum/forum-post-card";
import { ForumTopicModerationToolbar } from "@/components/forum/forum-topic-moderation-toolbar";
import { ForumTopicSidebar } from "@/components/forum/forum-topic-sidebar";
import { ReplyForm } from "@/components/forum/reply-form";
import { Pagination } from "@/components/directory/pagination";
import { Badge } from "@/components/ui/badge";
import { PageSection } from "@/components/ui/page-section";
import { PageShell } from "@/components/ui/page-shell";
import { getSessionToken } from "@/lib/auth/session";
import { fetchForumTopicPage, type ForumPost } from "@/lib/api/forum";
import { fetchPublicSettingsServer } from "@/lib/api/settings";
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
  const redirectPath = `/forum/${categorySlug}/${topicSlug}`;

  let data;
  let settings;

  try {
    [data, settings] = await Promise.all([
      fetchForumTopicPage(
        categorySlug,
        topicSlug,
        Number.isFinite(page) ? page : 1,
      ),
      fetchPublicSettingsServer(),
    ]);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

    throw error;
  }

  const { topic, posts, meta, related_topics: relatedTopics = [] } = data;

  const originalPost: ForumPost = {
    id: 0,
    body: topic.body,
    author_name: topic.author_name,
    author: topic.author,
    published_at: topic.published_at,
  };

  return (
    <>
      <PageHeroBleed>
        <HeroMeshCard variant="profile" align="start" className="w-full max-w-none">
          <Breadcrumbs
            items={[
              { label: t("common.home"), href: "/" },
              { label: t("forum.title"), href: "/forum" },
              { label: topic.category.name, href: `/forum/${categorySlug}` },
              { label: topic.title },
            ]}
          />
          <div className="mt-4 flex flex-wrap gap-2">
            {topic.is_pinned ? <Badge variant="primary">{t("forum.pinned")}</Badge> : null}
            {topic.is_locked ? <Badge variant="secondary">{t("forum.locked")}</Badge> : null}
          </div>
          <h1 className="mt-4 text-3xl font-black tracking-tight text-foreground sm:text-4xl">
            {topic.title}
          </h1>
          <p className="mt-3 text-sm text-muted-foreground">
            {formatForumReplyCount(topic.replies_count)}
            <span aria-hidden> · </span>
            {t("forum.lastActivity")}: {formatForumLastActivity(topic.published_at)}
          </p>
        </HeroMeshCard>
      </PageHeroBleed>

      <PageShell className="gap-8 pb-16">
        <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(260px,320px)]">
          <div className="min-w-0 space-y-6">
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
                basePath={redirectPath}
                currentPage={meta.current_page}
                lastPage={meta.last_page}
                total={meta.total}
                searchParams={{}}
              />
            </PageSection>

            {topic.is_locked ? (
              <p className="content-card rounded-[1.625rem] p-4 text-sm text-muted-foreground">
                {t("forum.topicLocked")}
              </p>
            ) : token ? (
              <ReplyForm categorySlug={categorySlug} topicSlug={topicSlug} />
            ) : null}
          </div>

          <ForumTopicSidebar
            author={topic.author}
            categorySlug={categorySlug}
            categoryName={topic.category.name}
            relatedTopics={relatedTopics}
            settings={settings}
            isLoggedIn={Boolean(token)}
            redirectPath={redirectPath}
          />
        </div>
      </PageShell>
    </>
  );
}
