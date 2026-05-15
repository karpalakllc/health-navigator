import {
  FilterField,
  FilterForm,
  filterInputClassName,
  SEARCH_QUERY_HINT,
} from "@/components/directory/filter-form";
import { PageHeader } from "@/components/directory/page-header";
import { HubLinkCard } from "@/components/ui/hub-link-card";
import { SEARCH_DIRECTORY_SECTIONS } from "@/components/layout/search-directory-sections";
import { PageShell } from "@/components/ui/page-shell";
import { directorySearchHref } from "@/lib/search";
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

  return (
    <PageShell>
      <PageHeader title={t("search.title")} description={t("search.description")} />

      <FilterForm searchHint={SEARCH_QUERY_HINT}>
        <FilterField label={t("search.nameLabel")}>
          <input
            name="q"
            defaultValue={params.q ?? ""}
            className={filterInputClassName}
          />
        </FilterField>
        <FilterField label={t("search.cityLabel")}>
          <input
            name="city"
            defaultValue={params.city ?? ""}
            className={filterInputClassName}
          />
        </FilterField>
      </FilterForm>

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
  );
}
