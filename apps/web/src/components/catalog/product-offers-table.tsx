import Link from "next/link";
import { formatMkDate } from "@/lib/mk-date";
import { Tag } from "@/components/ui/tag";
import type { ProductOffer } from "@/lib/api/types";
import { cn } from "@/lib/cn";
import { t, tFormat } from "@/i18n/t";

/**
 * Prices per pharmacy, cheapest first, the lowest marked „Најдобра цена“.
 *
 * A two-column table from `sm` up. On a phone each row stacks (pharmacy and
 * its meta on top, the price and tag under it): side by side, the name and
 * the meta wrapped to three and four lines next to the price column. The
 * explicit table roles keep the table semantics once the rows are display
 * flex/block — some browsers drop them for a table not displayed as one.
 */
export function ProductOffersTable({ offers }: { offers: ProductOffer[] }) {
  if (offers.length === 0) {
    return <p className="type-body text-ink-2">{t("products.noOffers")}</p>;
  }

  const minPrice = Math.min(...offers.map((offer) => offer.price));
  const sorted = [...offers].sort((a, b) => a.price - b.price);

  return (
    <table
      role="table"
      className="w-full border-collapse type-body max-sm:block"
    >
      <thead role="rowgroup" className="max-sm:sr-only">
        <tr role="row" className="type-meta text-ink-2">
          <th
            role="columnheader"
            scope="col"
            className="px-3 pb-2 text-left font-normal"
          >
            {t("products.pharmacyColumn")}
          </th>
          <th
            role="columnheader"
            scope="col"
            className="px-3 pb-2 text-right font-normal"
          >
            {t("products.priceColumn")}
          </th>
        </tr>
      </thead>
      <tbody role="rowgroup" className="max-sm:flex max-sm:flex-col">
        {sorted.map((offer) => {
          const isBest = offer.price === minPrice;
          const updated = formatMkDate(offer.price_updated_at);

          return (
            <tr
              key={`${offer.pharmacy.slug}-${offer.price}`}
              role="row"
              className={cn(
                "max-sm:flex max-sm:flex-col max-sm:gap-2 max-sm:rounded-lg max-sm:px-3 max-sm:py-3",
                "sm:[&+tr>*]:border-t sm:[&+tr>*]:border-line",
                "max-sm:[&+tr]:border-t max-sm:[&+tr]:border-line",
                isBest &&
                  "bg-care-tint max-sm:[&+tr]:border-transparent sm:[&+tr>*]:border-transparent",
              )}
            >
              <th
                role="rowheader"
                scope="row"
                className={cn(
                  "text-left align-top font-normal max-sm:block sm:px-3 sm:py-3",
                  isBest && "sm:rounded-l-lg",
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
                role="cell"
                className={cn(
                  "align-top max-sm:flex max-sm:flex-wrap max-sm:items-center max-sm:gap-x-3 max-sm:gap-y-1 sm:px-3 sm:py-3 sm:text-right",
                  isBest && "sm:rounded-r-lg",
                )}
              >
                <span className="block whitespace-nowrap font-semibold tabular-nums text-ink">
                  {offer.price.toLocaleString("mk-MK")} {offer.currency}
                </span>
                {isBest ? (
                  <Tag tone="white" icon="check" className="text-care sm:mt-1">
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
