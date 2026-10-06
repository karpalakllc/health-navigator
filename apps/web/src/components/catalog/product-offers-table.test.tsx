import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ProductOffersTable } from "@/components/catalog/product-offers-table";
import type { ProductOffer } from "@/lib/api/types";
import { t } from "@/i18n/t";

function offer(name: string, price: number): ProductOffer {
  return {
    price,
    currency: "ден.",
    price_updated_at: null,
    pharmacy: { slug: name.toLowerCase(), name, city: "Битола" },
  };
}

describe("ProductOffersTable", () => {
  it("stays a table with row headers even when the rows stack on a phone", () => {
    render(
      <ProductOffersTable
        offers={[offer("Фарма Медика", 320), offer("Зегин", 290)]}
      />,
    );

    const table = screen.getByRole("table");
    expect(
      within(table)
        .getAllByRole("columnheader")
        .map((th) => th.textContent),
    ).toEqual([t("products.pharmacyColumn"), t("products.priceColumn")]);

    // Cheapest first, the pharmacy is the row header, the best price tagged.
    const [first, second] = within(table).getAllByRole("rowheader");
    expect(first).toHaveTextContent("Зегин");
    expect(second).toHaveTextContent("Фарма Медика");
    const bestRow = first.closest("tr")!;
    expect(bestRow).toHaveTextContent(t("products.bestDeal"));
    expect(second.closest("tr")).not.toHaveTextContent(t("products.bestDeal"));
    // The stacking is a phone-only layout; the price never breaks mid-value.
    expect(bestRow.className).toContain("max-sm:flex-col");
    expect(within(bestRow).getByText(/290/)).toHaveClass("whitespace-nowrap");
  });
});
