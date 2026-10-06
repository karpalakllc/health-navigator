import { notFound } from "next/navigation";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { Pagination } from "@/components/directory/pagination";
import { ForumColumns, forumPageClass } from "@/components/forum/forum-layout";
import { ForumPostCard } from "@/components/forum/forum-post-card";
import { ForumTopicModerationToolbar } from "@/components/forum/forum-topic-moderation-toolbar";
import { ForumTopicSidebar } from "@/components/forum/forum-topic-sidebar";
import { REPLY_FORM_ID, ReplyForm } from "@/components/forum/reply-form";
import { ReportButton } from "@/components/reports/report-button";
import { BackLink } from "@/components/ui/back-link";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Notice } from "@/components/ui/notice";
import { Tag } from "@/components/ui/tag";
import { ForumTagRow } from "@/components/forum/forum-tag-row";
import { JsonLd } from "@/components/seo/json-ld";
import { parseListPage } from "@/lib/api/directory-cache-policy";
import { breadcrumbJsonLd, forumTopicJsonLd } from "@/lib/structured-data";
import { absoluteUrl } from "@/lib/site-url";
import { getShellSession } from "@/lib/auth/header-session";
import { getSessionToken } from "@/lib/auth/session";
import {
  fetchForumCategories,
  fetchForumTopicPage,
  type ForumPost,
} from "@/lib/api/forum";
import { fetchPublicSettings } from "@/lib/api/settings";
import { isModuleOn } from "@/lib/api/public-settings";
import { ApiRequestError } from "@/lib/api/server";
import { formatForumLastActivity, formatForumReplyCount } from "@/lib/format";
import { forumTopicMeta, pageMetadata } from "@/lib/metadata";
import type { Metadata } from "next";
import { t, tFormat } from "@/i18n/t";

type TopicDetailPageProps = {
  params: Promise<{ categorySlug: string; topicSlug: string }>;
  searchParams: Promise<{ page?: string }>;
};

export async function generateMetadata({
  params,
  searchParams,
}: TopicDetailPageProps): Promise<Metadata> {
  const { categorySlug, topicSlug } = await params;
  const page = parseListPage((await searchParams).page);
  const settings = await fetchPublicSettings();
  const basePath = `/forum/${categorySlug}/${topicSlug}`;

  if (!settings.public_forum) {
    return pageMetadata(t("forum.title"), undefined, { noIndex: true });
  }

  try {
    const { topic } = await fetchForumTopicPage(categorySlug, topicSlug, page);
    const { title, description } = forumTopicMeta(topic);

    // Later pages carry other replies, so each is its own canonical URL.
    return pageMetadata(title, description, {
      path: page > 1 ? `${basePath}?page=${page}` : basePath,
      ogType: "article",
    });
  } catch (error) {
    // The not-found metadata (noindex), not a generic indexable title.
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

    return pageMetadata(t("forum.title"), undefined, { path: basePath });
  }
}

export default async function TopicDetailPage({
  params,
  searchParams,
}: TopicDetailPageProps) {
  const { categorySlug, topicSlug } = await params;
  const query = await searchParams;
  const page = parseListPage(query.page);
  const token = await getSessionToken();
  const redirectPath = `/forum/${categorySlug}/${topicSlug}`;

  const settings = await fetchPublicSettings();

  // Detail pages of a switched-off module do not exist; /forum explains why.
  if (!isModuleOn(settings, "public_forum")) {
    notFound();
  }

  let data;

  try {
    data = await fetchForumTopicPage(categorySlug, topicSlug, page);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

    throw error;
  }

  const { topic, posts, meta, related_topics: relatedTopics = [] } = data;

  // The topic count for the aside; the category list is cached and only
  // decorative here, so a failure just leaves the count out.
  const [categories, session] = await Promise.all([
    fetchForumCategories().catch(() => []),
    token ? getShellSession() : Promise.resolve(null),
  ]);
  const categoryCount = categories.find(
    (item) => item.slug === topic.category.slug,
  )?.topics_count;
  const viewer = session?.user
    ? {
        name: session.user.display_name,
        initials: session.user.avatar_initials || undefined,
      }
    : null;

  const originalPost: ForumPost = {
    id: 0,
    body: topic.body,
    author_name: topic.author_name,
    author: topic.author,
    published_at: topic.published_at,
  };

  const isLoggedIn = Boolean(token);
  const canReply = !topic.is_locked && isLoggedIn;
  const topicActions = (
    <div className="flex w-full flex-wrap items-center justify-between gap-2">
      {canReply ? (
        <Button href={`#${REPLY_FORM_ID}`} variant="soft" leadingIcon="reply">
          {t("forum.replyToTopic")}
        </Button>
      ) : (
        <span />
      )}
      {/* Not on one's own topic: the API refuses it. */}
      {topic.viewer?.is_own ? null : (
        <ReportButton
          target={{ kind: "forum_topic", categorySlug, topicSlug }}
          label={t("reports.actionTopic")}
          isLoggedIn={isLoggedIn}
          returnTo={redirectPath}
        />
      )}
    </div>
  );

  const topicUrl = absoluteUrl(redirectPath);
  const categoryUrl = absoluteUrl(`/forum/${categorySlug}`);
  const forumJsonLd = forumTopicJsonLd({
    topic,
    posts,
    url: topicUrl,
    category: { name: topic.category.name, url: categoryUrl },
  });
  const breadcrumbs = breadcrumbJsonLd([
    { name: t("common.home"), url: absoluteUrl("/") },
    { name: t("forum.title"), url: absoluteUrl("/forum") },
    { name: topic.category.name, url: categoryUrl },
    { name: topic.title },
  ]);

  return (
    <div className={`${forumPageClass} gap-6`}>
      {forumJsonLd ? <JsonLd data={forumJsonLd} /> : null}
      {breadcrumbs ? <JsonLd data={breadcrumbs} /> : null}
      <ForumColumns
        main={
          <>
            <div className="lg:hidden">
              <BackLink
                href={`/forum/${categorySlug}`}
                label={topic.category.name}
              />
            </div>
            <div className="-mb-6 hidden lg:block">
              <Breadcrumbs
                items={[
                  { label: t("common.home"), href: "/" },
                  { label: t("forum.title"), href: "/forum" },
                  {
                    label: topic.category.name,
                    href: `/forum/${categorySlug}`,
                  },
                  { label: topic.title },
                ]}
              />
            </div>

            <header className="flex flex-col gap-2">
              {topic.is_pinned || topic.is_locked ? (
                <div className="flex flex-wrap gap-2">
                  {topic.is_pinned ? (
                    <Tag tone="ink">{t("forum.pinned")}</Tag>
                  ) : null}
                  {topic.is_locked ? <Tag>{t("forum.locked")}</Tag> : null}
                </div>
              ) : null}
              <h1 className="type-h1 break-words text-ink">{topic.title}</h1>
              <ForumTagRow tags={topic.tags ?? []} />
              <p className="type-meta text-ink-2">
                {formatForumReplyCount(topic.replies_count)}
                <span aria-hidden="true"> · </span>
                {t("forum.lastActivity")}:{" "}
                {formatForumLastActivity(
                  topic.last_post_at ?? topic.published_at,
                )}
              </p>
            </header>

            {topic.viewer?.can_moderate ? (
              <ForumTopicModerationToolbar
                categorySlug={categorySlug}
                topicSlug={topicSlug}
                isPinned={topic.is_pinned}
                isLocked={topic.is_locked}
              />
            ) : null}

            <ForumPostCard
              post={originalPost}
              isOriginalPost
              isTopicAuthor
              actions={topicActions}
            />

            <section
              aria-labelledby="forum-replies-heading"
              className="flex flex-col gap-3 pt-2"
            >
              <h2 id="forum-replies-heading" className="type-h2 text-ink">
                {tFormat("forum.repliesHeading", {
                  count: topic.replies_count,
                })}
              </h2>
              {posts.length === 0 ? (
                <p className="type-body text-ink-2">{t("forum.noReplies")}</p>
              ) : (
                <ul className="flex flex-col gap-3">
                  {posts.map((post) => (
                    <li key={post.id} id={`post-${post.id}`}>
                      <ForumPostCard
                        post={post}
                        isTopicAuthor={post.is_topic_author === true}
                        actions={
                          post.viewer?.is_own ? undefined : (
                            <div className="flex w-full justify-end">
                              <ReportButton
                                target={{ kind: "forum_post", id: post.id }}
                                label={tFormat("reports.actionPost", {
                                  name: post.author.name,
                                })}
                                isLoggedIn={isLoggedIn}
                                returnTo={redirectPath}
                              />
                            </div>
                          )
                        }
                      />
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
            </section>

            {topic.is_locked ? (
              <Notice tone="info" icon="info">
                {t("forum.topicLocked")}
              </Notice>
            ) : token ? (
              <ReplyForm
                categorySlug={categorySlug}
                topicSlug={topicSlug}
                viewer={viewer}
              />
            ) : (
              <Card
                as="section"
                padding="md"
                aria-labelledby="forum-login-heading"
                className="flex flex-col items-start gap-3 lg:p-8"
              >
                <h2 id="forum-login-heading" className="type-h3 text-ink">
                  {t("forum.loginCtaTitle")}
                </h2>
                <p className="type-body text-ink-2">
                  {t("forum.loginCtaBody")}
                </p>
                <Button
                  href={`/login?redirect=${encodeURIComponent(redirectPath)}`}
                  size="lg"
                  className="w-full sm:w-auto"
                >
                  {t("forum.guestReplyCta")}
                </Button>
              </Card>
            )}
          </>
        }
        aside={
          <ForumTopicSidebar
            category={{
              name: topic.category.name,
              topics_count: categoryCount,
            }}
            categorySlug={categorySlug}
            relatedTopics={relatedTopics}
            settings={settings}
          />
        }
      />
    </div>
  );
}
