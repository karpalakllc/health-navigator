import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { PriceDisclaimer } from "@/components/catalog/price-disclaimer";
import { ProductOffersTable } from "@/components/catalog/product-offers-table";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { Badge } from "@/components/ui/badge";
import { PageSection } from "@/components/ui/page-section";
import { PageShell } from "@/components/ui/page-shell";
import { ComingSoonShell } from "@/components/layout/coming-soon-shell";
import { fetchProduct } from "@/lib/api/products";
import { fetchPublicSettings } from "@/lib/api/settings";
import { pageMetadata } from "@/lib/metadata";
import { t, tFormat } from "@/i18n/t";

type ProductDetailPageProps = {
  params: Promise<{ slug: string }>;
};

export async function generateMetadata({
  params,
}: ProductDetailPageProps): Promise<Metadata> {
  const settings = await fetchPublicSettings();

  if (!settings.public_products) {
    return pageMetadata(t("products.title"));
  }

  const { slug } = await params;

  try {
    const product = await fetchProduct(slug);

    return pageMetadata(product.name, product.category ?? t("products.description"));
  } catch {
    return pageMetadata(t("products.title"));
  }
}

export default async function ProductDetailPage({
  params,
}: ProductDetailPageProps) {
  const settings = await fetchPublicSettings();

  if (!settings.public_products) {
    return (
      <ComingSoonShell
        title={t("products.title")}
        description={t("products.description")}
      />
    );
  }

  const { slug } = await params;

  let product;

  try {
    product = await fetchProduct(slug);
  } catch {
    notFound();
  }

  return (
    <PageShell className="gap-8">
      <Breadcrumbs
        items={[
          { label: t("common.home"), href: "/" },
          { label: t("products.title"), href: "/products" },
          { label: product.name },
        ]}
      />

      <header className="space-y-2">
        <h1 className="text-3xl font-semibold tracking-tight text-foreground">
          {product.name}
        </h1>
        {product.category ? (
          <Badge variant="outline">{product.category}</Badge>
        ) : null}
      </header>

      <PriceDisclaimer />

      {product.description ? (
        <p className="leading-relaxed text-muted-foreground">{product.description}</p>
      ) : null}

      <PageSection title={t("products.pharmacyOffers")}>
        <ProductOffersTable offers={product.offers} />
        {product.offers_truncated ? (
          <p className="mt-3 text-sm text-muted-foreground">
            {tFormat("products.offersTruncated", {
              shown: product.offers.length,
              total: product.offers_total,
            })}
          </p>
        ) : null}
      </PageSection>
    </PageShell>
  );
}
