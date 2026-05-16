import Link from "next/link";
import type { ProductListItem } from "@/lib/api/types";
import { Card } from "@/components/ui/card";
import { cn } from "@/lib/cn";
import { t, tFormat } from "@/i18n/t";

const GRADIENTS = [
  "from-[hsl(213_80%_52%)] via-[hsl(199_72%_48%)] to-[hsl(160_52%_42%)]",
  "from-[hsl(265_70%_52%)] via-[hsl(213_75%_55%)] to-[hsl(38_90%_52%)]",
  "from-[hsl(350_72%_52%)] via-[hsl(280_65%_48%)] to-[hsl(213_80%_50%)]",
  "from-[hsl(160_55%_38%)] via-[hsl(190_70%_42%)] to-[hsl(213_82%_48%)]",
];

export function HomeShowcaseProductCard({
  product,
  index,
}: {
  product: ProductListItem;
  index: number;
}) {
  const gradient = GRADIENTS[index % GRADIENTS.length];

  return (
    <Link href={`/products/${product.slug}`} className={cn("block h-full min-w-[260px] max-w-[280px] shrink-0 snap-start")}>
      <Card className="card-hover flex h-full flex-col overflow-hidden p-0 shadow-[0_16px_44px_-28px_rgb(15_23_42/0.45)]">
        <div
          className={cn(
            "relative flex h-[120px] items-center justify-center bg-gradient-to-br",
            gradient,
          )}
        >
          <span className="rounded-full bg-white/20 p-3 backdrop-blur-[2px]" aria-hidden>
            <PillGlyph className="h-10 w-10 text-white" />
          </span>
        </div>
        <div className="flex flex-1 flex-col gap-2 p-4">
          <h3 className="line-clamp-2 font-semibold leading-snug text-foreground">{product.name}</h3>
          {product.category ? (
            <p className="line-clamp-2 text-xs text-muted-foreground">{product.category}</p>
          ) : null}
          <div className="mt-auto flex flex-wrap items-end justify-between gap-2 pt-2">
            <div>
              {product.from_price != null ? (
                <p className="text-lg font-bold text-primary">
                  {product.from_price.toLocaleString("mk-MK")}{" "}
                  <span className="text-xs font-semibold uppercase text-muted-foreground">MKD</span>
                </p>
              ) : (
                <span className="text-sm text-muted-foreground">{t("products.noPriceListed")}</span>
              )}
              {product.offer_count > 0 ? (
                <p className="text-xs text-muted-foreground">
                  {tFormat("products.offerCount", { count: String(product.offer_count) })}
                </p>
              ) : null}
            </div>
            <span className="rounded-full bg-primary px-3 py-1 text-xs font-semibold text-primary-foreground shadow-sm">
              {t("home.productSpotlightCta")}
            </span>
          </div>
        </div>
      </Card>
    </Link>
  );
}

function PillGlyph({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 48 48" fill="none" aria-hidden>
      <rect x="12" y="20" width="24" height="10" rx="5" stroke="currentColor" strokeWidth="2.25" />
      <path d="M18 25h12" stroke="currentColor" strokeWidth="2.25" strokeLinecap="round" />
    </svg>
  );
}
