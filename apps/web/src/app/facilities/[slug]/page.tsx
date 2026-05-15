import { notFound } from "next/navigation";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { DirectoryDetailLayout } from "@/components/directory/directory-detail-layout";
import { DirectoryDetailSidebar } from "@/components/directory/directory-detail-sidebar";
import { EntityLinkList } from "@/components/directory/entity-link-list";
import { FacilityProfileHero } from "@/components/directory/facility-profile-hero";
import { OfficeHoursGrid } from "@/components/directory/office-hours-grid";
import { ReviewSection } from "@/components/reviews/review-section";
import { PageSection } from "@/components/ui/page-section";
import { PageShell } from "@/components/ui/page-shell";
import { fetchFacility } from "@/lib/api/facilities";
import { fetchFacilityReviews } from "@/lib/api/reviews";
import { t } from "@/i18n/t";

type FacilityDetailPageProps = {
  params: Promise<{ slug: string }>;
};

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
  } catch {
    notFound();
  }

  const doctorItems = facility.doctors.map((doctor) => ({
    href: `/doctors/${doctor.slug}`,
    title: doctor.full_name,
    subtitle: [doctor.title, doctor.is_primary ? t("facilities.primaryWorkplace") : null]
      .filter(Boolean)
      .join(" · "),
  }));

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

            {Object.keys(facility.office_hours ?? {}).length > 0 ? (
              <PageSection title={t("directory.officeHours")}>
                <OfficeHoursGrid hours={facility.office_hours} />
              </PageSection>
            ) : null}

            <PageSection title={t("facilities.doctors")}>
              <EntityLinkList items={doctorItems} emptyMessage={t("facilities.noDoctors")} />
            </PageSection>

            <ReviewSection
              kind="facility"
              slug={slug}
              summary={facility.review_summary}
              reviews={reviews.data}
            />
          </>
        }
        sidebar={
          <DirectoryDetailSidebar
            phone={facility.phone}
            email={facility.email}
            website={facility.website}
            mapQuery={{
              name: facility.name,
              address: facility.address,
              city: facility.city,
            }}
          />
        }
      />
    </PageShell>
  );
}
