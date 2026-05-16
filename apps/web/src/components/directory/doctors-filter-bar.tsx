import {
  FilterField,
  FilterForm,
  SEARCH_QUERY_HINT,
  filterInputClassName,
} from "@/components/directory/filter-form";
import { FilterStatsRow } from "@/components/directory/filter-stats-row";
import type { Specialty } from "@/lib/api/types";
import { t, tFormat } from "@/i18n/t";

export type DoctorsFilterValues = {
  specialty?: string;
  city?: string;
  q?: string;
  sort?: string;
};

export function DoctorsFilterBar({
  specialties,
  values,
  resultsTotal,
}: {
  specialties: Specialty[];
  values: DoctorsFilterValues;
  resultsTotal: number;
}) {
  const hasFilters = Boolean(values.specialty || values.city || values.q || values.sort === "rating");

  return (
    <FilterForm searchHint={SEARCH_QUERY_HINT} fieldsClassName="xl:grid-cols-4">
      <FilterStatsRow
        label={tFormat("doctors.resultsCount", { count: String(resultsTotal) })}
        clearHref={hasFilters ? "/doctors" : undefined}
      />
      <FilterField label={t("filters.specialty")}>
        <select
          name="specialty"
          defaultValue={values.specialty ?? ""}
          className={filterInputClassName}
        >
          <option value="">{t("doctors.allSpecialties")}</option>
          {specialties.map((specialty) => (
            <option key={specialty.slug} value={specialty.slug}>
              {specialty.name}
              {specialty.doctors_count > 0 ? ` (${specialty.doctors_count})` : ""}
            </option>
          ))}
        </select>
      </FilterField>
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
      <FilterField label={t("filters.sortBy")}>
        <select name="sort" defaultValue={values.sort ?? "name"} className={filterInputClassName}>
          <option value="name">{t("filters.sortByName")}</option>
          <option value="rating">{t("filters.sortByRating")}</option>
        </select>
      </FilterField>
    </FilterForm>
  );
}
