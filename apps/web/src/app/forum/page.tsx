import { ForumCategoryCard } from "@/components/forum/forum-category-card";
import { ForumCommunityRules } from "@/components/forum/forum-community-rules";
import { ForumSafetyNotice } from "@/components/forum/forum-safety-notice";
import { ForumSearchTopicCard } from "@/components/forum/forum-search-topic-card";
import { EmptyState } from "@/components/directory/empty-state";
import {
  FilterField,
  FilterForm,
  SEARCH_QUERY_HINT,
  filterInputClassName,
} from "@/components/directory/filter-form";
import { FilterStatsRow } from "@/components/directory/filter-stats-row";
import { Pagination } from "@/components/directory/pagination";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { pageMetadata } from "@/lib/metadata";
import { fetchForumCategories, fetchForumTopicSearch } from "@/lib/api/forum";
import { t, tFormat } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("forum.title"),
  t("forum.description"),
);

type ForumPageProps = {
  searchParams: Promise<{ q?: string; page?: string }>;
};

export default async function ForumPage({ searchParams }: ForumPageProps) {
  const query = await searchParams;
  const page = query.page ? Number(query.page) : 1;
  const searchQuery = query.q?.trim() ?? "";
  const categories = await fetchForumCategories();
  const showSearch = searchQuery.length >= 2;
  const topics = showSearch
    ? await fetchForumTopicSearch({
        q: searchQuery,
        page: Number.isFinite(page) ? page : 1,
      })
    : null;

  return (
    <PageShell>
      <PageHeader title={t("forum.title")} description={t("forum.description")} />
      <ForumSafetyNotice />
      <ForumCommunityRules />

      <FilterForm searchHint={SEARCH_QUERY_HINT}>
        <FilterField label={t("forum.searchTopics")}>
          <input name="q" defaultValue={searchQuery} className={filterInputClassName} />
        </FilterField>
      </FilterForm>

      {showSearch && topics ? (
        <div className="space-y-6">
          <FilterStatsRow
            label={tFormat("forum.topicsCount", { count: String(topics.meta.total) })}
            clearHref={searchQuery ? "/forum" : undefined}
          />

          {topics.data.length === 0 ? (
            <EmptyState
              title={t("forum.noTopics")}
              clearHref="/forum"
              clearLabel={t("common.clearFilters")}
            />
          ) : (
            <ul className="grid gap-3">
              {topics.data.map((topic) => (
                <li key={`${topic.category.slug}-${topic.slug}`}>
                  <ForumSearchTopicCard topic={topic} />
                </li>
              ))}
            </ul>
          )}

          <Pagination
            basePath="/forum"
            currentPage={topics.meta.current_page}
            lastPage={topics.meta.last_page}
            total={topics.meta.total}
            searchParams={{ q: searchQuery }}
          />
        </div>
      ) : null}

      {categories.length === 0 ? (
        <EmptyState title={t("forum.noCategories")} />
      ) : (
        <div className="mt-8 space-y-4">
          <p className="text-sm text-muted-foreground">{t("forum.browseCategories")}</p>
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {categories.map((category) => (
              <li key={category.slug}>
                <ForumCategoryCard category={category} />
              </li>
            ))}
          </ul>
        </div>
      )}
    </PageShell>
  );
}
