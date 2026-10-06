"use client";

import type { ReactNode } from "react";
import {
  ChoiceChips,
  FilterGroup,
  SwitchRow,
  TextFilter,
} from "@/components/directory/filter-controls";
import {
  DirectoryListView,
  type ActiveFilter,
} from "@/components/directory/directory-list-view";
import type { DepartmentListItem } from "@/lib/api/types";
import { facilityTypeLabel } from "@/lib/facility-labels";
import { t } from "@/i18n/t";

export type FacilitiesFilterValues = {
  q: string;
  type: string;
  department: string;
  city: string;
  has_emergency: string;
};

const TYPES = ["clinic", "hospital", "laboratory"] as const;

/** The facilities list: search strip, filter sheet / rail, results. */
export function FacilitiesDirectory({
  departments,
  applied,
  total,
  children,
}: {
  departments: DepartmentListItem[];
  applied: FacilitiesFilterValues;
  total: number;
  children: ReactNode;
}) {
  const departmentName = departments.find(
    (d) => d.slug === applied.department,
  )?.name;

  const activeFilters: ActiveFilter[] = [
    applied.q ? { name: "q", label: `„${applied.q}“` } : null,
    applied.type
      ? { name: "type", label: facilityTypeLabel(applied.type) }
      : null,
    applied.department
      ? { name: "department", label: departmentName ?? applied.department }
      : null,
    applied.city ? { name: "city", label: applied.city } : null,
    applied.has_emergency
      ? { name: "has_emergency", label: t("facilities.emergencyFilter") }
      : null,
  ].filter((filter): filter is ActiveFilter => filter !== null);

  return (
    <DirectoryListView
      basePath="/facilities"
      title={t("facilities.title")}
      countKey="facilities.resultsCount"
      total={total}
      applied={applied}
      search={{
        name: "q",
        label: t("facilities.queryLabel"),
        placeholder: t("facilities.namePlaceholder"),
      }}
      activeFilters={activeFilters}
      quickChips={[
        ...TYPES.map((type) => ({
          name: "type",
          value: type,
          label: facilityTypeLabel(type),
        })),
        {
          name: "has_emergency",
          value: "1",
          label: t("facilities.emergencyFilter"),
        },
      ]}
      aboutData={[
        t("facilities.trustRatingsInfo"),
        t("facilities.trustModeratedInfo"),
        t("home.trustLocal"),
      ]}
      renderFields={({ values, set }, place) => (
        <>
          {place === "rail" ? (
            <TextFilter
              label={t("facilities.queryLabel")}
              hint={t("search.queryHint")}
              name="q"
              icon="search"
              value={values.q}
              placeholder={t("facilities.namePlaceholder")}
              onChange={(value) => set("q", value, { debounce: true })}
            />
          ) : null}
          <ChoiceChips
            legend={t("filters.type")}
            name="type"
            value={values.type}
            allLabel={t("facilities.allTypes")}
            options={TYPES.map((type) => ({
              value: type,
              label: facilityTypeLabel(type),
            }))}
            onChange={(value) => set("type", value)}
          />
          {departments.length > 0 ? (
            <ChoiceChips
              legend={t("filters.department")}
              name="department"
              value={values.department}
              allLabel={t("facilities.allDepartments")}
              options={departments.map((d) => ({
                value: d.slug,
                label: d.name,
              }))}
              visible={6}
              onChange={(value) => set("department", value)}
            />
          ) : null}
          <TextFilter
            label={t("filters.city")}
            name="city"
            icon="map-pin"
            value={values.city}
            placeholder={t("facilities.cityPlaceholder")}
            autoComplete="address-level2"
            onChange={(value) => set("city", value, { debounce: true })}
          />
          <FilterGroup legend={t("directory.availability")}>
            <SwitchRow
              name="has_emergency"
              label={t("facilities.emergencyFilter")}
              checked={values.has_emergency === "1"}
              onChange={(checked) => set("has_emergency", checked ? "1" : "")}
            />
          </FilterGroup>
        </>
      )}
    >
      {children}
    </DirectoryListView>
  );
}
