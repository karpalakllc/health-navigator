import {
  FilterField,
  FilterForm,
  SEARCH_QUERY_HINT,
  filterInputClassName,
} from "@/components/directory/filter-form";
import { FilterStatsRow } from "@/components/directory/filter-stats-row";
import { t, tFormat } from "@/i18n/t";

export function PharmaciesFilterBar({
  values,
  resultsTotal,
}: {
  values: { city?: string; q?: string };
  resultsTotal: number;
}) {
  const hasFilters = Boolean(values.city || values.q);

  return (
    <FilterForm searchHint={SEARCH_QUERY_HINT} fieldsClassName="xl:grid-cols-2">
      <FilterStatsRow
        label={tFormat("pharmacies.resultsCount", { count: String(resultsTotal) })}
        clearHref={hasFilters ? "/pharmacies" : undefined}
      />
      <FilterField label={t("filters.city")}>
        <input
          name="city"
          defaultValue={values.city ?? ""}
          className={filterInputClassName}
          autoComplete="address-level2"
        />
      </FilterField>
      <FilterField label={t("search.nameLabel")}>
        <input name="q" defaultValue={values.q ?? ""} className={filterInputClassName} />
      </FilterField>
    </FilterForm>
  );
}
