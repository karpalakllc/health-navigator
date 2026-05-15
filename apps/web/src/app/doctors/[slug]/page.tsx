import { notFound } from "next/navigation";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { DirectoryDetailLayout } from "@/components/directory/directory-detail-layout";
import { DoctorProfileHero } from "@/components/directory/doctor-profile-hero";
import { EntityLinkList } from "@/components/directory/entity-link-list";
import { OfficeHoursGrid } from "@/components/directory/office-hours-grid";
import { TagList } from "@/components/directory/tag-list";
import { ReviewSection } from "@/components/reviews/review-section";
import { Card } from "@/components/ui/card";
import { ContactBlock } from "@/components/ui/contact-block";
import { PageSection } from "@/components/ui/page-section";
import { PageShell } from "@/components/ui/page-shell";
import { fetchDoctor } from "@/lib/api/doctors";
import { fetchDoctorReviews } from "@/lib/api/reviews";
import { facilityPublicPath, facilityTypeLabel } from "@/lib/facility-labels";
import { t } from "@/i18n/t";

type DoctorDetailPageProps = {
  params: Promise<{ slug: string }>;
};

export default async function DoctorDetailPage({
  params,
}: DoctorDetailPageProps) {
  const { slug } = await params;

  let doctor;
  let reviews;

  try {
    [doctor, reviews] = await Promise.all([
      fetchDoctor(slug),
      fetchDoctorReviews(slug),
    ]);
  } catch {
    notFound();
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
    <PageShell className="gap-8">
      <Breadcrumbs
        items={[
          { label: t("common.home"), href: "/" },
          { label: t("doctors.title"), href: "/doctors" },
          { label: doctor.full_name },
        ]}
      />

      <DirectoryDetailLayout
        main={
          <>
            <DoctorProfileHero doctor={doctor} />

            {doctor.education ? (
              <PageSection title={t("doctors.education")}>
                <p className="text-sm leading-relaxed text-muted-foreground">
                  {doctor.education}
                </p>
              </PageSection>
            ) : null}

            {doctor.languages.length > 0 ? (
              <PageSection title={t("doctors.languages")}>
                <TagList items={doctor.languages} />
              </PageSection>
            ) : null}

            {doctor.clinical_interests.length > 0 ? (
              <PageSection title={t("doctors.clinicalInterests")}>
                <TagList items={doctor.clinical_interests} />
              </PageSection>
            ) : null}

            {doctor.procedures.length > 0 ? (
              <PageSection title={t("doctors.procedures")}>
                <TagList items={doctor.procedures} />
              </PageSection>
            ) : null}

            {officeHourEntries.length > 0 ? (
              <PageSection title={t("doctors.officeHours")}>
                <OfficeHoursGrid
                  hours={Object.fromEntries(officeHourEntries)}
                />
              </PageSection>
            ) : null}

            <PageSection title={t("doctors.facilities")}>
              <EntityLinkList
                items={facilityItems}
                emptyMessage={t("doctors.noFacilities")}
              />
            </PageSection>

            <ReviewSection
              kind="doctor"
              slug={slug}
              summary={doctor.review_summary}
              reviews={reviews.data}
            />
          </>
        }
        sidebar={
          <aside className="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <Card className="space-y-4 p-6">
              <h2 className="font-semibold">{t("doctors.bookContact")}</h2>
              <ContactBlock phone={doctor.phone} email={doctor.email} />
              {doctor.consultation_fee_note ? (
                <p className="text-sm text-muted-foreground">
                  <span className="font-medium text-foreground">
                    {t("doctors.consultationFee")}:{" "}
                  </span>
                  {doctor.consultation_fee_note}
                </p>
              ) : null}
            </Card>
            <p className="rounded-xl bg-secondary/50 p-4 text-xs leading-relaxed text-muted-foreground">
              {t("footer.informational")}
            </p>
          </aside>
        }
      />
    </PageShell>
  );
}
