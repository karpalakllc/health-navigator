import { notFound } from "next/navigation";
import { ForumSafetyNotice } from "@/components/forum/forum-safety-notice";
import { ReplyForm } from "@/components/forum/reply-form";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { PageHeader } from "@/components/directory/page-header";
import { Pagination } from "@/components/directory/pagination";
import { LoginPrompt } from "@/components/ui/login-prompt";
import { PageSection } from "@/components/ui/page-section";
import { PageShell } from "@/components/ui/page-shell";
import { Card } from "@/components/ui/card";
import { StackedList } from "@/components/ui/stacked-list";
import { getSessionToken } from "@/lib/auth/session";
import { fetchForumTopicPage } from "@/lib/api/forum";
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
  } catch {
    notFound();
  }

  const { topic, posts, meta } = data;

  return (
    <PageShell className="gap-6">
      <Breadcrumbs
        items={[
          { label: t("common.home"), href: "/" },
          { label: t("forum.title"), href: "/forum" },
          { label: topic.category.name, href: `/forum/${categorySlug}` },
          { label: topic.title },
        ]}
      />

      <PageHeader title={topic.title} />
      <ForumSafetyNotice compact />

      <Card className="space-y-3 p-5">
        <p className="text-sm text-muted-foreground">
          {topic.author_name}
          {topic.is_locked ? ` ${t("forum.lockedSuffix")}` : ""}
        </p>
        <p className="whitespace-pre-wrap text-sm leading-relaxed text-foreground">{topic.body}</p>
      </Card>

      <PageSection title={t("forum.replies")}>
        {posts.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t("forum.noReplies")}</p>
        ) : (
          <StackedList>
            {posts.map((post) => (
              <li key={post.id} className="space-y-1 p-4">
                <p className="text-sm font-medium text-foreground">{post.author_name}</p>
                <p className="whitespace-pre-wrap text-sm text-muted-foreground">{post.body}</p>
              </li>
            ))}
          </StackedList>
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
    </PageShell>
  );
}
