import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { DirectoryDetailLayout } from "@/components/directory/directory-detail-layout";
import { EntityLinkList } from "@/components/directory/entity-link-list";
import {
  ProfileCallBar,
  ProfileContactCard,
  ProfileContactList,
  type ContactInfo,
} from "@/components/directory/profile-contact";
import { ProfileHeader } from "@/components/directory/profile-header";
import { RecordRecentlyViewed } from "@/components/directory/record-recently-viewed";
import {
  HoursTable,
  ProfileSection,
  ProfileTagList,
} from "@/components/directory/profile-parts";
import { JsonLd } from "@/components/seo/json-ld";
import { ReviewSection } from "@/components/reviews/review-section";
import { ProfileCorrectionLinks } from "@/components/corrections/profile-correction-links";
import { FeaturedTag, Tag } from "@/components/ui/tag";
import { Monogram } from "@/components/ui/user-avatar";
import { fetchFacility } from "@/lib/api/facilities";
import { fetchRelatedForumTopics } from "@/lib/api/forum";
import { isModuleOn } from "@/lib/api/public-settings";
import { fetchPublicSettings } from "@/lib/api/settings";
import { ForumRelatedTopics } from "@/components/forum/forum-related-topics";
import { facilityKindLabel } from "@/lib/facility-labels";
import {
  googleMapsDirectionsUrl,
  hasMapCoordinates,
  addressMapUrl,
} from "@/lib/maps";
import { pageMetadata, profileMeta } from "@/lib/metadata";
import { officeHoursRows } from "@/lib/office-hours";
import { ApiRequestError } from "@/lib/api/server";
import { absoluteUrl } from "@/lib/site-url";
import {
  breadcrumbJsonLd,
  facilitySchemaType,
  placeJsonLd,
} from "@/lib/structured-data";
import { ProfileReportButton } from "@/components/reports/profile-report-button";
import { t, tCount } from "@/i18n/t";

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
    const { title, description } = profileMeta({
      name: facility.name,
      kind: facilityKindLabel(facility.type),
      city: facility.city,
      text: facility.description,
      fallback: t("facilities.description"),
    });

    return pageMetadata(title, description, { path: `/facilities/${slug}` });
  } catch (error) {
    // The not-found metadata (noindex), not a generic indexable title.
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

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
  // Decorative: a failing forum lookup (or the forum switched off) only
  // leaves the box out.
  const forumTopicsPromise = fetchPublicSettings()
    .then((settings) =>
      isModuleOn(settings, "public_forum")
        ? fetchRelatedForumTopics({ facility: slug })
        : [],
    )
    .catch(() => []);

  try {
    facility = await fetchFacility(slug);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

    throw error;
  }

  const forumTopics = await forumTopicsPromise;
  const breadcrumbs = breadcrumbJsonLd([
    { name: t("common.home"), url: absoluteUrl("/") },
    { name: t("facilities.title"), url: absoluteUrl("/facilities") },
    { name: facility.name },
  ]);

  const now = new Date();
  const hasHours = officeHoursRows(facility.office_hours, now).length > 0;
  const directionsHref = hasMapCoordinates(
    facility.latitude,
    facility.longitude,
  )
    ? googleMapsDirectionsUrl(
        facility.latitude as number,
        facility.longitude as number,
      )
    : addressMapUrl({
        name: facility.name,
        address: facility.address,
        city: facility.city,
      });

  const contact: ContactInfo = {
    name: facility.name,
    phone: facility.phone,
    email: facility.email,
    website: facility.website,
    place:
      facility.address || facility.city
        ? {
            title: facility.address ?? facility.city ?? "",
            sub:
              facility.address &&
              facility.city &&
              !facility.address.includes(facility.city)
                ? facility.city
                : null,
          }
        : null,
    directionsHref,
    hours: facility.office_hours,
    hoursAnchor: hasHours ? "hours" : undefined,
    coordinates: { latitude: facility.latitude, longitude: facility.longitude },
    now,
  };

  const doctorItems = facility.doctors.map((doctor) => ({
    href: `/doctors/${doctor.slug}`,
    title: doctor.full_name,
    subtitle: doctor.is_primary ? t("facilities.primaryWorkplace") : undefined,
    leading: (
      <Monogram name={doctor.full_name} kind="doctor" size={44} tone="white" />
    ),
  }));

  return (
    <>
      <RecordRecentlyViewed
        kind="facility"
        slug={facility.slug}
        name={facility.name}
        subtitle={[facilityKindLabel(facility.type), facility.city]
          .filter(Boolean)
          .join(" · ")}
        avatarUrl={facility.avatar_url}
      />
      <JsonLd
        data={placeJsonLd(
          facilitySchemaType(facility.type),
          facility,
          absoluteUrl(`/facilities/${facility.slug}`),
        )}
      />
      {breadcrumbs ? <JsonLd data={breadcrumbs} /> : null}
      <DirectoryDetailLayout
        back={{ href: "/facilities", label: t("facilities.back") }}
        breadcrumbs={[
          { label: t("common.home"), href: "/" },
          { label: t("facilities.title"), href: "/facilities" },
          { label: facility.name },
        ]}
        main={
          <>
            <ProfileHeader
              kind="facility"
              avatarUrl={facility.avatar_url}
              cover={{ url: facility.cover_url }}
              name={facility.name}
              subtitle={[facilityKindLabel(facility.type), facility.city]
                .filter(Boolean)
                .join(" · ")}
              summary={facility.review_summary}
              tags={
                <>
                  {facility.has_emergency_services ? (
                    <Tag icon="building">
                      {t("facilities.emergencyAvailable")}
                    </Tag>
                  ) : null}
                  {facility.departments.length > 0 ? (
                    <Tag>
                      {tCount(
                        "facilities.departmentCount",
                        facility.departments.length,
                      )}
                    </Tag>
                  ) : null}
                  {facility.is_featured ? <FeaturedTag /> : null}
                </>
              }
            />
            <ProfileReportButton subject="facility" slug={slug} />

            <ProfileContactList info={contact} />

            {facility.description ? (
              <ProfileSection id="about" title={t("facilities.about")}>
                <p className="type-reading measure text-ink">
                  {facility.description}
                </p>
              </ProfileSection>
            ) : null}

            {facility.departments.length > 0 ? (
              <ProfileSection
                id="departments"
                title={t("facilities.departments")}
              >
                <ProfileTagList items={facility.departments} />
              </ProfileSection>
            ) : null}

            {hasHours ? (
              <ProfileSection id="hours" title={t("directory.officeHours")}>
                <HoursTable hours={facility.office_hours} now={now} />
              </ProfileSection>
            ) : null}

            <ProfileSection
              id="facility-doctors"
              title={t("facilities.doctors")}
            >
              <EntityLinkList
                items={doctorItems}
                emptyMessage={t("facilities.noDoctors")}
                columns={2}
              />
            </ProfileSection>

            <ReviewSection
              kind="facility"
              slug={slug}
              summary={facility.review_summary}
              searchParams={reviewQuery}
            />

            <ForumRelatedTopics
              topics={forumTopics}
              title={t("seo.facilityForumTitle")}
              lead={t("seo.facilityForumLead")}
              headingId="facility-forum-heading"
              hideWhenEmpty
            />

            <ProfileCorrectionLinks subject="facility" slug={slug} />
          </>
        }
        sidebar={<ProfileContactCard info={contact} />}
        footer={<ProfileCallBar info={contact} />}
      />
    </>
  );
}
