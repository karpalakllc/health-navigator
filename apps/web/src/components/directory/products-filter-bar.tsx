import {
  FilterField,
  FilterForm,
  SEARCH_QUERY_HINT,
  filterInputClassName,
} from "@/components/directory/filter-form";
import { FilterStatsRow } from "@/components/directory/filter-stats-row";
import { t, tFormat } from "@/i18n/t";

export function ProductsFilterBar({
  values,
  resultsTotal,
}: {
  values: { q?: string; category?: string; pharmacy?: string };
  resultsTotal: number;
}) {
  const hasFilters = Boolean(values.q || values.category || values.pharmacy);

  return (
    <FilterForm searchHint={SEARCH_QUERY_HINT} fieldsClassName="xl:grid-cols-3">
      <FilterStatsRow
        label={tFormat("products.resultsCount", {
          count: String(resultsTotal),
        })}
        clearHref={hasFilters ? "/products" : undefined}
      />
      <FilterField label={t("search.nameLabel")}>
        <input
          name="q"
          defaultValue={values.q ?? ""}
          className={filterInputClassName}
        />
      </FilterField>
      <FilterField label={t("filters.category")}>
        <input
          name="category"
          defaultValue={values.category ?? ""}
          className={filterInputClassName}
        />
      </FilterField>
      <FilterField label={t("filters.pharmacy")}>
        <input
          name="pharmacy"
          defaultValue={values.pharmacy ?? ""}
          className={filterInputClassName}
        />
      </FilterField>
    </FilterForm>
  );
}
