import { notFound } from "next/navigation";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { Pagination } from "@/components/directory/pagination";
import {
  ForumCategoryToolbar,
  newTopicHref,
  type ForumCategorySort,
} from "@/components/forum/forum-category-toolbar";
import {
  ForumColumns,
  ForumEmpty,
  ForumPageHead,
  forumPageClass,
} from "@/components/forum/forum-layout";
import { ForumRulesCard } from "@/components/forum/forum-rules-band";
import {
  ForumTopicList,
  type ForumTopicRowData,
} from "@/components/forum/forum-topic-row";
import { ForumTopicSearch } from "@/components/forum/forum-topic-search";
import { BackLink } from "@/components/ui/back-link";
import { Button, TextLink } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { getSessionToken } from "@/lib/auth/session";
import {
  fetchForumCategories,
  fetchForumTopics,
  fetchForumUnansweredTopics,
  type ForumCategory,
} from "@/lib/api/forum";
import { fetchPublicSettings } from "@/lib/api/settings";
import { isModuleOn } from "@/lib/api/public-settings";
import { forumAnswerHref } from "@/lib/forum/answer-link";
import { listCanonicalPath, pageMetadata } from "@/lib/metadata";
import type { Metadata } from "next";
import { t, tCount } from "@/i18n/t";

type CategoryTopicsPageProps = {
  params: Promise<{ categorySlug: string }>;
  searchParams: Promise<{ q?: string; sort?: string; page?: string }>;
};

export async function generateMetadata({
  params,
  searchParams,
}: CategoryTopicsPageProps): Promise<Metadata> {
  const { categorySlug } = await params;
  // Page 2+ is its own document; search and sort are views of the list.
  const canonical = listCanonicalPath(
    `/forum/${categorySlug}`,
    await searchParams,
  );
  const settings = await fetchPublicSettings();

  if (!settings.public_forum) {
    return pageMetadata(t("forum.title"), undefined, { noIndex: true });
  }

  try {
    const categories = await fetchForumCategories();
    const category = categories.find((item) => item.slug === categorySlug);

    if (category) {
      return pageMetadata(category.name, category.description ?? undefined, {
        path: canonical,
      });
    }
  } catch {
    // Fall through to the generic forum title.
  }

  return pageMetadata(t("forum.title"), undefined, { path: canonical });
}

export default async function CategoryTopicsPage({
  params,
  searchParams,
}: CategoryTopicsPageProps) {
  const settings = await fetchPublicSettings();

  // Detail pages of a switched-off module do not exist; /forum explains why.
  if (!isModuleOn(settings, "public_forum")) {
    notFound();
  }

  const { categorySlug } = await params;
  const query = await searchParams;
  const page = query.page ? Number(query.page) : 1;
  const sort: ForumCategorySort =
    query.sort === "active" || query.sort === "unanswered"
      ? query.sort
      : "latest";
  const listPage = Number.isFinite(page) ? page : 1;
  const token = await getSessionToken();

  let categories: ForumCategory[];
  let topics: {
    rows: ForumTopicRowData[];
    meta: { current_page: number; last_page: number; total: number };
  };
  try {
    if (sort === "unanswered") {
      // Questions nobody has answered yet, newest first (W8-A).
      const [all, unanswered] = await Promise.all([
        fetchForumCategories(),
        fetchForumUnansweredTopics({
          category: categorySlug,
          q: query.q,
          page: listPage,
          per_page: 15,
        }),
      ]);
      categories = all;
      topics = {
        rows: unanswered.data.map((topic) => ({
          href: `/forum/${categorySlug}/${topic.slug}`,
          title: topic.title,
          authorName: topic.author_name,
          repliesCount: topic.replies_count,
          lastActivityAt: topic.published_at ?? topic.last_post_at,
          answerHref: forumAnswerHref(categorySlug, topic.slug),
        })),
        meta: unanswered.meta,
      };
    } else {
      const [all, list] = await Promise.all([
        fetchForumCategories(),
        fetchForumTopics(categorySlug, {
          q: query.q,
          sort,
          page: listPage,
        }),
      ]);
      categories = all;
      topics = {
        rows: list.data.map((topic) => ({
          href: `/forum/${categorySlug}/${topic.slug}`,
          title: topic.title,
          authorName: topic.author_name,
          repliesCount: topic.replies_count,
          lastActivityAt: topic.last_post_at ?? topic.published_at,
          isPinned: topic.is_pinned,
          isLocked: topic.is_locked,
        })),
        meta: list.meta,
      };
    }
  } catch {
    notFound();
  }

  const category = categories.find((item) => item.slug === categorySlug);

  if (!category) {
    notFound();
  }

  const searching = Boolean(query.q?.trim());

  return (
    <div className={`${forumPageClass} gap-6 lg:gap-8`}>
      <div className="lg:hidden">
        <BackLink href="/forum" label={t("forum.title")} />
      </div>
      <div className="-mb-6 hidden lg:block">
        <Breadcrumbs
          items={[
            { label: t("common.home"), href: "/" },
            { label: t("forum.title"), href: "/forum" },
            { label: category.name },
          ]}
        />
      </div>

      <ForumPageHead
        title={category.name}
        lead={category.description ?? undefined}
        meta={
          // The „Без одговор“ total is not the category's size.
          sort === "unanswered"
            ? category.topics_count === undefined
              ? undefined
              : tCount("forum.topicsCount", category.topics_count)
            : tCount("forum.topicsCount", topics.meta.total)
        }
        actions={
          <Button
            href={newTopicHref(categorySlug, Boolean(token))}
            size="lg"
            leadingIcon="message-circle"
            className="w-full sm:w-auto"
          >
            {t("forum.newTopic")}
          </Button>
        }
      />

      <ForumColumns
        main={
          <>
            <Card padding="md" className="flex flex-col gap-4">
              <ForumTopicSearch
                defaultQuery={query.q}
                action={`/forum/${categorySlug}`}
              />
              <div className="flex flex-wrap items-center justify-between gap-3">
                <ForumCategoryToolbar
                  categorySlug={categorySlug}
                  currentSort={sort}
                  searchQuery={query.q}
                />
                {searching ? (
                  <TextLink href={`/forum/${categorySlug}`}>
                    {t("common.clearFilters")}
                  </TextLink>
                ) : null}
              </div>
            </Card>

            {topics.rows.length === 0 ? (
              <ForumEmpty
                title={
                  sort === "unanswered" && !searching
                    ? t("help.emptyTitle")
                    : t("forum.noTopics")
                }
                description={
                  sort === "unanswered" && !searching
                    ? t("help.emptyBody")
                    : undefined
                }
                action={
                  searching ? (
                    <Button href={`/forum/${categorySlug}`} variant="secondary">
                      {t("common.clearFilters")}
                    </Button>
                  ) : undefined
                }
              />
            ) : (
              <ForumTopicList
                headingLevel={2}
                label={
                  sort === "unanswered"
                    ? `${category.name}: ${t("help.listTitle")}`
                    : category.name
                }
                topics={topics.rows}
              />
            )}

            <Pagination
              basePath={`/forum/${categorySlug}`}
              currentPage={topics.meta.current_page}
              lastPage={topics.meta.last_page}
              total={topics.meta.total}
              searchParams={{
                q: query.q,
                ...(sort !== "latest" ? { sort } : {}),
              }}
            />
          </>
        }
        aside={
          <aside className="flex flex-col gap-5 lg:sticky lg:top-[calc(var(--header-h)+1.5rem)]">
            <ForumRulesCard settings={settings} />
          </aside>
        }
      />
    </div>
  );
}
