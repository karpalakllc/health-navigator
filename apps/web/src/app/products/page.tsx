import type { Metadata } from "next";
import { PriceDisclaimer } from "@/components/catalog/price-disclaimer";
import { ProductCard } from "@/components/catalog/product-card";
import { DirectoryCardGrid } from "@/components/directory/directory-card-grid";
import { ProductsFilterBar } from "@/components/directory/products-filter-bar";
import { EmptyState } from "@/components/directory/empty-state";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { Pagination } from "@/components/directory/pagination";
import { ComingSoonShell } from "@/components/layout/coming-soon-shell";
import { fetchProducts } from "@/lib/api/products";
import { fetchPublicSettings } from "@/lib/api/settings";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("products.title"),
  t("products.description"),
);

type ProductsPageProps = {
  searchParams: Promise<{
    q?: string;
    category?: string;
    pharmacy?: string;
    page?: string;
  }>;
};

export default async function ProductsPage({
  searchParams,
}: ProductsPageProps) {
  const settings = await fetchPublicSettings();

  if (!settings.public_products) {
    return (
      <ComingSoonShell
        title={t("products.title")}
        description={t("products.description")}
      />
    );
  }

  const params = await searchParams;
  const page = params.page ? Number(params.page) : 1;

  const products = await fetchProducts({
    q: params.q,
    category: params.category,
    pharmacy: params.pharmacy,
    page: Number.isFinite(page) ? page : 1,
  });

  const filterParams = {
    q: params.q,
    category: params.category,
    pharmacy: params.pharmacy,
  };

  const hasFilters = Boolean(params.q || params.category || params.pharmacy);

  return (
    <PageShell>
      <PageHeader
        title={t("products.title")}
        description={t("products.description")}
      />
      <PriceDisclaimer />

      <ProductsFilterBar
        values={filterParams}
        resultsTotal={products.meta.total}
      />

      {products.data.length === 0 ? (
        <EmptyState
          title={t("products.empty")}
          description={!hasFilters ? t("common.demoDataHint") : undefined}
          clearHref={hasFilters ? "/products" : undefined}
          clearLabel={hasFilters ? t("common.clearFilters") : undefined}
        />
      ) : (
        <DirectoryCardGrid>
          {products.data.map((product) => (
            <li key={product.slug}>
              <ProductCard product={product} />
            </li>
          ))}
        </DirectoryCardGrid>
      )}

      <Pagination
        basePath="/products"
        currentPage={products.meta.current_page}
        lastPage={products.meta.last_page}
        total={products.meta.total}
        searchParams={filterParams}
      />
    </PageShell>
  );
}
