import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import type { ProductListItem } from "@/lib/api/types";
import { t, tFormat } from "@/i18n/t";

export function ProductCard({ product }: { product: ProductListItem }) {
  return (
    <Link href={`/products/${product.slug}`} className="block h-full">
      <Card className="card-hover flex h-full flex-col gap-3 p-5">
        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-500/10 text-lg font-bold text-violet-700">
          {product.name.charAt(0)}
        </div>
        <div>
          <h2 className="font-semibold text-foreground">{product.name}</h2>
          {product.category ? (
            <p className="mt-1 text-sm text-muted-foreground">{product.category}</p>
          ) : null}
        </div>
        <div className="mt-auto flex flex-wrap items-center gap-2">
          {product.from_price != null ? (
            <Badge variant="primary">
              {tFormat("products.priceFrom", {
                price: product.from_price.toLocaleString("mk-MK"),
              })}
            </Badge>
          ) : (
            <Badge variant="outline">{t("products.noPriceListed")}</Badge>
          )}
          {product.offer_count > 0 ? (
            <span className="text-xs text-muted-foreground">
              {tFormat("products.offerCount", { count: String(product.offer_count) })}
            </span>
          ) : null}
        </div>
      </Card>
    </Link>
  );
}
