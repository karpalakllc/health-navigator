import Link from "next/link";
import { Icon } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import type { ProductListItem } from "@/lib/api/types";
import { t, tFormat, tCount } from "@/i18n/t";

/** A catalogue product: the whole card links to its price comparison. */
export function ProductCard({ product }: { product: ProductListItem }) {
  return (
    <Link
      href={`/products/${product.slug}`}
      className="card hover-lift flex h-full flex-col gap-4 p-5 text-ink no-underline"
    >
      <div className="flex items-start gap-3">
        <span className="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-sand">
          <Icon name="pill" size={22} />
        </span>
        <div className="min-w-0 flex-1">
          <h2 className="type-h3 text-ink">
            <span className="link-grow">{product.name}</span>
          </h2>
          {product.category ? (
            <p className="type-meta mt-0.5 text-ink-2">{product.category}</p>
          ) : null}
        </div>
      </div>
      <div className="mt-auto flex flex-wrap items-center justify-between gap-2">
        {product.from_price != null ? (
          <p className="type-h3 tabular-nums text-ink">
            {tFormat("products.priceFrom", {
              price: product.from_price.toLocaleString("mk-MK"),
            })}
          </p>
        ) : (
          <Tag tone="outline">{t("products.noPriceListed")}</Tag>
        )}
        {product.offer_count > 0 ? (
          <span className="flex items-center gap-1.5 type-meta text-ink-2">
            <Icon name="building" size={18} />
            {tCount("products.offerCount", product.offer_count)}
          </span>
        ) : null}
      </div>
    </Link>
  );
}
