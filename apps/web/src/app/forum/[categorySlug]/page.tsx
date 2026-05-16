import { notFound } from "next/navigation";
import { ForumCommunityRules } from "@/components/forum/forum-community-rules";
import { ForumSafetyNotice } from "@/components/forum/forum-safety-notice";
import { ForumTopicCard } from "@/components/forum/forum-topic-card";
import { TopicForm } from "@/components/forum/topic-form";
import {
  FilterField,
  FilterForm,
  SEARCH_QUERY_HINT,
  filterInputClassName,
} from "@/components/directory/filter-form";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { EmptyState } from "@/components/directory/empty-state";
import { PageHeader } from "@/components/directory/page-header";
import { FilterStatsRow } from "@/components/directory/filter-stats-row";
import { Pagination } from "@/components/directory/pagination";
import { LoginPrompt } from "@/components/ui/login-prompt";
import { PageShell } from "@/components/ui/page-shell";
import { getSessionToken } from "@/lib/auth/session";
import { fetchForumCategories, fetchForumTopics } from "@/lib/api/forum";
import { t, tFormat } from "@/i18n/t";

type CategoryTopicsPageProps = {
  params: Promise<{ categorySlug: string }>;
  searchParams: Promise<{ q?: string; page?: string }>;
};

export default async function CategoryTopicsPage({
  params,
  searchParams,
}: CategoryTopicsPageProps) {
  const { categorySlug } = await params;
  const query = await searchParams;
  const page = query.page ? Number(query.page) : 1;
  const token = await getSessionToken();

  let categories;
  let topics;

  try {
    categories = await fetchForumCategories();
    topics = await fetchForumTopics(categorySlug, {
      q: query.q,
      page: Number.isFinite(page) ? page : 1,
    });
  } catch {
    notFound();
  }

  const category = categories.find((item) => item.slug === categorySlug);

  if (!category) {
    notFound();
  }

  return (
    <PageShell>
      <Breadcrumbs
        items={[
          { label: t("common.home"), href: "/" },
          { label: t("forum.title"), href: "/forum" },
          { label: category.name },
        ]}
      />
      <PageHeader title={category.name} description={category.description ?? undefined} />
      <ForumSafetyNotice compact />
      <ForumCommunityRules compact />

      {token ? (
        <TopicForm categorySlug={categorySlug} />
      ) : (
        <LoginPrompt suffix={t("forum.loginToTopic")} />
      )}

      <FilterForm searchHint={SEARCH_QUERY_HINT}>
        <FilterField label={t("forum.searchTopics")}>
          <input
            name="q"
            defaultValue={query.q ?? ""}
            className={filterInputClassName}
          />
        </FilterField>
      </FilterForm>

      <FilterStatsRow
        label={tFormat("forum.topicsCount", { count: String(topics.meta.total) })}
        clearHref={query.q ? `/forum/${categorySlug}` : undefined}
      />

      {topics.data.length === 0 ? (
        <EmptyState
          title={t("forum.noTopics")}
          clearHref={query.q ? `/forum/${categorySlug}` : undefined}
          clearLabel={query.q ? t("common.clearFilters") : undefined}
        />
      ) : (
        <ul className="grid gap-3">
          {topics.data.map((topic) => (
            <li key={topic.slug}>
              <ForumTopicCard topic={topic} categorySlug={categorySlug} />
            </li>
          ))}
        </ul>
      )}

      <Pagination
        basePath={`/forum/${categorySlug}`}
        currentPage={topics.meta.current_page}
        lastPage={topics.meta.last_page}
        total={topics.meta.total}
        searchParams={{ q: query.q }}
      />
    </PageShell>
  );
}
