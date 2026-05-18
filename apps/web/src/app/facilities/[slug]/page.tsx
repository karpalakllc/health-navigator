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
import { ProfileContentCard } from "@/components/design/profile-content-card";
import { ReviewSection } from "@/components/reviews/review-section";
import { PageShell } from "@/components/ui/page-shell";
import { fetchFacility } from "@/lib/api/facilities";
import { pageMetadata } from "@/lib/metadata";
import { ApiRequestError } from "@/lib/api/server";
import { t } from "@/i18n/t";

type FacilityDetailPageProps = {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{
    review_page?: string;
    review_sort?: string;
    review_rating?: string;
  }>;
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
  searchParams,
}: FacilityDetailPageProps) {
  const { slug } = await params;
  const reviewQuery = await searchParams;

  let facility;

  try {
    facility = await fetchFacility(slug);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

    throw error;
  }

  const officeHourEntries = Object.entries(facility.office_hours ?? {});

  return (
    <PageShell gap="loose" className="pb-16 pt-[18px]">
      <Breadcrumbs
        items={[
          { label: t("common.home"), href: "/" },
          { label: t("facilities.title"), href: "/facilities" },
          { label: facility.name },
        ]}
      />

      <DirectoryDetailLayout
        main={
          <div className="space-y-5">
            <FacilityProfileHero facility={facility} />

            {facility.has_emergency_services ? <FacilityEmergencyBanner /> : null}

            {facility.departments.length > 0 ? (
              <ProfileContentCard title={t("facilities.departments")}>
                <TagList items={facility.departments} />
              </ProfileContentCard>
            ) : null}

            {officeHourEntries.length > 0 ? (
              <ProfileContentCard title={t("directory.officeHours")}>
                <OfficeHoursGrid hours={Object.fromEntries(officeHourEntries)} />
              </ProfileContentCard>
            ) : null}

            <ProfileContentCard title={t("facilities.doctors")}>
              <FacilityDoctorList
                doctors={facility.doctors}
                emptyMessage={t("facilities.noDoctors")}
              />
            </ProfileContentCard>

            <ReviewSection
              kind="facility"
              slug={slug}
              summary={facility.review_summary}
              searchParams={reviewQuery}
            />
          </div>
        }
        sidebar={<FacilitySidebarContact facility={facility} />}
      />
    </PageShell>
  );
}
