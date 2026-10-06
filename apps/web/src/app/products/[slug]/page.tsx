import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { PriceDisclaimer } from "@/components/catalog/price-disclaimer";
import { ProductOffersTable } from "@/components/catalog/product-offers-table";
import { DirectoryDetailLayout } from "@/components/directory/directory-detail-layout";
import { ProfileSection } from "@/components/directory/profile-parts";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import { fetchProduct } from "@/lib/api/products";
import { fetchPublicSettings } from "@/lib/api/settings";
import { isModuleOn } from "@/lib/api/public-settings";
import type { ProductDetail } from "@/lib/api/types";
import { pageMetadata } from "@/lib/metadata";
import { t, tCount, tFormat } from "@/i18n/t";

type ProductDetailPageProps = {
  params: Promise<{ slug: string }>;
};

export async function generateMetadata({
  params,
}: ProductDetailPageProps): Promise<Metadata> {
  const settings = await fetchPublicSettings();

  if (!settings.public_products) {
    return pageMetadata(t("products.title"), undefined, { noIndex: true });
  }

  const { slug } = await params;

  try {
    const product = await fetchProduct(slug);

    return pageMetadata(
      product.name,
      product.category ?? t("products.description"),
      { path: `/products/${slug}` },
    );
  } catch {
    return pageMetadata(t("products.title"));
  }
}

function CheapestCard({ product }: { product: ProductDetail }) {
  const cheapest = [...product.offers].sort((a, b) => a.price - b.price)[0];

  if (!cheapest) {
    return null;
  }

  return (
    <Card
      as="aside"
      aria-labelledby="cheapest-title"
      className="flex flex-col gap-4 lg:sticky lg:top-[calc(var(--header-h)+1rem)]"
    >
      <div>
        <h2 id="cheapest-title" className="type-meta text-ink-2">
          {t("products.cheapestFrom")}
        </h2>
        <p className="mt-1 text-[2rem] font-bold leading-10 tabular-nums text-ink">
          {cheapest.price.toLocaleString("mk-MK")} {cheapest.currency}
        </p>
        <p className="type-body text-ink">
          {[cheapest.pharmacy.name, cheapest.pharmacy.city]
            .filter(Boolean)
            .join(" · ")}
        </p>
        <p className="type-meta mt-1 text-ink-2">
          {tCount("products.offerCount", product.offers_total)}
        </p>
      </div>
      <Button
        href={`/pharmacies/${cheapest.pharmacy.slug}`}
        size="lg"
        fullWidth
        trailingIcon="arrow-right"
      >
        {t("products.viewPharmacy")}
      </Button>
    </Card>
  );
}

export default async function ProductDetailPage({
  params,
}: ProductDetailPageProps) {
  const settings = await fetchPublicSettings();

  // A switched-off module has no detail pages: the list page carries the
  // "coming soon" stand-in, a detail URL is simply not there.
  if (!isModuleOn(settings, "public_products")) {
    notFound();
  }

  const { slug } = await params;

  let product;

  try {
    product = await fetchProduct(slug);
  } catch {
    notFound();
  }

  return (
    <DirectoryDetailLayout
      back={{ href: "/products", label: t("products.back") }}
      breadcrumbs={[
        { label: t("common.home"), href: "/" },
        { label: t("products.title"), href: "/products" },
        { label: product.name },
      ]}
      main={
        <>
          <Card
            edge
            padding="none"
            className="flex flex-col gap-4 rounded-sheet px-5 pb-6 pt-7 lg:flex-row lg:gap-8 lg:p-8"
          >
            <span className="inline-flex size-20 shrink-0 items-center justify-center rounded-full bg-sand text-ink lg:size-30">
              <Icon name="pill" size={36} />
            </span>
            <div className="flex min-w-0 flex-col gap-3">
              <h1 className="type-h1 text-ink">{product.name}</h1>
              {product.category ? (
                <div>
                  <Tag icon="package">{product.category}</Tag>
                </div>
              ) : null}
              {product.description ? (
                <p className="type-reading measure text-ink">
                  {product.description}
                </p>
              ) : null}
            </div>
          </Card>

          <div className="lg:hidden">
            <CheapestCard product={product} />
          </div>

          <ProfileSection id="offers" title={t("products.pharmacyOffers")}>
            <div className="flex flex-col gap-4">
              <PriceDisclaimer />
              <ProductOffersTable offers={product.offers} />
              {product.offers_truncated ? (
                <p className="type-meta text-ink-2">
                  {tFormat("products.offersTruncated", {
                    shown: product.offers.length,
                    total: product.offers_total,
                  })}
                </p>
              ) : null}
            </div>
          </ProfileSection>
        </>
      }
      sidebar={<CheapestCard product={product} />}
    />
  );
}
