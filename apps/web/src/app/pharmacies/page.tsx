import type { Metadata } from "next";
import {
  FilterField,
  FilterForm,
  SEARCH_QUERY_HINT,
  filterInputClassName,
} from "@/components/directory/filter-form";
import { EmptyState } from "@/components/directory/empty-state";
import { DirectoryCardGrid } from "@/components/directory/directory-card-grid";
import { PageHeader } from "@/components/directory/page-header";
import { PharmacyCard } from "@/components/directory/pharmacy-card";
import { PageShell } from "@/components/ui/page-shell";
import { Pagination } from "@/components/directory/pagination";
import { fetchPharmacies } from "@/lib/api/pharmacies";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("pharmacies.title"),
  t("pharmacies.description"),
);

type PharmaciesPageProps = {
  searchParams: Promise<{
    city?: string;
    q?: string;
    page?: string;
  }>;
};

function hasActiveFilters(params: { city?: string; q?: string }): boolean {
  return Boolean(params.city || params.q);
}

export default async function PharmaciesPage({
  searchParams,
}: PharmaciesPageProps) {
  const params = await searchParams;
  const page = params.page ? Number(params.page) : 1;

  const pharmacies = await fetchPharmacies({
    city: params.city,
    q: params.q,
    page: Number.isFinite(page) ? page : 1,
  });

  const filterParams = { city: params.city, q: params.q };

  return (
    <PageShell>
      <PageHeader title={t("pharmacies.title")} description={t("pharmacies.description")} />

      <FilterForm searchHint={SEARCH_QUERY_HINT}>
        <FilterField label={t("filters.city")}>
          <input
            name="city"
            defaultValue={params.city ?? ""}
            className={filterInputClassName}
          />
        </FilterField>
        <FilterField label={t("search.nameLabel")}>
          <input
            name="q"
            defaultValue={params.q ?? ""}
            className={filterInputClassName}
          />
        </FilterField>
      </FilterForm>

      {pharmacies.data.length === 0 ? (
        <EmptyState
          title={t("pharmacies.empty")}
          description={
            !hasActiveFilters(params) ? t("common.demoDataHint") : undefined
          }
          clearHref={hasActiveFilters(params) ? "/pharmacies" : undefined}
          clearLabel={hasActiveFilters(params) ? t("common.clearFilters") : undefined}
        />
      ) : (
        <DirectoryCardGrid>
          {pharmacies.data.map((pharmacy) => (
            <li key={pharmacy.slug}>
              <PharmacyCard pharmacy={pharmacy} />
            </li>
          ))}
        </DirectoryCardGrid>
      )}

      <Pagination
        basePath="/pharmacies"
        currentPage={pharmacies.meta.current_page}
        lastPage={pharmacies.meta.last_page}
        total={pharmacies.meta.total}
        searchParams={filterParams}
      />
    </PageShell>
  );
}
