import { DirectoryHero } from "@/components/design/directory-hero";
import { TrustRibbon } from "@/components/design/trust-ribbon";
import {
  FilterField,
  FilterForm,
  SEARCH_QUERY_HINT,
  filterInputClassName,
} from "@/components/directory/filter-form";
import { HubLinkCard } from "@/components/ui/hub-link-card";
import { SEARCH_DIRECTORY_SECTIONS } from "@/components/layout/search-directory-sections";
import { AdvancedSearchTrigger } from "@/components/search/advanced-search-trigger";
import { UnifiedSearchResults } from "@/components/search/unified-search-results";
import { PageShell } from "@/components/ui/page-shell";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
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
      <>
        <PageHeroBleed>
          <DirectoryHero
            badge={t("search.directoryBadge")}
            title={t("search.unifiedTitle")}
            description={`„${qRaw.trim()}“${cityTrim ? ` · ${cityTrim}` : ""}`}
          />
        </PageHeroBleed>

      <PageShell className="gap-8 pb-16">
          <div className="flex flex-wrap items-center gap-3">
            <AdvancedSearchTrigger />
          </div>
          <UnifiedSearchResults q={qNormalized} city={city} />
        </PageShell>
      </>
    );
  }

  return (
    <>
      <PageHeroBleed>
        <DirectoryHero
          badge={t("search.directoryBadge")}
          title={t("search.title")}
          description={t("search.hubIntro")}
          filters={
            <div className="filters-card filters-card-nested p-4 sm:p-5">
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
            </div>
          }
        />
        <TrustRibbon
          items={[
            { text: t("search.trustUnified"), icon: <SearchIcon />, tone: "teal" },
            { text: t("search.trustFilters"), icon: <GridIcon />, tone: "teal" },
            { text: t("search.trustInformational"), icon: <InfoIcon />, tone: "red" },
          ]}
        />
      </PageHeroBleed>

      <PageShell className="gap-8 pb-16">
        <div className="flex flex-wrap gap-3">
          <AdvancedSearchTrigger />
        </div>

        <ul className="grid gap-4 sm:grid-cols-2">
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
    </>
  );
}

function SearchIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <circle cx="11" cy="11" r="7" />
      <path d="M20 20l-3-3" strokeLinecap="round" />
    </svg>
  );
}

function GridIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" />
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
