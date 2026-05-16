import {
  FilterField,
  FilterForm,
  SEARCH_QUERY_HINT,
  filterInputClassName,
} from "@/components/directory/filter-form";
import { FilterStatsRow } from "@/components/directory/filter-stats-row";
import type { DepartmentListItem, FacilityType } from "@/lib/api/types";
import { t, tFormat } from "@/i18n/t";

const FACILITY_TYPES: {
  value: FacilityType;
  labelKey: "facilities.typeClinic" | "facilities.typeHospital" | "facilities.typeLaboratory";
}[] = [
  { value: "clinic", labelKey: "facilities.typeClinic" },
  { value: "hospital", labelKey: "facilities.typeHospital" },
  { value: "laboratory", labelKey: "facilities.typeLaboratory" },
];

export function FacilitiesFilterBar({
  values,
  resultsTotal,
  departments,
}: {
  values: {
    type?: string;
    city?: string;
    q?: string;
    has_emergency?: string;
    department?: string;
  };
  resultsTotal: number;
  departments: DepartmentListItem[];
}) {
  const hasFilters = Boolean(
    values.type || values.city || values.q || values.has_emergency || values.department,
  );
  const emergencyChecked = values.has_emergency === "1";

  return (
    <FilterForm searchHint={SEARCH_QUERY_HINT} fieldsClassName="xl:grid-cols-3 2xl:grid-cols-6">
      <FilterStatsRow
        label={tFormat("facilities.resultsCount", { count: String(resultsTotal) })}
        clearHref={hasFilters ? "/facilities" : undefined}
        className="2xl:col-span-6"
      />
      <FilterField label={t("filters.type")}>
        <select name="type" defaultValue={values.type ?? ""} className={filterInputClassName}>
          <option value="">{t("facilities.allTypes")}</option>
          {FACILITY_TYPES.map((type) => (
            <option key={type.value} value={type.value}>
              {t(type.labelKey)}
            </option>
          ))}
        </select>
      </FilterField>
      <FilterField label={t("filters.department")}>
        <select name="department" defaultValue={values.department ?? ""} className={filterInputClassName}>
          <option value="">{t("facilities.allDepartments")}</option>
          {departments.map((department) => (
            <option key={department.slug} value={department.slug}>
              {department.name}
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
      <FilterField label={t("facilities.emergencyFilter")}>
        <label className="flex h-11 items-center gap-2 rounded-xl border border-border bg-background px-3 text-sm">
          <input
            type="checkbox"
            name="has_emergency"
            value="1"
            defaultChecked={emergencyChecked}
            className="size-4 rounded border-border text-primary focus:ring-primary"
          />
          <span>{t("facilities.emergencyAvailable")}</span>
        </label>
      </FilterField>
    </FilterForm>
  );
}
