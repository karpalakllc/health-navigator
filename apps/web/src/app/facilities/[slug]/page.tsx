import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { DirectoryDetailLayout } from "@/components/directory/directory-detail-layout";
import { FacilityDoctorList } from "@/components/directory/facility-doctor-list";
import { FacilityEmergencyBanner } from "@/components/directory/facility-emergency-banner";
import { FacilityProfileHero } from "@/components/directory/facility-profile-hero";
import { FacilitySidebarContact } from "@/components/directory/facility-sidebar-contact";
import { OfficeHoursGrid } from "@/components/directory/office-hours-grid";
import { TagList } from "@/components/directory/tag-list";
import { ReviewSection } from "@/components/reviews/review-section";
import { PageSection } from "@/components/ui/page-section";
import { PageShell } from "@/components/ui/page-shell";
import { fetchFacility } from "@/lib/api/facilities";
import { fetchFacilityReviews } from "@/lib/api/reviews";
import { pageMetadata } from "@/lib/metadata";
import { ApiRequestError } from "@/lib/api/server";
import { t } from "@/i18n/t";

type FacilityDetailPageProps = {
  params: Promise<{ slug: string }>;
};

export async function generateMetadata({
  params,
}: FacilityDetailPageProps): Promise<Metadata> {
  const { slug } = await params;

  try {
    const facility = await fetchFacility(slug);
    const description = [facility.city, facility.description?.slice(0, 140)]
      .filter(Boolean)
      .join(" · ");

    return pageMetadata(facility.name, description || t("facilities.description"));
  } catch {
    return pageMetadata(t("facilities.title"));
  }
}

export default async function FacilityDetailPage({
  params,
}: FacilityDetailPageProps) {
  const { slug } = await params;

  let facility;
  let reviews;

  try {
    [facility, reviews] = await Promise.all([
      fetchFacility(slug),
      fetchFacilityReviews(slug),
    ]);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

    throw error;
  }

  const officeHourEntries = Object.entries(facility.office_hours ?? {});

  return (
    <PageShell className="gap-8">
      <Breadcrumbs
        items={[
          { label: t("common.home"), href: "/" },
          { label: t("facilities.title"), href: "/facilities" },
          { label: facility.name },
        ]}
      />

      <DirectoryDetailLayout
        main={
          <>
            <FacilityProfileHero facility={facility} />

            {facility.has_emergency_services ? <FacilityEmergencyBanner /> : null}

            {facility.departments.length > 0 ? (
              <PageSection title={t("facilities.departments")}>
                <TagList items={facility.departments} />
              </PageSection>
            ) : null}

            {officeHourEntries.length > 0 ? (
              <PageSection title={t("directory.officeHours")}>
                <OfficeHoursGrid hours={Object.fromEntries(officeHourEntries)} />
              </PageSection>
            ) : null}

            <PageSection title={t("facilities.doctors")}>
              <FacilityDoctorList
                doctors={facility.doctors}
                emptyMessage={t("facilities.noDoctors")}
              />
            </PageSection>

            <ReviewSection
              kind="facility"
              slug={slug}
              summary={facility.review_summary}
              reviews={reviews.data}
            />
          </>
        }
        sidebar={<FacilitySidebarContact facility={facility} />}
      />
    </PageShell>
  );
}
