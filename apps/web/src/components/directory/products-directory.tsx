"use client";

import type { ReactNode } from "react";
import { TextFilter } from "@/components/directory/filter-controls";
import {
  DirectoryListView,
  type ActiveFilter,
} from "@/components/directory/directory-list-view";
import { t } from "@/i18n/t";

export type ProductsFilterValues = {
  q: string;
  category: string;
  pharmacy: string;
};

/** The product catalogue: search strip, filter sheet / rail, results. */
export function ProductsDirectory({
  applied,
  total,
  children,
}: {
  applied: ProductsFilterValues;
  total: number;
  children: ReactNode;
}) {
  const activeFilters: ActiveFilter[] = [
    applied.q ? { name: "q", label: `„${applied.q}“` } : null,
    applied.category ? { name: "category", label: applied.category } : null,
    applied.pharmacy
      ? {
          name: "pharmacy",
          label: `${t("filters.pharmacy")}: ${applied.pharmacy}`,
        }
      : null,
  ].filter((filter): filter is ActiveFilter => filter !== null);

  return (
    <DirectoryListView
      basePath="/products"
      title={t("products.title")}
      countKey="products.resultsCount"
      total={total}
      applied={applied}
      search={{
        name: "q",
        label: t("search.nameLabel"),
        placeholder: t("products.searchPlaceholder"),
      }}
      activeFilters={activeFilters}
      aboutData={[
        t("catalog.priceDisclaimer"),
        t("pharmacies.trustPricesInfo"),
      ]}
      renderFields={({ values, set }, place) => (
        <>
          {place === "rail" ? (
            <TextFilter
              label={t("search.nameLabel")}
              hint={t("search.queryHint")}
              name="q"
              icon="search"
              value={values.q}
              placeholder={t("products.searchPlaceholder")}
              onChange={(value) => set("q", value, { debounce: true })}
            />
          ) : null}
          <TextFilter
            label={t("filters.category")}
            name="category"
            icon="package"
            value={values.category}
            onChange={(value) => set("category", value, { debounce: true })}
          />
          <TextFilter
            label={t("filters.pharmacy")}
            name="pharmacy"
            icon="building"
            value={values.pharmacy}
            onChange={(value) => set("pharmacy", value, { debounce: true })}
          />
        </>
      )}
    >
      {children}
    </DirectoryListView>
  );
}
