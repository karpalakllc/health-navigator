import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import type { ProductOffer } from "@/lib/api/types";
import { t } from "@/i18n/t";

export function ProductOffersTable({ offers }: { offers: ProductOffer[] }) {
  if (offers.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">{t("products.noOffers")}</p>
    );
  }

  const minPrice = Math.min(...offers.map((offer) => offer.price));

  return (
    <div className="overflow-x-auto rounded-2xl border border-border">
      <table className="w-full min-w-[320px] text-left text-sm">
        <thead>
          <tr className="border-b border-border bg-muted/40">
            <th className="px-4 py-3 font-semibold text-foreground">
              {t("products.pharmacyColumn")}
            </th>
            <th className="px-4 py-3 font-semibold text-foreground">
              {t("products.priceColumn")}
            </th>
          </tr>
        </thead>
        <tbody>
          {offers.map((offer) => {
            const isBest = offer.price === minPrice;

            return (
              <tr
                key={`${offer.pharmacy.slug}-${offer.price}`}
                className={isBest ? "bg-primary/5" : "border-t border-border"}
              >
                <td className="px-4 py-3">
                  <Link
                    href={`/pharmacies/${offer.pharmacy.slug}`}
                    className="font-medium text-foreground hover:text-primary"
                  >
                    {offer.pharmacy.name}
                  </Link>
                  {offer.pharmacy.city ? (
                    <p className="text-xs text-muted-foreground">
                      {offer.pharmacy.city}
                    </p>
                  ) : null}
                </td>
                <td className="px-4 py-3">
                  <span className="font-semibold tabular-nums">
                    {offer.price.toLocaleString("mk-MK")} {offer.currency}
                  </span>
                  {isBest ? (
                    <Badge variant="primary" className="ml-2 align-middle">
                      {t("products.bestDeal")}
                    </Badge>
                  ) : null}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
