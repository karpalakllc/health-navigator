import { redirect } from "next/navigation";
import {
  AccountLayout,
  AccountPage,
} from "@/components/account/account-layout";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { Pagination } from "@/components/directory/pagination";
import {
  ForumActivityItem,
  ForumActivitySection,
} from "@/components/forum/forum-activity-list";
import { getSessionToken } from "@/lib/auth/session";
import { fetchMyForumPosts, fetchMyForumTopics } from "@/lib/api/forum";
import { ApiRequestError } from "@/lib/api/server";
import { formatForumLastActivity, formatForumReplyCount } from "@/lib/format";
import { t } from "@/i18n/t";
import { pageMetadata } from "@/lib/metadata";

export const metadata = pageMetadata(t("account.forumActivity"), undefined, {
  noIndex: true,
});

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
    topics = await fetchMyForumTopics(
      Number.isFinite(topicsPage) ? topicsPage : 1,
    );
    posts = await fetchMyForumPosts(Number.isFinite(postsPage) ? postsPage : 1);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 401) {
      redirect("/login?redirect=/account/forum");
    }

    throw error;
  }

  return (
    <AccountPage>
      <AccountPageHero
        badge={t("nav.myForum")}
        title={t("account.forumActivity")}
        description={t("account.forumHeroDescription")}
      />
      <AccountLayout current="forum">
        <ForumActivitySection
          id="my-forum-topics"
          title={t("account.topicsHeading")}
          pagination={
            <Pagination
              basePath="/account/forum"
              currentPage={topics.meta.current_page}
              lastPage={topics.meta.last_page}
              total={topics.meta.total}
              searchParams={{}}
              pageParam="topics_page"
              label={`${t("account.topicsHeading")}: ${t("pagination.label")}`}
            />
          }
        >
          {topics.data.length === 0 ? (
            <li className="px-5 py-4 type-body text-ink-2">
              {t("account.noTopicsYet")}
            </li>
          ) : (
            topics.data.map((topic) => (
              <ForumActivityItem
                key={`${topic.slug}-${topic.created_at}`}
                title={topic.title}
                href={
                  topic.status === "approved"
                    ? `/forum/${topic.category.slug}/${topic.slug}`
                    : undefined
                }
                status={topic.status}
                rejectionNote={
                  topic.status === "rejected" ? topic.rejection_note : null
                }
                meta={
                  <>
                    {topic.category.name}
                    <span aria-hidden="true"> · </span>
                    {formatForumReplyCount(topic.replies_count)}
                    {topic.last_post_at || topic.published_at ? (
                      <>
                        <span aria-hidden="true"> · </span>
                        {formatForumLastActivity(
                          topic.last_post_at ?? topic.published_at,
                        )}
                      </>
                    ) : null}
                  </>
                }
              />
            ))
          )}
        </ForumActivitySection>

        <ForumActivitySection
          id="my-forum-replies"
          title={t("account.repliesHeading")}
          pagination={
            <Pagination
              basePath="/account/forum"
              currentPage={posts.meta.current_page}
              lastPage={posts.meta.last_page}
              total={posts.meta.total}
              searchParams={{}}
              pageParam="posts_page"
              label={`${t("account.repliesHeading")}: ${t("pagination.label")}`}
            />
          }
        >
          {posts.data.length === 0 ? (
            <li className="px-5 py-4 type-body text-ink-2">
              {t("account.noRepliesYet")}
            </li>
          ) : (
            posts.data.map((post) => (
              <ForumActivityItem
                key={post.id}
                title={post.topic.title}
                href={
                  post.status === "approved"
                    ? `/forum/${post.topic.category_slug}/${post.topic.slug}`
                    : undefined
                }
                status={post.status}
                rejectionNote={
                  post.status === "rejected" ? post.rejection_note : null
                }
                body={post.body}
              />
            ))
          )}
        </ForumActivitySection>
      </AccountLayout>
    </AccountPage>
  );
}
