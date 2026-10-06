import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { JsonLd } from "@/components/seo/json-ld";
import { DirectoryDetailLayout } from "@/components/directory/directory-detail-layout";
import { EntityLinkList } from "@/components/directory/entity-link-list";
import {
  ProfileCallBar,
  ProfileContactCard,
  ProfileContactList,
  type ContactInfo,
} from "@/components/directory/profile-contact";
import { ProfileHeader } from "@/components/directory/profile-header";
import {
  HoursTable,
  ProfileSection,
  ProfileTagList,
} from "@/components/directory/profile-parts";
import { ReviewSection } from "@/components/reviews/review-section";
import { Icon } from "@/components/ui/icons";
import { SponsoredBadge } from "@/components/ui/sponsored-badge";
import { FeaturedTag, Tag } from "@/components/ui/tag";
import { fetchDoctor } from "@/lib/api/doctors";
import { facilityPublicPath, facilityTypeLabel } from "@/lib/facility-labels";
import { addressMapUrl } from "@/lib/maps";
import { pageMetadata } from "@/lib/metadata";
import { officeHoursRows } from "@/lib/office-hours";
import { absoluteUrl } from "@/lib/site-url";
import { ApiRequestError } from "@/lib/api/server";
import { t, tFormat } from "@/i18n/t";

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
      doctor.specialties.find((s) => s.is_primary)?.name ??
      doctor.specialties[0]?.name;
    const description = [specialty, doctor.city, doctor.bio?.slice(0, 120)]
      .filter(Boolean)
      .join(" · ");

    return pageMetadata(
      doctor.full_name,
      description || t("doctors.description"),
      {
        path: `/doctors/${slug}`,
      },
    );
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

  const now = new Date();
  const primarySpecialty =
    doctor.specialties.find((s) => s.is_primary)?.name ??
    doctor.specialties[0]?.name;
  const specialtyLine = [primarySpecialty, doctor.subspecialty]
    .filter(Boolean)
    .join(" · ");
  const otherSpecialties = doctor.specialties
    .filter((s) => s.name !== primarySpecialty)
    .map((s) => s.name);
  const workplace =
    doctor.facilities.find((f) => f.is_primary) ?? doctor.facilities[0];
  const directionsHref = workplace
    ? addressMapUrl({ name: workplace.name, city: workplace.city })
    : null;
  const hasHours = officeHoursRows(doctor.office_hours, now).length > 0;

  const contact: ContactInfo = {
    name: doctor.full_name,
    phone: doctor.phone,
    email: doctor.email,
    place: workplace
      ? {
          title: workplace.name,
          sub: workplace.city,
          href: facilityPublicPath(workplace.type, workplace.slug),
        }
      : doctor.city
        ? { title: doctor.city }
        : null,
    directionsHref,
    hours: doctor.office_hours,
    hoursAnchor: hasHours ? "hours" : undefined,
    fee: doctor.consultation_fee_note
      ? {
          label: `${t("doctors.consultationFee")}: ${doctor.consultation_fee_note}`,
          note: t("doctors.feeNote"),
        }
      : null,
    now,
  };

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

  return (
    <>
      {/*
        Only facts the database actually holds — no credentials, ratings or
        affiliations we cannot substantiate. Overstating a clinician's
        qualifications in structured data is a trust and regulatory problem,
        not just an SEO one.
      */}
      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@type": "Physician",
          name: doctor.full_name,
          url: absoluteUrl(`/doctors/${doctor.slug}`),
          ...(primarySpecialty ? { medicalSpecialty: primarySpecialty } : {}),
          ...(doctor.city
            ? {
                address: {
                  "@type": "PostalAddress",
                  addressLocality: doctor.city,
                },
              }
            : {}),
          ...(doctor.phone ? { telephone: doctor.phone } : {}),
        }}
      />
      <DirectoryDetailLayout
        back={{ href: "/doctors", label: t("doctors.back") }}
        breadcrumbs={[
          { label: t("common.home"), href: "/" },
          { label: t("doctors.title"), href: "/doctors" },
          { label: doctor.full_name },
        ]}
        main={
          <>
            <ProfileHeader
              kind="doctor"
              avatarUrl={doctor.avatar_url}
              name={doctor.full_name}
              subtitle={specialtyLine || undefined}
              summary={doctor.review_summary}
              tags={
                <>
                  {doctor.accepts_new_patients ? (
                    <Tag tone="care" icon="check">
                      {t("doctors.acceptingPatients")}
                    </Tag>
                  ) : (
                    <Tag>{t("doctors.notAcceptingPatients")}</Tag>
                  )}
                  {doctor.years_experience ? (
                    <Tag icon="award">
                      {tFormat("doctors.yearsExperience", {
                        years: String(doctor.years_experience),
                      })}
                    </Tag>
                  ) : null}
                  {doctor.languages.length > 0 ? (
                    <Tag icon="globe" className="lg:hidden">
                      {doctor.languages.join(", ")}
                    </Tag>
                  ) : null}
                  {doctor.is_sponsored ? <SponsoredBadge /> : null}
                  {doctor.is_featured && !doctor.is_sponsored ? (
                    <FeaturedTag />
                  ) : null}
                </>
              }
              details={
                doctor.languages.length > 0 || otherSpecialties.length > 0 ? (
                  <>
                    {doctor.languages.length > 0 ? (
                      <p className="flex items-center gap-2 type-body text-ink">
                        <Icon name="globe" size={20} className="text-ink-2" />
                        <span className="text-ink-2">
                          {t("doctors.languages")}:
                        </span>
                        {doctor.languages.join(", ")}
                      </p>
                    ) : null}
                    {otherSpecialties.length > 0 ? (
                      <p className="flex items-center gap-2 type-body text-ink">
                        <Icon
                          name="stethoscope"
                          size={20}
                          className="text-ink-2"
                        />
                        <span className="text-ink-2">
                          {t("doctors.specialties")}:
                        </span>
                        {otherSpecialties.join(", ")}
                      </p>
                    ) : null}
                  </>
                ) : undefined
              }
            />

            <ProfileContactList info={contact} />

            {doctor.bio || doctor.education ? (
              <ProfileSection id="about" title={t("doctors.about")}>
                {doctor.bio ? (
                  <p className="type-reading measure text-ink">{doctor.bio}</p>
                ) : null}
                {doctor.education ? (
                  <p className="mt-4 flex gap-2 type-body text-ink">
                    <Icon
                      name="award"
                      size={20}
                      className="mt-0.5 text-ink-2"
                    />
                    <span>
                      <span className="text-ink-2">
                        {t("doctors.education")}:
                      </span>{" "}
                      {doctor.education}
                    </span>
                  </p>
                ) : null}
              </ProfileSection>
            ) : null}

            {doctor.procedures.length > 0 ? (
              <ProfileSection id="procedures" title={t("doctors.procedures")}>
                <ProfileTagList items={doctor.procedures} />
              </ProfileSection>
            ) : null}

            {doctor.clinical_interests.length > 0 ? (
              <ProfileSection
                id="interests"
                title={t("doctors.clinicalInterests")}
              >
                <ProfileTagList items={doctor.clinical_interests} />
              </ProfileSection>
            ) : null}

            {hasHours ? (
              <ProfileSection id="hours" title={t("doctors.officeHours")}>
                <HoursTable hours={doctor.office_hours} now={now} />
              </ProfileSection>
            ) : null}

            <ProfileSection
              id="doctor-locations"
              title={t("doctors.facilities")}
            >
              <EntityLinkList
                items={facilityItems}
                emptyMessage={t("doctors.noFacilities")}
              />
            </ProfileSection>

            <ReviewSection
              kind="doctor"
              slug={slug}
              summary={doctor.review_summary}
              searchParams={reviewQuery}
            />
          </>
        }
        sidebar={<ProfileContactCard info={contact} />}
        footer={<ProfileCallBar info={contact} />}
      />
    </>
  );
}
