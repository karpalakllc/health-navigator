import type { Metadata } from "next";
import { DoctorCard } from "@/components/directory/doctor-card";
import { DoctorsDirectory } from "@/components/directory/doctors-directory";
import { EmptyState } from "@/components/directory/empty-state";
import { Pagination } from "@/components/directory/pagination";
import { ResultsGrid } from "@/components/directory/results-grid";
import { fetchDoctors } from "@/lib/api/doctors";
import { fetchSpecialties } from "@/lib/api/specialties";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import { parseListPage } from "@/lib/api/directory-cache-policy";

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
    min_reviews?: string;
    page?: string;
  }>;
};

export default async function DoctorsPage({ searchParams }: DoctorsPageProps) {
  const params = await searchParams;
  const page = parseListPage(params.page);
  const sort = params.sort === "rating" ? "rating" : "name";
  // „Има рецензии“: the only value the UI sends; anything else is ignored.
  const withReviews = params.min_reviews === "1";

  const [specialties, doctors] = await Promise.all([
    fetchSpecialties(),
    fetchDoctors({
      specialty: params.specialty,
      city: params.city,
      q: params.q,
      sort,
      min_reviews: withReviews ? 1 : undefined,
      page,
    }),
  ]);

  const applied = {
    q: params.q ?? "",
    specialty: params.specialty ?? "",
    city: params.city ?? "",
    min_reviews: withReviews ? "1" : "",
    sort: sort === "rating" ? "rating" : "",
  };
  const hasFilters = Object.values(applied).some(Boolean);

  return (
    <DoctorsDirectory
      specialties={specialties}
      applied={applied}
      total={doctors.meta.total}
    >
      {doctors.data.length === 0 ? (
        <EmptyState
          title={t("doctors.empty")}
          description={!hasFilters ? t("common.demoDataHint") : undefined}
          clearHref={hasFilters ? "/doctors" : undefined}
          clearLabel={hasFilters ? t("common.clearFilters") : undefined}
        />
      ) : (
        <ResultsGrid>
          {doctors.data.map((doctor) => (
            <li key={doctor.slug}>
              <DoctorCard doctor={doctor} />
            </li>
          ))}
        </ResultsGrid>
      )}

      {doctors.data.length > 0 ? (
        <Pagination
          basePath="/doctors"
          currentPage={doctors.meta.current_page}
          lastPage={doctors.meta.last_page}
          total={doctors.meta.total}
          searchParams={applied}
        />
      ) : null}
    </DoctorsDirectory>
  );
}
