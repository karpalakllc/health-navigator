import {
  FilterField,
  FilterForm,
  SEARCH_QUERY_HINT,
  filterInputClassName,
} from "@/components/directory/filter-form";
import { PageHeader } from "@/components/directory/page-header";
import { HubLinkCard } from "@/components/ui/hub-link-card";
import { SEARCH_DIRECTORY_SECTIONS } from "@/components/layout/search-directory-sections";
import { AdvancedSearchTrigger } from "@/components/search/advanced-search-trigger";
import { UnifiedSearchResults } from "@/components/search/unified-search-results";
import { PageShell } from "@/components/ui/page-shell";
import { directorySearchHref, normalizeSearchQuery } from "@/lib/search";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("search.title"),
  t("search.description"),
);

type SearchPageProps = {
  searchParams: Promise<{
    q?: string;
    city?: string;
  }>;
};

const sections = SEARCH_DIRECTORY_SECTIONS;

export default async function SearchPage({ searchParams }: SearchPageProps) {
  const params = await searchParams;
  const qRaw = params.q ?? "";
  const qNormalized = normalizeSearchQuery(qRaw);
  const cityTrim = params.city?.trim();
  const city = cityTrim || undefined;

  if (qNormalized) {
    return (
      <PageShell>
        <PageHeader
          title={t("search.unifiedTitle")}
          description={`„${qRaw.trim()}“${cityTrim ? ` · ${cityTrim}` : ""}`}
        />
        <div className="mb-6 flex flex-wrap items-center gap-3">
          <AdvancedSearchTrigger />
        </div>
        <UnifiedSearchResults q={qNormalized} city={city} />
      </PageShell>
    );
  }

  return (
    <PageShell>
      <PageHeader title={t("search.title")} description={t("search.hubIntro")} />

      <div className="mb-4 flex flex-wrap gap-3">
        <AdvancedSearchTrigger />
      </div>

      <FilterForm
        searchHint={SEARCH_QUERY_HINT}
        action="/search"
        method="get"
        fieldsClassName="sm:grid-cols-2"
      >
        <FilterField label={t("search.nameLabel")}>
          <input
            name="q"
            defaultValue={params.q ?? ""}
            className={filterInputClassName}
            autoComplete="off"
          />
        </FilterField>
        <FilterField label={t("search.cityLabel")}>
          <input
            name="city"
            defaultValue={params.city ?? ""}
            className={filterInputClassName}
            autoComplete="off"
          />
        </FilterField>
      </FilterForm>

      <ul className="mt-8 grid gap-4 sm:grid-cols-2">
        {sections.map((section) => (
          <li key={section.basePath}>
            <HubLinkCard
              href={directorySearchHref(section.basePath, params.q, params.city)}
              title={t(section.titleKey)}
              description={`${t("search.viewMatching")} ${t(section.titleKey).toLowerCase()} ${t("search.matching")}`}
            />
          </li>
        ))}
      </ul>
    </PageShell>
  );
}
