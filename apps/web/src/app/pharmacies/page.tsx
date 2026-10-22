import type { Metadata } from "next";
import { EmptyState } from "@/components/directory/empty-state";
import { Pagination } from "@/components/directory/pagination";
import { PharmaciesDirectory } from "@/components/directory/pharmacies-directory";
import { PharmacyCard } from "@/components/directory/pharmacy-card";
import { ResultsGrid } from "@/components/directory/results-grid";
import {
  FuzzySearchNotice,
  suggestionListHref,
} from "@/components/search/fuzzy-search-notice";
import { ComingSoonShell } from "@/components/layout/coming-soon-shell";
import { OnDutySourceNote } from "@/components/urgent-care/on-duty-tag";
import { fetchPharmacies } from "@/lib/api/pharmacies";
import { fetchPublicSettings } from "@/lib/api/settings";
import { isModuleOn } from "@/lib/api/public-settings";
import { listCanonicalPath, pageMetadata } from "@/lib/metadata";
import { t, tFormat } from "@/i18n/t";
import { emptyStateHint } from "@/lib/empty-state-hint";
import { parseListPage } from "@/lib/api/directory-cache-policy";

type PharmaciesPageProps = {
  searchParams: Promise<{
    city?: string;
    q?: string;
    verified?: string;
    on_duty?: string;
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
  const onlyVerified = params.verified === "1";
  const onlyOnDuty = params.on_duty === "1";

  const pharmacies = await fetchPharmacies({
    city: params.city,
    q: params.q,
    verified: onlyVerified ? "1" : undefined,
    on_duty: onlyOnDuty ? "1" : undefined,
    page,
  });

  const applied = {
    q: params.q ?? "",
    city: params.city ?? "",
    verified: onlyVerified ? "1" : "",
    on_duty: onlyOnDuty ? "1" : "",
  };
  const hasFilters = Boolean(
    applied.q || applied.city || applied.verified || applied.on_duty,
  );
  const city = applied.city.trim();
  // On-duty cards lead in a city (API order); ФЗОМ's caveat is said once.
  const showOnDutyNote =
    pharmacies.data.length > 0 &&
    (onlyOnDuty || pharmacies.data.some((pharmacy) => pharmacy.on_duty_today));

  return (
    <PharmaciesDirectory applied={applied} total={pharmacies.meta.total}>
      {pharmacies.data.length === 0 && onlyOnDuty ? (
        <EmptyState
          title={
            city
              ? tFormat("pharmacies.onDutyNoneInCity", { city })
              : t("pharmacies.onDutyNone")
          }
          description={t("urgentCare.pharmaciesSource")}
          clearHref={
            city
              ? `/pharmacies?${new URLSearchParams({ city })}`
              : "/pharmacies"
          }
          clearLabel={
            city
              ? tFormat("pharmacies.allInCity", { city })
              : t("common.clearFilters")
          }
        />
      ) : pharmacies.data.length === 0 ? (
        <EmptyState
          title={t("pharmacies.empty")}
          description={emptyStateHint(hasFilters, "pharmacies")}
          clearHref={hasFilters ? "/pharmacies" : undefined}
          clearLabel={hasFilters ? t("common.clearFilters") : undefined}
        />
      ) : (
        <>
          {pharmacies.meta.fuzzy && applied.q ? (
            <FuzzySearchNotice
              q={applied.q}
              suggestion={pharmacies.meta.suggestion}
              suggestionHref={
                pharmacies.meta.suggestion
                  ? suggestionListHref(
                      "/pharmacies",
                      applied,
                      pharmacies.meta.suggestion,
                    )
                  : undefined
              }
            />
          ) : null}
          {showOnDutyNote ? <OnDutySourceNote className="mb-4" /> : null}
          <ResultsGrid>
            {pharmacies.data.map((pharmacy) => (
              <li key={pharmacy.slug}>
                <PharmacyCard pharmacy={pharmacy} />
              </li>
            ))}
          </ResultsGrid>
        </>
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
