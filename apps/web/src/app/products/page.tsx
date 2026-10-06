import type { Metadata } from "next";
import { PriceDisclaimer } from "@/components/catalog/price-disclaimer";
import { ProductCard } from "@/components/catalog/product-card";
import { EmptyState } from "@/components/directory/empty-state";
import { Pagination } from "@/components/directory/pagination";
import { ProductsDirectory } from "@/components/directory/products-directory";
import { ResultsGrid } from "@/components/directory/results-grid";
import { ComingSoonShell } from "@/components/layout/coming-soon-shell";
import { fetchProducts } from "@/lib/api/products";
import { fetchPublicSettings } from "@/lib/api/settings";
import { isModuleOn } from "@/lib/api/public-settings";
import { listCanonicalPath, pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import { parseListPage } from "@/lib/api/directory-cache-policy";

type ProductsPageProps = {
  searchParams: Promise<{
    q?: string;
    category?: string;
    pharmacy?: string;
    page?: string;
  }>;
};

export async function generateMetadata({
  searchParams,
}: ProductsPageProps): Promise<Metadata> {
  const settings = await fetchPublicSettings();

  return pageMetadata(t("products.title"), t("products.description"), {
    path: listCanonicalPath("/products", await searchParams),
    noIndex: !settings.public_products,
  });
}

export default async function ProductsPage({
  searchParams,
}: ProductsPageProps) {
  const settings = await fetchPublicSettings();

  if (!isModuleOn(settings, "public_products")) {
    return (
      <ComingSoonShell
        title={t("products.title")}
        description={t("products.description")}
      />
    );
  }

  const params = await searchParams;
  const page = parseListPage(params.page);

  const products = await fetchProducts({
    q: params.q,
    category: params.category,
    pharmacy: params.pharmacy,
    page,
  });

  const applied = {
    q: params.q ?? "",
    category: params.category ?? "",
    pharmacy: params.pharmacy ?? "",
  };
  const hasFilters = Object.values(applied).some(Boolean);

  return (
    <ProductsDirectory applied={applied} total={products.meta.total}>
      <PriceDisclaimer />

      {products.data.length === 0 ? (
        <EmptyState
          title={t("products.empty")}
          description={!hasFilters ? t("common.demoDataHint") : undefined}
          clearHref={hasFilters ? "/products" : undefined}
          clearLabel={hasFilters ? t("common.clearFilters") : undefined}
        />
      ) : (
        <ResultsGrid>
          {products.data.map((product) => (
            <li key={product.slug}>
              <ProductCard product={product} />
            </li>
          ))}
        </ResultsGrid>
      )}

      <Pagination
        basePath="/products"
        currentPage={products.meta.current_page}
        lastPage={products.meta.last_page}
        total={products.meta.total}
        searchParams={applied}
      />
    </ProductsDirectory>
  );
}
