import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { PriceDisclaimer } from "@/components/catalog/price-disclaimer";
import { ComingSoonShell } from "@/components/layout/coming-soon-shell";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { DirectoryDetailLayout } from "@/components/directory/directory-detail-layout";
import { DirectoryDetailSidebar } from "@/components/directory/directory-detail-sidebar";
import { EntityLinkList } from "@/components/directory/entity-link-list";
import { FacilityProfileHero } from "@/components/directory/facility-profile-hero";
import { OfficeHoursGrid } from "@/components/directory/office-hours-grid";
import { ReviewSection } from "@/components/reviews/review-section";
import { PageSection } from "@/components/ui/page-section";
import { PageShell } from "@/components/ui/page-shell";
import { fetchPharmacy, fetchPharmacyProducts } from "@/lib/api/pharmacies";
import { fetchFacilityReviews } from "@/lib/api/reviews";
import { fetchPublicSettings } from "@/lib/api/settings";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

type PharmacyDetailPageProps = {
  params: Promise<{ slug: string }>;
};

export async function generateMetadata({
  params,
}: PharmacyDetailPageProps): Promise<Metadata> {
  const settings = await fetchPublicSettings();

  if (!settings.public_pharmacies) {
    return pageMetadata(t("pharmacies.title"));
  }

  const { slug } = await params;

  try {
    const pharmacy = await fetchPharmacy(slug);

    return pageMetadata(pharmacy.name, pharmacy.city ?? t("pharmacies.description"));
  } catch {
    return pageMetadata(t("pharmacies.title"));
  }
}

export default async function PharmacyDetailPage({
  params,
}: PharmacyDetailPageProps) {
  const settings = await fetchPublicSettings();

  if (!settings.public_pharmacies) {
    return (
      <ComingSoonShell
        title={t("pharmacies.title")}
        description={t("pharmacies.description")}
      />
    );
  }

  const { slug } = await params;

  let pharmacy;
  let products;
  let reviews;

  try {
    [pharmacy, products, reviews] = await Promise.all([
      fetchPharmacy(slug),
      fetchPharmacyProducts(slug),
      fetchFacilityReviews(slug),
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
    <PageShell className="gap-8">
      <Breadcrumbs
        items={[
          { label: t("common.home"), href: "/" },
          { label: t("pharmacies.title"), href: "/pharmacies" },
          { label: pharmacy.name },
        ]}
      />

      <DirectoryDetailLayout
        main={
          <>
            <FacilityProfileHero
              facility={pharmacy}
              typeLabel={t("nav.pharmacies")}
            />

            <PriceDisclaimer />

            {Object.keys(pharmacy.office_hours ?? {}).length > 0 ? (
              <PageSection title={t("directory.officeHours")}>
                <OfficeHoursGrid hours={pharmacy.office_hours} />
              </PageSection>
            ) : null}

            <PageSection title={t("pharmacies.products")}>
              <EntityLinkList items={productItems} emptyMessage={t("pharmacies.noProducts")} />
            </PageSection>

            <ReviewSection
              kind="facility"
              slug={slug}
              summary={pharmacy.review_summary}
              reviews={reviews.data}
            />
          </>
        }
        sidebar={
          <DirectoryDetailSidebar
            phone={pharmacy.phone}
            email={pharmacy.email}
            website={pharmacy.website}
            mapQuery={{
              name: pharmacy.name,
              address: pharmacy.address,
              city: pharmacy.city,
            }}
          />
        }
      />
    </PageShell>
  );
}
