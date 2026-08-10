import Link from "next/link";
import { redirect } from "next/navigation";
import { AccountLayout } from "@/components/account/account-layout";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { ProfileContentCard } from "@/components/design/profile-content-card";
import { Pagination } from "@/components/directory/pagination";
import { ModerationStatusBadge } from "@/components/ui/moderation-status-badge";
import { PageShell } from "@/components/ui/page-shell";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { getSessionToken } from "@/lib/auth/session";
import { fetchMyForumPosts, fetchMyForumTopics } from "@/lib/api/forum";
import { ApiRequestError } from "@/lib/api/server";
import { formatForumLastActivity, formatForumReplyCount } from "@/lib/format";
import { t } from "@/i18n/t";
import { pageMetadata } from "@/lib/metadata";

export const metadata = pageMetadata(t("account.forumActivity"), undefined, { noIndex: true });

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
  const postsPage = params.posts_page ? Number(params.posts_page) : 1;

  let topics;
  let posts;

  try {
    topics = await fetchMyForumTopics(Number.isFinite(topicsPage) ? topicsPage : 1);
    posts = await fetchMyForumPosts(Number.isFinite(postsPage) ? postsPage : 1);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 401) {
      redirect("/login?redirect=/account/forum");
    }

    throw error;
  }

  return (
    <>
      <PageHeroBleed>
        <AccountPageHero
          badge={t("nav.myForum")}
          title={t("account.forumActivity")}
          description={t("account.forumHeroDescription")}
        />
      </PageHeroBleed>

      <PageShell className="pb-16">
        <AccountLayout current="forum">
          <ProfileContentCard title={t("account.topicsHeading")}>
            <ul className="divide-y divide-border/80">
              {topics.data.length === 0 ? (
                <li className="py-4 text-sm text-muted-foreground">{t("account.noTopicsYet")}</li>
              ) : (
                topics.data.map((topic) => (
                  <li key={`${topic.slug}-${topic.created_at}`} className="space-y-2 py-4 first:pt-0 last:pb-0">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                      <p className="font-semibold text-foreground">
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
                    {topic.status === "rejected" && topic.rejection_note ? (
                      <p className="rounded-xl border border-destructive/20 bg-destructive/5 px-3 py-2 text-sm text-muted-foreground">
                        <span className="font-medium text-foreground">{t("account.rejectionNote")}: </span>
                        {topic.rejection_note}
                      </p>
                    ) : null}
                  </li>
                ))
              )}
            </ul>
            <Pagination
              basePath="/account/forum"
              currentPage={topics.meta.current_page}
              lastPage={topics.meta.last_page}
              total={topics.meta.total}
              searchParams={{}}
              pageParam="topics_page"
            />
          </ProfileContentCard>

          <ProfileContentCard title={t("account.repliesHeading")}>
            <ul className="divide-y divide-border/80">
              {posts.data.length === 0 ? (
                <li className="py-4 text-sm text-muted-foreground">{t("account.noRepliesYet")}</li>
              ) : (
                posts.data.map((post) => (
                  <li key={post.id} className="space-y-2 py-4 first:pt-0 last:pb-0">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                      <p className="text-sm font-semibold text-foreground">
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
                    {post.status === "rejected" && post.rejection_note ? (
                      <p className="rounded-xl border border-destructive/20 bg-destructive/5 px-3 py-2 text-sm text-muted-foreground">
                        <span className="font-medium text-foreground">{t("account.rejectionNote")}: </span>
                        {post.rejection_note}
                      </p>
                    ) : null}
                  </li>
                ))
              )}
            </ul>
            <Pagination
              basePath="/account/forum"
              currentPage={posts.meta.current_page}
              lastPage={posts.meta.last_page}
              total={posts.meta.total}
              searchParams={{}}
              pageParam="posts_page"
            />
          </ProfileContentCard>
        </AccountLayout>
      </PageShell>
    </>
  );
}
