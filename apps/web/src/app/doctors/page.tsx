import type { Metadata } from "next";
import {
  FilterField,
  FilterForm,
  SEARCH_QUERY_HINT,
  filterInputClassName,
} from "@/components/directory/filter-form";
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
    page?: string;
  }>;
};

export default async function DoctorsPage({ searchParams }: DoctorsPageProps) {
  const params = await searchParams;
  const page = params.page ? Number(params.page) : 1;

  const [specialties, doctors] = await Promise.all([
    fetchSpecialties(),
    fetchDoctors({
      specialty: params.specialty,
      city: params.city,
      q: params.q,
      page: Number.isFinite(page) ? page : 1,
    }),
  ]);

  const filterParams = {
    specialty: params.specialty,
    city: params.city,
    q: params.q,
  };

  return (
    <PageShell>
      <PageHeader title={t("doctors.title")} description={t("doctors.description")} />

      <FilterForm searchHint={SEARCH_QUERY_HINT}>
        <FilterField label={t("filters.specialty")}>
          <select
            name="specialty"
            defaultValue={params.specialty ?? ""}
            className={filterInputClassName}
          >
            <option value="">{t("doctors.allSpecialties")}</option>
            {specialties.map((specialty) => (
              <option key={specialty.slug} value={specialty.slug}>
                {specialty.name}
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

      {doctors.data.length === 0 ? (
        <EmptyState
          title={t("doctors.empty")}
          description={
            !params.specialty && !params.city && !params.q
              ? t("common.demoDataHint")
              : undefined
          }
          clearHref={
            params.specialty || params.city || params.q ? "/doctors" : undefined
          }
          clearLabel={
            params.specialty || params.city || params.q
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
