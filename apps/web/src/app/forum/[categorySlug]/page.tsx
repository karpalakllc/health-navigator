import { notFound } from "next/navigation";
import { DirectoryHero } from "@/components/design/directory-hero";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { TrustRibbon } from "@/components/design/trust-ribbon";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { EmptyState } from "@/components/directory/empty-state";
import { FilterStatsRow } from "@/components/directory/filter-stats-row";
import { Pagination } from "@/components/directory/pagination";
import { ForumCategoryToolbar } from "@/components/forum/forum-category-toolbar";
import { ForumTopicCard } from "@/components/forum/forum-topic-card";
import { ForumTopicSearch } from "@/components/forum/forum-topic-search";
import { PageShell } from "@/components/ui/page-shell";
import { getSessionToken } from "@/lib/auth/session";
import { fetchForumCategories, fetchForumTopics } from "@/lib/api/forum";
import { pageMetadata } from "@/lib/metadata";
import type { Metadata } from "next";
import { t, tFormat } from "@/i18n/t";

type CategoryTopicsPageProps = {
  params: Promise<{ categorySlug: string }>;
  searchParams: Promise<{ q?: string; sort?: string; page?: string }>;
};

export async function generateMetadata({
  params,
}: CategoryTopicsPageProps): Promise<Metadata> {
  const { categorySlug } = await params;

  try {
    const categories = await fetchForumCategories();
    const category = categories.find((item) => item.slug === categorySlug);

    if (category) {
      return pageMetadata(category.name, category.description ?? undefined, {
        path: `/forum/${categorySlug}`,
      });
    }
  } catch {
    // Fall through to the generic forum title.
  }

  return pageMetadata(t("forum.title"), undefined, { path: `/forum/${categorySlug}` });
}

export default async function CategoryTopicsPage({
  params,
  searchParams,
}: CategoryTopicsPageProps) {
  const { categorySlug } = await params;
  const query = await searchParams;
  const page = query.page ? Number(query.page) : 1;
  const sort = query.sort === "active" ? "active" : "latest";
  const token = await getSessionToken();

  let categories;
  let topics;
  try {
    [categories, topics] = await Promise.all([
      fetchForumCategories(),
      fetchForumTopics(categorySlug, {
        q: query.q,
        sort,
        page: Number.isFinite(page) ? page : 1,
      }),
    ]);
  } catch {
    notFound();
  }

  const category = categories.find((item) => item.slug === categorySlug);

  if (!category) {
    notFound();
  }

  return (
    <>
      <PageHeroBleed>
        <DirectoryHero
          badge={
            <>
              <ForumIcon />
              {category.name}
            </>
          }
          title={category.name}
          description={category.description ?? t("forum.description")}
          stat={
            <span className="inline-flex min-h-12 items-center gap-2.5 rounded-full border border-white/90 bg-white/[0.86] px-4 text-sm font-extrabold text-[#4f5b67] shadow-[0_14px_40px_rgb(16_30_36_/_0.07)]">
              {tFormat("forum.topicsCount", { count: String(topics.meta.total) })}
            </span>
          }
          filters={
            <div className="filters-card filters-card-nested space-y-4 p-4 sm:p-5">
              <ForumTopicSearch defaultQuery={query.q} action={`/forum/${categorySlug}`} />
              <ForumCategoryToolbar
                categorySlug={categorySlug}
                currentSort={sort}
                searchQuery={query.q}
                isLoggedIn={Boolean(token)}
              />
            </div>
          }
        />
        <TrustRibbon
          variant="compact"
          columns={3}
          items={[
            { text: t("forum.rulesModeration"), icon: <ShieldIcon />, tone: "teal" },
            { text: t("forum.rulesNoDiagnosis"), icon: <InfoIcon />, tone: "red" },
          ]}
        />
      </PageHeroBleed>

      <PageShell className="gap-8 pb-16">
        <Breadcrumbs
          items={[
            { label: t("common.home"), href: "/" },
            { label: t("forum.title"), href: "/forum" },
            { label: category.name },
          ]}
        />

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
          searchParams={{
            q: query.q,
            ...(sort !== "latest" ? { sort } : {}),
          }}
        />
      </PageShell>
    </>
  );
}

function ForumIcon() {
  return (
    <svg className="h-4 w-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4v8z" strokeLinejoin="round" />
    </svg>
  );
}

function ShieldIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 3l8 4v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z" strokeLinejoin="round" />
    </svg>
  );
}

function InfoIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 10v6M12 7h.01" strokeLinecap="round" />
    </svg>
  );
}
