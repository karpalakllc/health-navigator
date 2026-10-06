import Link from "next/link";
import { formatMkDate } from "@/lib/mk-date";
import { Tag } from "@/components/ui/tag";
import type { ProductOffer } from "@/lib/api/types";
import { cn } from "@/lib/cn";
import { t, tFormat } from "@/i18n/t";

/** Prices per pharmacy, cheapest first, the lowest marked „Најдобра цена“. */
export function ProductOffersTable({ offers }: { offers: ProductOffer[] }) {
  if (offers.length === 0) {
    return <p className="type-body text-ink-2">{t("products.noOffers")}</p>;
  }

  const minPrice = Math.min(...offers.map((offer) => offer.price));
  const sorted = [...offers].sort((a, b) => a.price - b.price);

  return (
    <table className="w-full border-collapse type-body">
      <thead>
        <tr className="type-meta text-ink-2">
          <th scope="col" className="px-3 pb-2 text-left font-normal">
            {t("products.pharmacyColumn")}
          </th>
          <th scope="col" className="px-3 pb-2 text-right font-normal">
            {t("products.priceColumn")}
          </th>
        </tr>
      </thead>
      <tbody>
        {sorted.map((offer) => {
          const isBest = offer.price === minPrice;
          const updated = formatMkDate(offer.price_updated_at);

          return (
            <tr
              key={`${offer.pharmacy.slug}-${offer.price}`}
              className={cn(
                "[&+tr>*]:border-t [&+tr>*]:border-line",
                isBest && "bg-care-tint [&+tr>*]:border-transparent",
              )}
            >
              <th
                scope="row"
                className={cn(
                  "px-3 py-3 text-left align-top font-normal",
                  isBest && "rounded-l-lg",
                )}
              >
                <Link
                  href={`/pharmacies/${offer.pharmacy.slug}`}
                  className="link-underline font-semibold text-ink"
                >
                  {offer.pharmacy.name}
                </Link>
                <span className="mt-0.5 block type-meta text-ink-2">
                  {[
                    offer.pharmacy.city,
                    updated
                      ? tFormat("products.priceUpdated", { date: updated })
                      : null,
                  ]
                    .filter(Boolean)
                    .join(" · ")}
                </span>
              </th>
              <td
                className={cn(
                  "px-3 py-3 text-right align-top",
                  isBest && "rounded-r-lg",
                )}
              >
                <span className="block whitespace-nowrap font-semibold tabular-nums text-ink">
                  {offer.price.toLocaleString("mk-MK")} {offer.currency}
                </span>
                {isBest ? (
                  <Tag tone="white" icon="check" className="mt-1 text-care">
                    {t("products.bestDeal")}
                  </Tag>
                ) : null}
              </td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}
