import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { PriceDisclaimer } from "@/components/catalog/price-disclaimer";
import { DirectoryDetailLayout } from "@/components/directory/directory-detail-layout";
import { EntityLinkList } from "@/components/directory/entity-link-list";
import {
  ProfileCallBar,
  ProfileContactCard,
  ProfileContactList,
  type ContactInfo,
} from "@/components/directory/profile-contact";
import { ProfileHeader } from "@/components/directory/profile-header";
import { VerificationBadge } from "@/components/directory/verification-badge";
import { RecordRecentlyViewed } from "@/components/directory/record-recently-viewed";
import {
  HoursTable,
  ProfileSection,
} from "@/components/directory/profile-parts";
import { ReviewSection } from "@/components/reviews/review-section";
import { JsonLd } from "@/components/seo/json-ld";
import { FeaturedTag } from "@/components/ui/tag";
import { fetchPharmacy, fetchPharmacyProducts } from "@/lib/api/pharmacies";
import { fetchPublicSettings } from "@/lib/api/settings";
import { isModuleOn } from "@/lib/api/public-settings";
import {
  addressMapUrl,
  googleMapsDirectionsUrl,
  hasMapCoordinates,
} from "@/lib/maps";
import { pageMetadata } from "@/lib/metadata";
import { officeHoursRows } from "@/lib/office-hours";
import { absoluteUrl } from "@/lib/site-url";
import { placeJsonLd } from "@/lib/structured-data";
import { ProfileReportButton } from "@/components/reports/profile-report-button";
import { t } from "@/i18n/t";

type PharmacyDetailPageProps = {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{
    review_page?: string;
    review_sort?: string;
    review_rating?: string;
  }>;
};

export async function generateMetadata({
  params,
}: PharmacyDetailPageProps): Promise<Metadata> {
  const settings = await fetchPublicSettings();

  if (!settings.public_pharmacies) {
    return pageMetadata(t("pharmacies.title"), undefined, { noIndex: true });
  }

  const { slug } = await params;

  try {
    const pharmacy = await fetchPharmacy(slug);

    return pageMetadata(
      pharmacy.name,
      pharmacy.city ?? t("pharmacies.description"),
      { path: `/pharmacies/${slug}` },
    );
  } catch {
    return pageMetadata(t("pharmacies.title"));
  }
}

export default async function PharmacyDetailPage({
  params,
  searchParams,
}: PharmacyDetailPageProps) {
  const settings = await fetchPublicSettings();

  // A switched-off module has no detail pages: the list page carries the
  // "coming soon" stand-in, a detail URL is simply not there.
  if (!isModuleOn(settings, "public_pharmacies")) {
    notFound();
  }

  const { slug } = await params;
  const reviewQuery = await searchParams;

  let pharmacy;
  let products;

  try {
    [pharmacy, products] = await Promise.all([
      fetchPharmacy(slug),
      fetchPharmacyProducts(slug),
    ]);
  } catch {
    notFound();
  }

  const now = new Date();
  const hasHours = officeHoursRows(pharmacy.office_hours, now).length > 0;
  const directionsHref = hasMapCoordinates(
    pharmacy.latitude,
    pharmacy.longitude,
  )
    ? googleMapsDirectionsUrl(
        pharmacy.latitude as number,
        pharmacy.longitude as number,
      )
    : addressMapUrl({
        name: pharmacy.name,
        address: pharmacy.address,
        city: pharmacy.city,
      });

  const contact: ContactInfo = {
    name: pharmacy.name,
    phone: pharmacy.phone,
    email: pharmacy.email,
    website: pharmacy.website,
    place:
      pharmacy.address || pharmacy.city
        ? {
            title: pharmacy.address ?? pharmacy.city ?? "",
            sub:
              pharmacy.address &&
              pharmacy.city &&
              !pharmacy.address.includes(pharmacy.city)
                ? pharmacy.city
                : null,
          }
        : null,
    directionsHref,
    hours: pharmacy.office_hours,
    hoursAnchor: hasHours ? "hours" : undefined,
    coordinates: { latitude: pharmacy.latitude, longitude: pharmacy.longitude },
    now,
  };

  const productItems = products.data.map((product) => ({
    href: `/products/${product.slug}`,
    title: product.name,
    subtitle: product.category ?? undefined,
    aside: (
      <span className="type-body font-semibold tabular-nums text-ink">
        {product.price.toLocaleString("mk-MK")} {product.currency}
      </span>
    ),
  }));

  return (
    <>
      <RecordRecentlyViewed
        kind="pharmacy"
        slug={pharmacy.slug}
        name={pharmacy.name}
        subtitle={[t("pharmacies.kind"), pharmacy.city]
          .filter(Boolean)
          .join(" · ")}
        avatarUrl={pharmacy.avatar_url}
      />
      <JsonLd
        data={placeJsonLd(
          "Pharmacy",
          pharmacy,
          absoluteUrl(`/pharmacies/${pharmacy.slug}`),
        )}
      />
      <DirectoryDetailLayout
        back={{ href: "/pharmacies", label: t("pharmacies.back") }}
        breadcrumbs={[
          { label: t("common.home"), href: "/" },
          { label: t("pharmacies.title"), href: "/pharmacies" },
          { label: pharmacy.name },
        ]}
        main={
          <>
            <ProfileHeader
              kind="pharmacy"
              avatarUrl={pharmacy.avatar_url}
              cover={{ url: pharmacy.cover_url }}
              name={pharmacy.name}
              subtitle={[t("pharmacies.kind"), pharmacy.city]
                .filter(Boolean)
                .join(" · ")}
              verification={
                <VerificationBadge
                  verification={pharmacy.verification}
                  kind="pharmacy"
                />
              }
              reportAction={
                <ProfileReportButton subject="pharmacy" slug={slug} compact />
              }
              summary={pharmacy.review_summary}
              tags={pharmacy.is_featured ? <FeaturedTag /> : undefined}
            />

            <ProfileContactList info={contact} />

            {pharmacy.description ? (
              <ProfileSection id="about" title={t("pharmacies.about")}>
                <p className="type-reading measure text-ink">
                  {pharmacy.description}
                </p>
              </ProfileSection>
            ) : null}

            {hasHours ? (
              <ProfileSection id="hours" title={t("directory.officeHours")}>
                <HoursTable hours={pharmacy.office_hours} now={now} />
              </ProfileSection>
            ) : null}

            <ProfileSection id="products" title={t("pharmacies.products")}>
              <div className="flex flex-col gap-4">
                <PriceDisclaimer />
                <EntityLinkList
                  items={productItems}
                  icon="pill"
                  emptyMessage={t("pharmacies.noProducts")}
                />
              </div>
            </ProfileSection>

            <ReviewSection
              kind="pharmacy"
              slug={slug}
              profileName={pharmacy.name}
              summary={pharmacy.review_summary}
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
