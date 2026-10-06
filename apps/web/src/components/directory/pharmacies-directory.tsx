"use client";

import type { ReactNode } from "react";
import { TextFilter } from "@/components/directory/filter-controls";
import {
  DirectoryListView,
  type ActiveFilter,
} from "@/components/directory/directory-list-view";
import { t } from "@/i18n/t";

export type PharmaciesFilterValues = { q: string; city: string };

/** The pharmacies list: search strip, filter sheet / rail, results. */
export function PharmaciesDirectory({
  applied,
  total,
  children,
}: {
  applied: PharmaciesFilterValues;
  total: number;
  children: ReactNode;
}) {
  const activeFilters: ActiveFilter[] = [
    applied.q ? { name: "q", label: `„${applied.q}“` } : null,
    applied.city ? { name: "city", label: applied.city } : null,
  ].filter((filter): filter is ActiveFilter => filter !== null);

  return (
    <DirectoryListView
      basePath="/pharmacies"
      title={t("pharmacies.title")}
      countKey="pharmacies.resultsCount"
      total={total}
      applied={applied}
      search={{
        name: "q",
        label: t("search.nameLabel"),
        placeholder: t("pharmacies.namePlaceholder"),
      }}
      activeFilters={activeFilters}
      aboutData={[
        t("pharmacies.trustPricesInfo"),
        t("pharmacies.trustModeratedInfo"),
        t("home.trustLocal"),
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
              placeholder={t("pharmacies.namePlaceholder")}
              onChange={(value) => set("q", value, { debounce: true })}
            />
          ) : null}
          <TextFilter
            label={t("filters.city")}
            name="city"
            icon="map-pin"
            value={values.city}
            placeholder={t("pharmacies.cityPlaceholder")}
            autoComplete="address-level2"
            onChange={(value) => set("city", value, { debounce: true })}
          />
        </>
      )}
    >
      {children}
    </DirectoryListView>
  );
}
