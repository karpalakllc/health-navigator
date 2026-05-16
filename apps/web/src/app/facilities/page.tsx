import type { Metadata } from "next";
import { EmptyState } from "@/components/directory/empty-state";
import { DirectoryCardGrid } from "@/components/directory/directory-card-grid";
import { FacilitiesFilterBar } from "@/components/directory/facilities-filter-bar";
import { FacilityCard } from "@/components/directory/facility-card";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { Pagination } from "@/components/directory/pagination";
import { fetchDepartments } from "@/lib/api/departments";
import { fetchFacilities } from "@/lib/api/facilities";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("facilities.title"),
  t("facilities.description"),
);

type FacilitiesPageProps = {
  searchParams: Promise<{
    type?: string;
    city?: string;
    q?: string;
    has_emergency?: string;
    department?: string;
    page?: string;
  }>;
};

function hasActiveFilters(params: {
  type?: string;
  city?: string;
  q?: string;
  has_emergency?: string;
  department?: string;
}): boolean {
  return Boolean(
    params.type || params.city || params.q || params.has_emergency || params.department,
  );
}

export default async function FacilitiesPage({
  searchParams,
}: FacilitiesPageProps) {
  const params = await searchParams;
  const page = params.page ? Number(params.page) : 1;
  const hasEmergency = params.has_emergency === "1";

  const [facilities, departments] = await Promise.all([
    fetchFacilities({
      type: params.type,
      city: params.city,
      q: params.q,
      has_emergency: hasEmergency ? true : undefined,
      department: params.department,
      page: Number.isFinite(page) ? page : 1,
    }),
    fetchDepartments().catch(() => []),
  ]);

  const filterParams = {
    type: params.type,
    city: params.city,
    q: params.q,
    has_emergency: hasEmergency ? "1" : undefined,
    department: params.department,
  };

  return (
    <PageShell>
      <PageHeader title={t("facilities.title")} description={t("facilities.description")} />

      <FacilitiesFilterBar
        values={filterParams}
        resultsTotal={facilities.meta.total}
        departments={departments}
      />

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
