import type { Metadata } from "next";
import {
  FilterField,
  FilterForm,
  SEARCH_QUERY_HINT,
  filterInputClassName,
} from "@/components/directory/filter-form";
import { EmptyState } from "@/components/directory/empty-state";
import { DirectoryCardGrid } from "@/components/directory/directory-card-grid";
import { FacilityCard } from "@/components/directory/facility-card";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { Pagination } from "@/components/directory/pagination";
import { fetchFacilities } from "@/lib/api/facilities";
import type { FacilityType } from "@/lib/api/types";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("facilities.title"),
  t("facilities.description"),
);

const FACILITY_TYPES: {
  value: FacilityType;
  labelKey: "facilities.typeClinic" | "facilities.typeHospital" | "facilities.typeLaboratory";
}[] = [
  { value: "clinic", labelKey: "facilities.typeClinic" },
  { value: "hospital", labelKey: "facilities.typeHospital" },
  { value: "laboratory", labelKey: "facilities.typeLaboratory" },
];

type FacilitiesPageProps = {
  searchParams: Promise<{
    type?: string;
    city?: string;
    q?: string;
    page?: string;
  }>;
};

function hasActiveFilters(params: {
  type?: string;
  city?: string;
  q?: string;
}): boolean {
  return Boolean(params.type || params.city || params.q);
}

export default async function FacilitiesPage({
  searchParams,
}: FacilitiesPageProps) {
  const params = await searchParams;
  const page = params.page ? Number(params.page) : 1;

  const facilities = await fetchFacilities({
    type: params.type,
    city: params.city,
    q: params.q,
    page: Number.isFinite(page) ? page : 1,
  });

  const filterParams = {
    type: params.type,
    city: params.city,
    q: params.q,
  };

  return (
    <PageShell>
      <PageHeader title={t("facilities.title")} description={t("facilities.description")} />

      <FilterForm searchHint={SEARCH_QUERY_HINT}>
        <FilterField label={t("filters.type")}>
          <select
            name="type"
            defaultValue={params.type ?? ""}
            className={filterInputClassName}
          >
            <option value="">{t("facilities.allTypes")}</option>
            {FACILITY_TYPES.map((type) => (
              <option key={type.value} value={type.value}>
                {t(type.labelKey)}
              </option>
            ))}
          </select>
        </FilterField>
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

      {facilities.data.length === 0 ? (
        <EmptyState
          title={t("facilities.empty")}
          description={
            !hasActiveFilters(params) ? t("common.demoDataHint") : undefined
          }
          clearHref={hasActiveFilters(params) ? "/facilities" : undefined}
          clearLabel={hasActiveFilters(params) ? t("common.clearFilters") : undefined}
        />
      ) : (
        <DirectoryCardGrid>
          {facilities.data.map((facility) => (
            <li key={facility.slug}>
              <FacilityCard facility={facility} />
            </li>
          ))}
        </DirectoryCardGrid>
      )}

      <Pagination
        basePath="/facilities"
        currentPage={facilities.meta.current_page}
        lastPage={facilities.meta.last_page}
        total={facilities.meta.total}
        searchParams={filterParams}
      />
    </PageShell>
  );
}
