import type { Metadata } from "next";
import { DirectoryHero } from "@/components/design/directory-hero";
import { TrustRibbon } from "@/components/design/trust-ribbon";
import { DoctorsFilterBar } from "@/components/directory/doctors-filter-bar";
import { DoctorsResultsSection } from "@/components/directory/doctors-results-section";
import { EmptyState } from "@/components/directory/empty-state";
import { PageShell } from "@/components/ui/page-shell";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { Pagination } from "@/components/directory/pagination";
import { fetchDoctors } from "@/lib/api/doctors";
import { fetchSpecialties } from "@/lib/api/specialties";
import { pageMetadata } from "@/lib/metadata";
import { t, tFormat } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("doctors.title"),
  t("doctors.description"),
);

type DoctorsPageProps = {
  searchParams: Promise<{
    specialty?: string;
    city?: string;
    q?: string;
    sort?: string;
    page?: string;
  }>;
};

export default async function DoctorsPage({ searchParams }: DoctorsPageProps) {
  const params = await searchParams;
  const page = params.page ? Number(params.page) : 1;
  const sort = params.sort === "rating" ? "rating" : "name";

  const [specialties, doctors] = await Promise.all([
    fetchSpecialties(),
    fetchDoctors({
      specialty: params.specialty,
      city: params.city,
      q: params.q,
      sort,
      page: Number.isFinite(page) ? page : 1,
    }),
  ]);

  const filterParams = {
    specialty: params.specialty,
    city: params.city,
    q: params.q,
    ...(sort === "rating" ? { sort: "rating" } : {}),
  };

  return (
    <>
      <PageHeroBleed>
        <DirectoryHero
          badge={
            <>
              <StethoscopeIcon />
              {t("doctors.directoryBadge")}
            </>
          }
          title={t("doctors.title")}
          description={t("doctors.description")}
          stat={
            <span className="inline-flex min-h-12 items-center gap-2.5 rounded-full border border-white/90 bg-white/[0.86] px-4 text-sm font-extrabold text-[#4f5b67] shadow-[0_14px_40px_rgb(16_30_36_/_0.07)]">
              <UsersIcon />
              {tFormat("doctors.resultsCount", {
                count: String(doctors.meta.total),
              })}
            </span>
          }
          filters={
            <DoctorsFilterBar
              specialties={specialties}
              values={{
                specialty: params.specialty,
                city: params.city,
                q: params.q,
                sort,
              }}
            />
          }
        />

        <TrustRibbon
          variant="compact"
          columns={3}
          items={[
            {
              text: t("doctors.trustRatingsInfo"),
              tone: "teal",
              icon: <InfoIcon />,
            },
            {
              text: t("doctors.trustModeratedInfo"),
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
        {doctors.data.length === 0 ? (
          <section className="space-y-6">
            <div>
              <h2 className="text-2xl font-black tracking-tight">
                {t("doctors.resultsTitle")}
              </h2>
              <p className="mt-1 text-muted-foreground">
                {tFormat("doctors.resultsCount", {
                  count: String(doctors.meta.total),
                })}
              </p>
            </div>
            <EmptyState
              title={t("doctors.empty")}
              description={
                !params.specialty && !params.city && !params.q
                  ? t("common.demoDataHint")
                  : undefined
              }
              clearHref={
                params.specialty || params.city || params.q || sort === "rating"
                  ? "/doctors"
                  : undefined
              }
              clearLabel={
                params.specialty || params.city || params.q || sort === "rating"
                  ? t("common.clearFilters")
                  : undefined
              }
            />
          </section>
        ) : (
          <DoctorsResultsSection
            doctors={doctors.data}
            total={doctors.meta.total}
          />
        )}

        {doctors.data.length > 0 && doctors.meta.last_page > 1 ? (
          <Pagination
            basePath="/doctors"
            currentPage={doctors.meta.current_page}
            lastPage={doctors.meta.last_page}
            total={doctors.meta.total}
            searchParams={filterParams}
          />
        ) : null}
      </PageShell>
    </>
  );
}

function StethoscopeIcon() {
  return (
    <svg
      className="h-4 w-4 text-accent"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <path
        d="M11 2v2M6 7a4 4 0 018 0M6 7v2a6 6 0 0012 0V7M6 7H4"
        strokeLinecap="round"
      />
    </svg>
  );
}

function UsersIcon() {
  return (
    <svg
      className="h-4 w-4"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <path
        d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 7a4 4 0 100-8 4 4 0 000 8zM22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"
        strokeLinecap="round"
      />
    </svg>
  );
}

function InfoIcon() {
  return (
    <svg
      className="h-4 w-4"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <circle cx="12" cy="12" r="9" />
      <path d="M12 10v6M12 7h.01" strokeLinecap="round" />
    </svg>
  );
}

function CheckIcon() {
  return (
    <svg
      className="h-4 w-4"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <path
        d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
        strokeLinecap="round"
      />
    </svg>
  );
}

function MapIcon() {
  return (
    <svg
      className="h-4 w-4"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <path
        d="M12 21s-7-4-7-10a7 7 0 1114 0c0 6-7 10-7 10z"
        strokeLinecap="round"
      />
      <circle cx="12" cy="11" r="2.5" />
    </svg>
  );
}
