import { DirectoryHero } from "@/components/design/directory-hero";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { SectionHeading } from "@/components/design/section-heading";
import { TrustRibbon } from "@/components/design/trust-ribbon";
import { EmptyState } from "@/components/directory/empty-state";
import { FilterStatsRow } from "@/components/directory/filter-stats-row";
import { Pagination } from "@/components/directory/pagination";
import { ForumCategoryCard } from "@/components/forum/forum-category-card";
import { ForumHubActions } from "@/components/forum/forum-hub-actions";
import { ForumHubSearch } from "@/components/forum/forum-hub-search";
import { ForumRulesBand } from "@/components/forum/forum-rules-band";
import { ForumSearchTopicCard } from "@/components/forum/forum-search-topic-card";
import { PageShell } from "@/components/ui/page-shell";
import { getSessionToken } from "@/lib/auth/session";
import {
  fetchForumCategories,
  fetchForumRecentTopics,
  fetchForumTopicSearch,
} from "@/lib/api/forum";
import { fetchPublicSettingsServer } from "@/lib/api/settings";
import { pageMetadata } from "@/lib/metadata";
import { t, tFormat } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("forum.title"),
  t("forum.description"),
);

type ForumPageProps = {
  searchParams: Promise<{ q?: string; category?: string; page?: string }>;
};

export default async function ForumPage({ searchParams }: ForumPageProps) {
  const query = await searchParams;
  const page = query.page ? Number(query.page) : 1;
  const searchQuery = query.q?.trim() ?? "";
  const categoryFilter = query.category?.trim() ?? "";
  const token = await getSessionToken();

  const [categories, settings, recent] = await Promise.all([
    fetchForumCategories(),
    fetchPublicSettingsServer(),
    fetchForumRecentTopics(6),
  ]);

  const showSearch = searchQuery.length >= 2;
  const topics = showSearch
    ? await fetchForumTopicSearch({
        q: searchQuery,
        category: categoryFilter || undefined,
        page: Number.isFinite(page) ? page : 1,
      })
    : null;

  const totalTopics = categories.reduce(
    (sum, item) => sum + (item.topics_count ?? 0),
    0,
  );

  return (
    <>
      <PageHeroBleed>
        <DirectoryHero
          badge={
            <>
              <ForumIcon />
              {t("forum.hubBadge")}
            </>
          }
          title={t("forum.title")}
          description={t("forum.description")}
          stat={
            <span className="inline-flex min-h-12 items-center gap-2.5 rounded-full border border-white/90 bg-white/[0.86] px-4 text-sm font-extrabold text-[#4f5b67] shadow-[0_14px_40px_rgb(16_30_36_/_0.07)]">
              {tFormat("forum.topicsCount", { count: String(totalTopics) })}
            </span>
          }
          filters={
            <div className="space-y-4">
              <ForumHubSearch
                defaultQuery={searchQuery}
                defaultCategory={categoryFilter}
                categories={categories}
              />
              <ForumHubActions isLoggedIn={Boolean(token)} />
            </div>
          }
        />
        <TrustRibbon
          items={[
            {
              text: t("home.trustModerated"),
              icon: <ShieldIcon />,
              tone: "teal",
            },
            {
              text: t("forum.rulesNoDiagnosis"),
              icon: <InfoIcon />,
              tone: "red",
            },
            {
              text: t("home.trustEmergency"),
              icon: <AlertIcon />,
              tone: "red",
            },
          ]}
        />
      </PageHeroBleed>

      <PageShell className="gap-10 pb-16">
        <ForumRulesBand settings={settings} />

        {showSearch && topics ? (
          <section className="space-y-6">
            <FilterStatsRow
              label={tFormat("forum.topicsCount", {
                count: String(topics.meta.total),
              })}
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
              searchParams={{
                q: searchQuery,
                ...(categoryFilter ? { category: categoryFilter } : {}),
              }}
            />
          </section>
        ) : (
          <>
            <section>
              <SectionHeading
                eyebrow={t("forum.categories")}
                eyebrowVariant="plain"
                title={t("forum.browseCategories")}
              />
              {categories.length === 0 ? (
                <EmptyState title={t("forum.noCategories")} />
              ) : (
                <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  {categories.map((category) => (
                    <li key={category.slug}>
                      <ForumCategoryCard category={category} />
                    </li>
                  ))}
                </ul>
              )}
            </section>

            {recent.data.length > 0 ? (
              <section>
                <SectionHeading
                  eyebrow={t("forum.hubBadge")}
                  eyebrowVariant="plain"
                  title={t("forum.recentDiscussions")}
                />
                <ul className="grid gap-3">
                  {recent.data.map((topic) => (
                    <li key={`${topic.category.slug}-${topic.slug}`}>
                      <ForumSearchTopicCard topic={topic} />
                    </li>
                  ))}
                </ul>
              </section>
            ) : null}
          </>
        )}
      </PageShell>
    </>
  );
}

function ForumIcon() {
  return (
    <svg
      className="h-4 w-4 text-primary"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <path
        d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4v8z"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function ShieldIcon() {
  return (
    <svg
      className="h-5 w-5"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <path
        d="M12 3l8 4v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function InfoIcon() {
  return (
    <svg
      className="h-5 w-5"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <circle cx="12" cy="12" r="9" />
      <path d="M12 10v6M12 7h.01" strokeLinecap="round" />
    </svg>
  );
}

function AlertIcon() {
  return (
    <svg
      className="h-5 w-5"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <path d="M12 9v4M12 17h.01" strokeLinecap="round" />
      <path d="M10.3 4.3h3.4L20 18H4L10.3 4.3z" strokeLinejoin="round" />
    </svg>
  );
}
