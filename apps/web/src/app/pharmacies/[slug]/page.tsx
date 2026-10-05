import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { PriceDisclaimer } from "@/components/catalog/price-disclaimer";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { DirectoryDetailLayout } from "@/components/directory/directory-detail-layout";
import { EntityLinkList } from "@/components/directory/entity-link-list";
import { FacilityProfileHero } from "@/components/directory/facility-profile-hero";
import { OfficeHoursGrid } from "@/components/directory/office-hours-grid";
import { PharmacySidebarContact } from "@/components/directory/pharmacy-sidebar-contact";
import { ProfileContentCard } from "@/components/design/profile-content-card";
import { ReviewSection } from "@/components/reviews/review-section";
import { JsonLd } from "@/components/seo/json-ld";
import { PageShell } from "@/components/ui/page-shell";
import { fetchPharmacy, fetchPharmacyProducts } from "@/lib/api/pharmacies";
import { fetchPublicSettings } from "@/lib/api/settings";
import { pageMetadata } from "@/lib/metadata";
import { absoluteUrl } from "@/lib/site-url";
import { placeJsonLd } from "@/lib/structured-data";
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
  if (!settings.public_pharmacies) {
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

  const productItems = products.data.map((product) => ({
    href: `/products/${product.slug}`,
    title: product.name,
    subtitle: [product.category, `${product.price} ${product.currency}`]
      .filter(Boolean)
      .join(" · "),
  }));

  return (
    <PageShell gap="loose" className="pb-16 pt-[18px]">
      <JsonLd
        data={placeJsonLd(
          "Pharmacy",
          pharmacy,
          absoluteUrl(`/pharmacies/${pharmacy.slug}`),
        )}
      />
      <Breadcrumbs
        items={[
          { label: t("common.home"), href: "/" },
          { label: t("pharmacies.title"), href: "/pharmacies" },
          { label: pharmacy.name },
        ]}
      />

      <DirectoryDetailLayout
        main={
          <div className="space-y-5">
            <FacilityProfileHero
              facility={pharmacy}
              typeLabel={t("nav.pharmacies")}
            />

            <PriceDisclaimer />

            {Object.keys(pharmacy.office_hours ?? {}).length > 0 ? (
              <ProfileContentCard title={t("directory.officeHours")}>
                <OfficeHoursGrid hours={pharmacy.office_hours} />
              </ProfileContentCard>
            ) : null}

            <ProfileContentCard title={t("pharmacies.products")}>
              <EntityLinkList
                items={productItems}
                emptyMessage={t("pharmacies.noProducts")}
              />
            </ProfileContentCard>

            <ReviewSection
              kind="pharmacy"
              slug={slug}
              summary={pharmacy.review_summary}
              searchParams={reviewQuery}
            />
          </div>
        }
        sidebar={<PharmacySidebarContact pharmacy={pharmacy} />}
      />
    </PageShell>
  );
}
