import type { Metadata } from "next";
import { DoctorsFilterBar } from "@/components/directory/doctors-filter-bar";
import { DirectoryCardGrid } from "@/components/directory/directory-card-grid";
import { DoctorCard } from "@/components/directory/doctor-card";
import { EmptyState } from "@/components/directory/empty-state";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { Pagination } from "@/components/directory/pagination";
import { fetchDoctors } from "@/lib/api/doctors";
import { fetchSpecialties } from "@/lib/api/specialties";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

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
    <PageShell>
      <PageHeader title={t("doctors.title")} description={t("doctors.description")} />

      <DoctorsFilterBar
        specialties={specialties}
        values={{
          specialty: params.specialty,
          city: params.city,
          q: params.q,
          sort,
        }}
        resultsTotal={doctors.meta.total}
      />

      {doctors.data.length === 0 ? (
        <EmptyState
          title={t("doctors.empty")}
          description={
            !params.specialty && !params.city && !params.q
              ? t("common.demoDataHint")
              : undefined
          }
          clearHref={
            params.specialty || params.city || params.q || sort === "rating" ? "/doctors" : undefined
          }
          clearLabel={
            params.specialty || params.city || params.q || sort === "rating"
              ? t("common.clearFilters")
              : undefined
          }
        />
      ) : (
        <DirectoryCardGrid>
          {doctors.data.map((doctor) => (
            <li key={doctor.slug}>
              <DoctorCard doctor={doctor} />
            </li>
          ))}
        </DirectoryCardGrid>
      )}

      <Pagination
        basePath="/doctors"
        currentPage={doctors.meta.current_page}
        lastPage={doctors.meta.last_page}
        total={doctors.meta.total}
        searchParams={filterParams}
      />
    </PageShell>
  );
}
