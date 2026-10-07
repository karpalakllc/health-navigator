import { Pagination } from "@/components/directory/pagination";
import { ForumCategoryList } from "@/components/forum/forum-category-card";
import { ForumHubActions } from "@/components/forum/forum-hub-actions";
import { ForumHubSearch } from "@/components/forum/forum-hub-search";
import {
  ForumColumns,
  ForumEmpty,
  ForumPageHead,
  forumPageClass,
} from "@/components/forum/forum-layout";
import { ForumRulesCard } from "@/components/forum/forum-rules-band";
import { ForumSafetyNotice } from "@/components/forum/forum-safety-notice";
import { ForumViewChips } from "@/components/forum/forum-view-chips";
import {
  ForumTopicList,
  type ForumTopicRowData,
} from "@/components/forum/forum-topic-row";
import { ComingSoonShell } from "@/components/layout/coming-soon-shell";
import { ForumCommunityCard } from "@/components/levels/forum-community-card";
import { Button, TextLink } from "@/components/ui/button";
import { SectionHeader } from "@/components/ui/section-header";
import { getSessionToken } from "@/lib/auth/session";
import {
  fetchForumCategories,
  fetchForumRecentTopics,
  fetchForumTopicSearch,
  fetchForumUnansweredTopics,
  type ForumTopicSearchItem,
} from "@/lib/api/forum";
import { fetchPublicSettings } from "@/lib/api/settings";
import { isModuleOn } from "@/lib/api/public-settings";
import { forumAnswerHref } from "@/lib/forum/answer-link";
import { listCanonicalPath, pageMetadata } from "@/lib/metadata";
import { t, tCount } from "@/i18n/t";
import type { Metadata } from "next";

type ForumPageProps = {
  searchParams: Promise<{
    q?: string;
    category?: string;
    page?: string;
    view?: string;
  }>;
};

export async function generateMetadata({
  searchParams,
}: ForumPageProps): Promise<Metadata> {
  const settings = await fetchPublicSettings();

  return pageMetadata(t("forum.title"), t("forum.description"), {
    path: listCanonicalPath("/forum", await searchParams),
    noIndex: !settings.public_forum,
  });
}

function toRow(topic: ForumTopicSearchItem): ForumTopicRowData {
  return {
    href: `/forum/${topic.category.slug}/${topic.slug}`,
    title: topic.title,
    categoryName: topic.category.name,
    authorName: topic.author_name,
    repliesCount: topic.replies_count,
    lastActivityAt: topic.last_post_at ?? topic.published_at,
  };
}

/** „Без одговор“ rows: asked when, and an „Одговори“ button. */
function toUnansweredRow(topic: ForumTopicSearchItem): ForumTopicRowData {
  return {
    ...toRow(topic),
    lastActivityAt: topic.published_at ?? topic.last_post_at,
    answerHref: forumAnswerHref(topic.category.slug, topic.slug),
  };
}

const UNANSWERED_PER_PAGE = 15;

export default async function ForumPage({ searchParams }: ForumPageProps) {
  const settings = await fetchPublicSettings();

  // Every /forum API route answers 503 while the module is off.
  if (!isModuleOn(settings, "public_forum")) {
    return (
      <ComingSoonShell
        module="forum"
        title={t("forum.title")}
        description={t("comingSoon.forumBody")}
      />
    );
  }

  const query = await searchParams;
  const page = query.page ? Number(query.page) : 1;
  const searchQuery = query.q?.trim() ?? "";
  const categoryFilter = query.category?.trim() ?? "";
  const token = await getSessionToken();

  const [categories, recent] = await Promise.all([
    fetchForumCategories(),
    fetchForumRecentTopics(8),
  ]);

  const showSearch = searchQuery.length >= 2;
  const unansweredView = !showSearch && query.view === "unanswered";
  const topics = showSearch
    ? await fetchForumTopicSearch({
        q: searchQuery,
        category: categoryFilter || undefined,
        page: Number.isFinite(page) ? page : 1,
      })
    : null;

  // The „Без одговор“ list, or just its count for the chip (one row). The
  // forum works without it: a failure only drops the count.
  const unanswered = showSearch
    ? null
    : await fetchForumUnansweredTopics(
        unansweredView
          ? {
              page: Number.isFinite(page) ? page : 1,
              per_page: UNANSWERED_PER_PAGE,
            }
          : { per_page: 1 },
      ).catch(() => null);

  const totalTopics = categories.reduce(
    (sum, item) => sum + (item.topics_count ?? 0),
    0,
  );

  return (
    <div className={`${forumPageClass} gap-8 lg:gap-10`}>
      <ForumPageHead
        title={t("forum.title")}
        lead={t("forum.description")}
        meta={tCount("forum.topicsCount", totalTopics)}
        actions={<ForumHubActions isLoggedIn={Boolean(token)} />}
      />

      <ForumColumns
        main={
          <>
            <ForumSafetyNotice />
            <ForumHubSearch
              defaultQuery={searchQuery}
              defaultCategory={categoryFilter}
              categories={categories}
            />

            {showSearch && topics ? (
              <section
                aria-labelledby="forum-results-heading"
                className="flex flex-col gap-4"
              >
                <SectionHeader
                  id="forum-results-heading"
                  title={t("forum.searchResultsHeading")}
                  description={tCount("forum.topicsCount", topics.meta.total)}
                  action={
                    <TextLink href="/forum">
                      {t("common.clearFilters")}
                    </TextLink>
                  }
                />
                {topics.data.length === 0 ? (
                  <ForumEmpty
                    title={t("forum.noTopics")}
                    action={
                      <Button href="/forum" variant="secondary">
                        {t("common.clearFilters")}
                      </Button>
                    }
                  />
                ) : (
                  <ForumTopicList
                    label={t("forum.searchResultsHeading")}
                    topics={topics.data.map(toRow)}
                  />
                )}
                <Pagination
                  basePath="/forum"
                  currentPage={topics.meta.current_page}
                  lastPage={topics.meta.last_page}
                  total={topics.meta.total}
                  searchParams={{
                    q: searchQuery,
                    ...(categoryFilter ? { category: categoryFilter } : {}),
                  }}
                />
              </section>
            ) : unansweredView ? (
              <section
                aria-labelledby="forum-unanswered-heading"
                className="flex flex-col gap-4"
              >
                <ForumViewChips
                  current="unanswered"
                  unansweredCount={unanswered?.meta.total}
                />
                <SectionHeader
                  id="forum-unanswered-heading"
                  title={t("help.listTitle")}
                  description={t("help.listLead")}
                />
                {!unanswered || unanswered.data.length === 0 ? (
                  <ForumEmpty
                    title={t("help.emptyTitle")}
                    description={t("help.emptyBody")}
                    action={
                      <Button href="/forum" variant="secondary">
                        {t("help.emptyAction")}
                      </Button>
                    }
                  />
                ) : (
                  <ForumTopicList
                    label={t("help.listTitle")}
                    topics={unanswered.data.map(toUnansweredRow)}
                  />
                )}
                {unanswered ? (
                  <Pagination
                    basePath="/forum"
                    currentPage={unanswered.meta.current_page}
                    lastPage={unanswered.meta.last_page}
                    total={unanswered.meta.total}
                    searchParams={{ view: "unanswered" }}
                  />
                ) : null}
              </section>
            ) : (
              <section
                aria-labelledby="forum-recent-heading"
                className="flex flex-col gap-4"
              >
                <ForumViewChips
                  current="recent"
                  unansweredCount={unanswered?.meta.total}
                />
                <SectionHeader
                  id="forum-recent-heading"
                  title={t("forum.recentDiscussions")}
                />
                {recent.data.length === 0 ? (
                  <ForumEmpty title={t("forum.noTopics")} />
                ) : (
                  <ForumTopicList
                    label={t("forum.recentDiscussions")}
                    topics={recent.data.map(toRow)}
                  />
                )}
              </section>
            )}
          </>
        }
        aside={
          <aside className="flex flex-col gap-5">
            <section
              aria-labelledby="forum-categories-heading"
              className="flex flex-col gap-3"
            >
              <h2 id="forum-categories-heading" className="type-h3 text-ink">
                {t("forum.categories")}
              </h2>
              {categories.length === 0 ? (
                <ForumEmpty title={t("forum.noCategories")} />
              ) : (
                <ForumCategoryList categories={categories} />
              )}
            </section>
            <ForumRulesCard settings={settings} />
            <ForumCommunityCard />
          </aside>
        }
      />
    </div>
  );
}
