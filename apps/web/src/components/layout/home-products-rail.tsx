import Link from "next/link";
import { HomeShowcaseProductCard } from "@/components/catalog/home-showcase-product-card";
import type { ProductListItem } from "@/lib/api/types";
import { t } from "@/i18n/t";

export function HomeProductsRail({ products }: { products: ProductListItem[] }) {
  if (products.length === 0) {
    return null;
  }

  return (
    <section className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="flex items-start gap-3">
          <span className="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <TrendGlyph className="h-5 w-5" aria-hidden />
          </span>
          <div>
            <h2 className="text-xl font-semibold tracking-tight">{t("home.productSpotlightTitle")}</h2>
            <p className="mt-1 max-w-xl text-sm text-muted-foreground">{t("home.productSpotlightDescription")}</p>
          </div>
        </div>
        <Link href="/products" className="shrink-0 text-sm font-semibold text-primary hover:underline">
          {t("home.productSpotlightViewAll")} →
        </Link>
      </div>

      <div className="relative -mx-4 sm:mx-0">
        <div className="flex gap-4 overflow-x-auto px-4 pb-1 pt-1 [scrollbar-width:none] sm:px-0 [&::-webkit-scrollbar]:hidden snap-x snap-mandatory">
          {products.map((product, i) => (
            <HomeShowcaseProductCard key={product.slug} product={product} index={i} />
          ))}
        </div>
      </div>
    </section>
  );
}

function TrendGlyph({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M4 14l4-4 4 4 8-8" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M14 6h6v6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}
