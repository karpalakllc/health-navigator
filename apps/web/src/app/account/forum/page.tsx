import Link from "next/link";
import { redirect } from "next/navigation";
import { AccountLayout } from "@/components/account/account-layout";
import { PageHeader } from "@/components/directory/page-header";
import { Pagination } from "@/components/directory/pagination";
import { ModerationStatusBadge } from "@/components/ui/moderation-status-badge";
import { PageSection } from "@/components/ui/page-section";
import { PageShell } from "@/components/ui/page-shell";
import { StackedList } from "@/components/ui/stacked-list";
import { getSessionToken } from "@/lib/auth/session";
import { fetchMyForumPosts, fetchMyForumTopics } from "@/lib/api/forum";
import { ApiRequestError } from "@/lib/api/server";
import { formatForumLastActivity, formatForumReplyCount } from "@/lib/format";
import { t } from "@/i18n/t";

type AccountForumPageProps = {
  searchParams: Promise<{ topics_page?: string; posts_page?: string }>;
};

export default async function AccountForumPage({
  searchParams,
}: AccountForumPageProps) {
  const token = await getSessionToken();

  if (!token) {
    redirect("/login?redirect=/account/forum");
  }

  const params = await searchParams;
  const topicsPage = params.topics_page ? Number(params.topics_page) : 1;

  let topics;
  let posts;

  try {
    topics = await fetchMyForumTopics(Number.isFinite(topicsPage) ? topicsPage : 1);
    posts = await fetchMyForumPosts(1);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 401) {
      redirect("/login?redirect=/account/forum");
    }

    throw error;
  }

  return (
    <PageShell>
      <AccountLayout current="forum">
        <PageHeader
          title={t("account.forumActivity")}
          description={t("forum.description")}
        />

        <PageSection title={t("account.topicsHeading")}>
          <StackedList>
            {topics.data.length === 0 ? (
              <li className="p-4 text-sm text-muted-foreground">{t("account.noTopicsYet")}</li>
            ) : (
              topics.data.map((topic) => (
                <li key={`${topic.slug}-${topic.created_at}`} className="space-y-2 p-4">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <p className="font-medium text-foreground">
                      {topic.status === "approved" ? (
                        <Link
                          href={`/forum/${topic.category.slug}/${topic.slug}`}
                          className="text-primary underline-offset-2 hover:underline"
                        >
                          {topic.title}
                        </Link>
                      ) : (
                        topic.title
                      )}
                    </p>
                    <ModerationStatusBadge status={topic.status} />
                  </div>
                  <p className="text-sm text-muted-foreground">
                    {topic.category.name}
                    <span aria-hidden> · </span>
                    {formatForumReplyCount(topic.replies_count)}
                    {topic.last_post_at || topic.published_at ? (
                      <>
                        <span aria-hidden> · </span>
                        {t("forum.lastActivity")}:{" "}
                        {formatForumLastActivity(topic.last_post_at ?? topic.published_at)}
                      </>
                    ) : null}
                  </p>
                </li>
              ))
            )}
          </StackedList>
          <Pagination
            basePath="/account/forum"
            currentPage={topics.meta.current_page}
            lastPage={topics.meta.last_page}
            total={topics.meta.total}
            searchParams={{}}
            pageParam="topics_page"
          />
        </PageSection>

        <PageSection title={t("account.repliesHeading")}>
          <StackedList>
            {posts.data.length === 0 ? (
              <li className="p-4 text-sm text-muted-foreground">{t("account.noRepliesYet")}</li>
            ) : (
              posts.data.map((post) => (
                <li key={post.id} className="space-y-2 p-4">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <p className="text-sm font-medium text-foreground">
                      {post.status === "approved" ? (
                        <Link
                          href={`/forum/${post.topic.category_slug}/${post.topic.slug}`}
                          className="text-primary underline-offset-2 hover:underline"
                        >
                          {post.topic.title}
                        </Link>
                      ) : (
                        post.topic.title
                      )}
                    </p>
                    <ModerationStatusBadge status={post.status} />
                  </div>
                  {post.body ? (
                    <p className="whitespace-pre-wrap text-sm text-muted-foreground">{post.body}</p>
                  ) : null}
                </li>
              ))
            )}
          </StackedList>
          <Pagination
            basePath="/account/forum"
            currentPage={posts.meta.current_page}
            lastPage={posts.meta.last_page}
            total={posts.meta.total}
            searchParams={{}}
            pageParam="posts_page"
          />
        </PageSection>
      </AccountLayout>
    </PageShell>
  );
}
