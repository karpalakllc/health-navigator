import type { Metadata } from "next";
import { DirectoryHero } from "@/components/design/directory-hero";
import { TrustRibbon } from "@/components/design/trust-ribbon";
import { EmptyState } from "@/components/directory/empty-state";
import { PharmaciesFilterBar } from "@/components/directory/pharmacies-filter-bar";
import { PharmaciesResultsSection } from "@/components/directory/pharmacies-results-section";
import { PageShell } from "@/components/ui/page-shell";
import { pageEdgeBleedClass } from "@/components/ui/layout";
import { Pagination } from "@/components/directory/pagination";
import { ComingSoonShell } from "@/components/layout/coming-soon-shell";
import { fetchPharmacies } from "@/lib/api/pharmacies";
import { fetchPublicSettings } from "@/lib/api/settings";
import { pageMetadata } from "@/lib/metadata";
import { t, tFormat } from "@/i18n/t";

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
  const settings = await fetchPublicSettings();

  if (!settings.public_pharmacies) {
    return (
      <ComingSoonShell
        title={t("pharmacies.title")}
        description={t("pharmacies.description")}
      />
    );
  }

  const params = await searchParams;
  const page = params.page ? Number(params.page) : 1;

  const pharmacies = await fetchPharmacies({
    city: params.city,
    q: params.q,
    page: Number.isFinite(page) ? page : 1,
  });

  const filterParams = { city: params.city, q: params.q };

  const resultsLabels = {
    resultsTitle: t("pharmacies.resultsTitle"),
    resultsCount: tFormat("pharmacies.resultsCount", { count: String(pharmacies.meta.total) }),
    viewModeAria: t("pharmacies.viewModeAria"),
    viewGrid: t("pharmacies.viewGrid"),
    viewList: t("pharmacies.viewList"),
  };

  return (
    <>
      <div className={`${pageEdgeBleedClass} flex flex-col gap-6 pb-2 pt-[18px]`}>
      <DirectoryHero
        badge={
          <>
            <PharmacyIcon />
            {t("pharmacies.directoryBadge")}
          </>
        }
        title={t("pharmacies.title")}
        description={t("pharmacies.description")}
        stat={
          <span className="inline-flex min-h-12 items-center gap-2.5 rounded-full border border-white/90 bg-white/[0.86] px-4 text-sm font-extrabold text-[#4f5b67] shadow-[0_14px_40px_rgb(16_30_36_/_0.07)]">
            <PinIcon />
            {tFormat("pharmacies.resultsCount", { count: String(pharmacies.meta.total) })}
          </span>
        }
        filters={<PharmaciesFilterBar values={filterParams} />}
      />

      <TrustRibbon
        variant="compact"
        columns={3}
        items={[
          {
            text: t("pharmacies.trustPricesInfo"),
            tone: "teal",
            icon: <InfoIcon />,
          },
          {
            text: t("pharmacies.trustModeratedInfo"),
            tone: "red",
            icon: <CheckIcon />,
          },
          {
            text: t("home.trustLocal"),
            tone: "teal",
            icon: <MapIcon />,
          },
        ]}
      />
      </div>

      <PageShell gap="loose" className="pb-16 pt-6">
      {pharmacies.data.length === 0 ? (
        <section className="space-y-6">
          <div>
            <h2 className="text-2xl font-black tracking-tight">{t("pharmacies.resultsTitle")}</h2>
            <p className="mt-1 text-muted-foreground">
              {tFormat("pharmacies.resultsCount", { count: String(pharmacies.meta.total) })}
            </p>
          </div>
          <EmptyState
            title={t("pharmacies.empty")}
            description={
              !hasActiveFilters(params) ? t("common.demoDataHint") : undefined
            }
            clearHref={hasActiveFilters(params) ? "/pharmacies" : undefined}
            clearLabel={hasActiveFilters(params) ? t("common.clearFilters") : undefined}
          />
        </section>
      ) : (
        <PharmaciesResultsSection pharmacies={pharmacies.data} total={pharmacies.meta.total} />
      )}

      {pharmacies.data.length > 0 && pharmacies.meta.last_page > 1 ? (
        <Pagination
          basePath="/pharmacies"
          currentPage={pharmacies.meta.current_page}
          lastPage={pharmacies.meta.last_page}
          total={pharmacies.meta.total}
          searchParams={filterParams}
        />
      ) : null}
      </PageShell>
    </>
  );
}

function PharmacyIcon() {
  return (
    <svg className="h-4 w-4 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 2v20M5 9h14" strokeLinecap="round" />
    </svg>
  );
}

function PinIcon() {
  return (
    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 21s-7-4-7-10a7 7 0 1114 0c0 6-7 10-7 10z" strokeLinecap="round" />
      <circle cx="12" cy="11" r="2.5" />
    </svg>
  );
}

function InfoIcon() {
  return (
    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 10v6M12 7h.01" strokeLinecap="round" />
    </svg>
  );
}

function CheckIcon() {
  return (
    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z" strokeLinecap="round" />
    </svg>
  );
}

function MapIcon() {
  return (
    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 21s-7-4-7-10a7 7 0 1114 0c0 6-7 10-7 10z" strokeLinecap="round" />
      <circle cx="12" cy="11" r="2.5" />
    </svg>
  );
}
