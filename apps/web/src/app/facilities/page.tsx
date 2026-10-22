import type { Metadata } from "next";
import { EmptyState } from "@/components/directory/empty-state";
import { FacilitiesDirectory } from "@/components/directory/facilities-directory";
import { FacilityCard } from "@/components/directory/facility-card";
import { Pagination } from "@/components/directory/pagination";
import { ResultsGrid } from "@/components/directory/results-grid";
import {
  FuzzySearchNotice,
  suggestionListHref,
} from "@/components/search/fuzzy-search-notice";
import { fetchDepartments } from "@/lib/api/departments";
import { fetchFacilities } from "@/lib/api/facilities";
import { listCanonicalPath, pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import { emptyStateHint } from "@/lib/empty-state-hint";
import { parseListPage } from "@/lib/api/directory-cache-policy";

type FacilitiesPageProps = {
  searchParams: Promise<{
    type?: string;
    city?: string;
    q?: string;
    has_emergency?: string;
    department?: string;
    verified?: string;
    page?: string;
  }>;
};

export async function generateMetadata({
  searchParams,
}: FacilitiesPageProps): Promise<Metadata> {
  return pageMetadata(t("facilities.title"), t("facilities.description"), {
    path: listCanonicalPath("/facilities", await searchParams),
  });
}

export default async function FacilitiesPage({
  searchParams,
}: FacilitiesPageProps) {
  const params = await searchParams;
  const page = parseListPage(params.page);
  const hasEmergency = params.has_emergency === "1";
  const onlyVerified = params.verified === "1";

  const [facilities, departments] = await Promise.all([
    fetchFacilities({
      type: params.type,
      city: params.city,
      q: params.q,
      has_emergency: hasEmergency ? true : undefined,
      department: params.department,
      verified: onlyVerified ? true : undefined,
      page,
    }),
    fetchDepartments().catch(() => []),
  ]);

  const applied = {
    q: params.q ?? "",
    type: params.type ?? "",
    department: params.department ?? "",
    city: params.city ?? "",
    has_emergency: hasEmergency ? "1" : "",
    verified: onlyVerified ? "1" : "",
  };
  const hasFilters = Object.values(applied).some(Boolean);

  return (
    <FacilitiesDirectory
      departments={departments}
      applied={applied}
      total={facilities.meta.total}
    >
      {facilities.data.length === 0 ? (
        <EmptyState
          title={t("facilities.empty")}
          description={emptyStateHint(hasFilters, "facilities")}
          clearHref={hasFilters ? "/facilities" : undefined}
          clearLabel={hasFilters ? t("common.clearFilters") : undefined}
        />
      ) : (
        <>
          {facilities.meta.fuzzy && applied.q ? (
            <FuzzySearchNotice
              q={applied.q}
              suggestion={facilities.meta.suggestion}
              suggestionHref={
                facilities.meta.suggestion
                  ? suggestionListHref(
                      "/facilities",
                      applied,
                      facilities.meta.suggestion,
                    )
                  : undefined
              }
            />
          ) : null}
          <ResultsGrid>
            {facilities.data.map((facility) => (
              <li key={facility.slug}>
                <FacilityCard facility={facility} />
              </li>
            ))}
          </ResultsGrid>
        </>
      )}

      {facilities.data.length > 0 ? (
        <Pagination
          basePath="/facilities"
          currentPage={facilities.meta.current_page}
          lastPage={facilities.meta.last_page}
          total={facilities.meta.total}
          searchParams={applied}
        />
      ) : null}
    </FacilitiesDirectory>
  );
}
