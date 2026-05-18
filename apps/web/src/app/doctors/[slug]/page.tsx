import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { DirectoryDetailLayout } from "@/components/directory/directory-detail-layout";
import { DoctorProfileHero } from "@/components/directory/doctor-profile-hero";
import { DoctorSidebarContact } from "@/components/directory/doctor-sidebar-contact";
import { EntityLinkList } from "@/components/directory/entity-link-list";
import { OfficeHoursGrid } from "@/components/directory/office-hours-grid";
import { TagList } from "@/components/directory/tag-list";
import { ProfileContentCard } from "@/components/design/profile-content-card";
import { ReviewSection } from "@/components/reviews/review-section";
import { PageShell } from "@/components/ui/page-shell";
import { fetchDoctor } from "@/lib/api/doctors";
import { facilityPublicPath, facilityTypeLabel } from "@/lib/facility-labels";
import { pageMetadata } from "@/lib/metadata";
import { ApiRequestError } from "@/lib/api/server";
import { t } from "@/i18n/t";

type DoctorDetailPageProps = {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{
    review_page?: string;
    review_sort?: string;
    review_rating?: string;
  }>;
};

export async function generateMetadata({
  params,
}: DoctorDetailPageProps): Promise<Metadata> {
  const { slug } = await params;

  try {
    const doctor = await fetchDoctor(slug);
    const specialty =
      doctor.specialties.find((s) => s.is_primary)?.name ?? doctor.specialties[0]?.name;
    const description = [specialty, doctor.city, doctor.bio?.slice(0, 120)]
      .filter(Boolean)
      .join(" · ");

    return pageMetadata(doctor.full_name, description || t("doctors.description"));
  } catch {
    return pageMetadata(t("doctors.title"));
  }
}

export default async function DoctorDetailPage({
  params,
  searchParams,
}: DoctorDetailPageProps) {
  const { slug } = await params;
  const reviewQuery = await searchParams;

  let doctor;

  try {
    doctor = await fetchDoctor(slug);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

    throw error;
  }

  const facilityItems = doctor.facilities.map((facility) => ({
    href: facilityPublicPath(facility.type, facility.slug),
    title: facility.name,
    subtitle: [
      facilityTypeLabel(facility.type),
      facility.city,
      facility.is_primary ? t("facilities.primaryWorkplace") : null,
    ]
      .filter(Boolean)
      .join(" · "),
  }));

  const officeHourEntries = Object.entries(doctor.office_hours ?? {});

  return (
    <PageShell gap="loose" className="pb-16 pt-[18px]">
      <Breadcrumbs
        items={[
          { label: t("common.home"), href: "/" },
          { label: t("doctors.title"), href: "/doctors" },
          { label: doctor.full_name },
        ]}
      />

      <DirectoryDetailLayout
        main={
          <div className="space-y-5">
            <DoctorProfileHero doctor={doctor} />

            {doctor.education ? (
              <ProfileContentCard title={t("doctors.education")}>
                <p className="text-sm leading-relaxed text-muted-foreground">{doctor.education}</p>
              </ProfileContentCard>
            ) : null}

            {doctor.languages.length > 0 ? (
              <ProfileContentCard title={t("doctors.languages")}>
                <TagList items={doctor.languages} />
              </ProfileContentCard>
            ) : null}

            {doctor.clinical_interests.length > 0 ? (
              <ProfileContentCard title={t("doctors.clinicalInterests")}>
                <TagList items={doctor.clinical_interests} />
              </ProfileContentCard>
            ) : null}

            {doctor.procedures.length > 0 ? (
              <ProfileContentCard title={t("doctors.procedures")}>
                <TagList items={doctor.procedures} />
              </ProfileContentCard>
            ) : null}

            {officeHourEntries.length > 0 ? (
              <ProfileContentCard title={t("doctors.officeHours")}>
                <OfficeHoursGrid hours={Object.fromEntries(officeHourEntries)} />
              </ProfileContentCard>
            ) : null}

            <ProfileContentCard title={t("doctors.facilities")} id="doctor-locations">
              <EntityLinkList items={facilityItems} emptyMessage={t("doctors.noFacilities")} />
            </ProfileContentCard>

            <ReviewSection
              kind="doctor"
              slug={slug}
              summary={doctor.review_summary}
              searchParams={reviewQuery}
            />
          </div>
        }
        sidebar={
          <DoctorSidebarContact doctor={doctor} />
        }
      />
    </PageShell>
  );
}
