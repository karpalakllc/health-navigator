import type { Metadata } from "next";
import { EmptyState } from "@/components/directory/empty-state";
import { Pagination } from "@/components/directory/pagination";
import { PharmaciesDirectory } from "@/components/directory/pharmacies-directory";
import { PharmacyCard } from "@/components/directory/pharmacy-card";
import { ResultsGrid } from "@/components/directory/results-grid";
import { ComingSoonShell } from "@/components/layout/coming-soon-shell";
import { fetchPharmacies } from "@/lib/api/pharmacies";
import { fetchPublicSettings } from "@/lib/api/settings";
import { isModuleOn } from "@/lib/api/public-settings";
import { listCanonicalPath, pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import { parseListPage } from "@/lib/api/directory-cache-policy";

type PharmaciesPageProps = {
  searchParams: Promise<{
    city?: string;
    q?: string;
    page?: string;
  }>;
};

export async function generateMetadata({
  searchParams,
}: PharmaciesPageProps): Promise<Metadata> {
  const settings = await fetchPublicSettings();

  return pageMetadata(t("pharmacies.title"), t("pharmacies.description"), {
    path: listCanonicalPath("/pharmacies", await searchParams),
    noIndex: !settings.public_pharmacies,
  });
}

export default async function PharmaciesPage({
  searchParams,
}: PharmaciesPageProps) {
  const settings = await fetchPublicSettings();

  if (!isModuleOn(settings, "public_pharmacies")) {
    return (
      <ComingSoonShell
        module="pharmacies"
        title={t("pharmacies.title")}
        description={t("pharmacies.description")}
      />
    );
  }

  const params = await searchParams;
  const page = parseListPage(params.page);

  const pharmacies = await fetchPharmacies({
    city: params.city,
    q: params.q,
    page,
  });

  const applied = { q: params.q ?? "", city: params.city ?? "" };
  const hasFilters = Boolean(applied.q || applied.city);

  return (
    <PharmaciesDirectory applied={applied} total={pharmacies.meta.total}>
      {pharmacies.data.length === 0 ? (
        <EmptyState
          title={t("pharmacies.empty")}
          description={!hasFilters ? t("common.demoDataHint") : undefined}
          clearHref={hasFilters ? "/pharmacies" : undefined}
          clearLabel={hasFilters ? t("common.clearFilters") : undefined}
        />
      ) : (
        <ResultsGrid>
          {pharmacies.data.map((pharmacy) => (
            <li key={pharmacy.slug}>
              <PharmacyCard pharmacy={pharmacy} />
            </li>
          ))}
        </ResultsGrid>
      )}

      {pharmacies.data.length > 0 ? (
        <Pagination
          basePath="/pharmacies"
          currentPage={pharmacies.meta.current_page}
          lastPage={pharmacies.meta.last_page}
          total={pharmacies.meta.total}
          searchParams={applied}
        />
      ) : null}
    </PharmaciesDirectory>
  );
}
