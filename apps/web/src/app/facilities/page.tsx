import type { Metadata } from "next";
import { DirectoryHero } from "@/components/design/directory-hero";
import { TrustRibbon } from "@/components/design/trust-ribbon";
import { EmptyState } from "@/components/directory/empty-state";
import { FacilitiesFilterBar } from "@/components/directory/facilities-filter-bar";
import { FacilitiesResultsSection } from "@/components/directory/facilities-results-section";
import { PageShell } from "@/components/ui/page-shell";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { Pagination } from "@/components/directory/pagination";
import { fetchDepartments } from "@/lib/api/departments";
import { fetchFacilities } from "@/lib/api/facilities";
import { pageMetadata } from "@/lib/metadata";
import { t, tFormat } from "@/i18n/t";

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
    <>
      <PageHeroBleed>
      <DirectoryHero
        badge={
          <>
            <BuildingIcon />
            {t("facilities.directoryBadge")}
          </>
        }
        title={t("facilities.title")}
        description={t("facilities.description")}
        stat={
          <span className="inline-flex min-h-12 items-center gap-2.5 rounded-full border border-white/90 bg-white/[0.86] px-4 text-sm font-extrabold text-[#4f5b67] shadow-[0_14px_40px_rgb(16_30_36_/_0.07)]">
            <PinIcon />
            {tFormat("facilities.resultsCount", { count: String(facilities.meta.total) })}
          </span>
        }
        filters={
          <FacilitiesFilterBar values={filterParams} departments={departments} />
        }
      />

      <TrustRibbon
        variant="compact"
        columns={3}
        items={[
          {
            text: t("facilities.trustRatingsInfo"),
            tone: "teal",
            icon: <InfoIcon />,
          },
          {
            text: t("facilities.trustModeratedInfo"),
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
      </PageHeroBleed>

      <PageShell gap="loose" className="pb-16 pt-6">
      {facilities.data.length === 0 ? (
        <section className="space-y-6">
          <div>
            <h2 className="text-2xl font-black tracking-tight">{t("facilities.resultsTitle")}</h2>
            <p className="mt-1 text-muted-foreground">
              {tFormat("facilities.resultsCount", { count: String(facilities.meta.total) })}
            </p>
          </div>
          <EmptyState
            title={t("facilities.empty")}
            description={
              !hasActiveFilters(params) ? t("common.demoDataHint") : undefined
            }
            clearHref={hasActiveFilters(params) ? "/facilities" : undefined}
            clearLabel={hasActiveFilters(params) ? t("common.clearFilters") : undefined}
          />
        </section>
      ) : (
        <FacilitiesResultsSection facilities={facilities.data} total={facilities.meta.total} />
      )}

      {facilities.data.length > 0 && facilities.meta.last_page > 1 ? (
        <Pagination
          basePath="/facilities"
          currentPage={facilities.meta.current_page}
          lastPage={facilities.meta.last_page}
          total={facilities.meta.total}
          searchParams={filterParams}
        />
      ) : null}
      </PageShell>
    </>
  );
}

function BuildingIcon() {
  return (
    <svg className="h-4 w-4 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6" strokeLinecap="round" strokeLinejoin="round" />
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
