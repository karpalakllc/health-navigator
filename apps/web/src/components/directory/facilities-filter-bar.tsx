"use client";

import Link from "next/link";
import { EmergencyFilterToggle } from "@/components/directory/emergency-filter-toggle";
import { FilterField } from "@/components/directory/filter-form";
import { FilterInputWrap } from "@/components/directory/filter-input-wrap";
import type { DepartmentListItem, FacilityType } from "@/lib/api/types";
import { t } from "@/i18n/t";

const FACILITY_TYPES: {
  value: FacilityType;
  labelKey: "facilities.typeClinic" | "facilities.typeHospital" | "facilities.typeLaboratory";
}[] = [
  { value: "clinic", labelKey: "facilities.typeClinic" },
  { value: "hospital", labelKey: "facilities.typeHospital" },
  { value: "laboratory", labelKey: "facilities.typeLaboratory" },
];

export type FacilitiesFilterValues = {
  type?: string;
  city?: string;
  q?: string;
  has_emergency?: string;
  department?: string;
};

export function FacilitiesFilterBar({
  values,
  departments,
}: {
  values: FacilitiesFilterValues;
  departments: DepartmentListItem[];
}) {
  const hasFilters = Boolean(
    values.type || values.city || values.q || values.has_emergency || values.department,
  );
  const emergencyChecked = values.has_emergency === "1";
  const activeType = FACILITY_TYPES.find((type) => type.value === values.type);
  const activeDepartment = departments.find((d) => d.slug === values.department);

  return (
    <form action="/facilities" method="get" className="space-y-0">
      <div className="filters-card filters-card-nested p-5">
        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 className="text-lg font-extrabold tracking-tight text-foreground">
              {t("facilities.filtersTitle")}
            </h2>
            <p className="mt-1.5 text-sm text-muted-foreground">{t("facilities.filtersDescription")}</p>
          </div>
          {hasFilters ? (
            <Link
              href="/facilities"
              className="inline-flex min-h-11 shrink-0 items-center justify-center gap-2.5 rounded-full border border-border bg-white px-4 text-sm font-bold text-[#55616d] transition hover:bg-[#f7f9fa]"
            >
              <ResetIcon className="h-4 w-4" aria-hidden />
              {t("facilities.resetFilters")}
            </Link>
          ) : null}
        </div>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <FilterField label={t("filters.type")}>
            <FilterInputWrap icon={<BuildingIcon className="h-5 w-5" aria-hidden />}>
              <select name="type" defaultValue={values.type ?? ""}>
                <option value="">{t("facilities.allTypes")}</option>
                {FACILITY_TYPES.map((type) => (
                  <option key={type.value} value={type.value}>
                    {t(type.labelKey)}
                  </option>
                ))}
              </select>
            </FilterInputWrap>
          </FilterField>

          <FilterField label={t("filters.department")}>
            <FilterInputWrap icon={<LayersIcon className="h-5 w-5" aria-hidden />}>
              <select name="department" defaultValue={values.department ?? ""}>
                <option value="">{t("facilities.allDepartments")}</option>
                {departments.map((department) => (
                  <option key={department.slug} value={department.slug}>
                    {department.name}
                  </option>
                ))}
              </select>
            </FilterInputWrap>
          </FilterField>

          <FilterField label={t("filters.city")}>
            <FilterInputWrap icon={<MapPinIcon className="h-5 w-5" aria-hidden />}>
              <input
                name="city"
                defaultValue={values.city ?? ""}
                placeholder={t("facilities.cityPlaceholder")}
                autoComplete="address-level2"
              />
            </FilterInputWrap>
          </FilterField>

          <FilterField label={t("search.nameLabel")} hint={t("search.queryHint")}>
            <FilterInputWrap icon={<SearchIcon className="h-5 w-5" aria-hidden />}>
              <input
                name="q"
                defaultValue={values.q ?? ""}
                placeholder={t("facilities.namePlaceholder")}
              />
            </FilterInputWrap>
          </FilterField>
        </div>

        <div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex flex-wrap items-center gap-2">
            <EmergencyFilterToggle defaultChecked={emergencyChecked} />
            {activeType ? (
              <span className="inline-flex min-h-[34px] items-center rounded-full bg-[#fff1f1] px-3 text-xs font-bold text-primary">
                {t(activeType.labelKey)}
              </span>
            ) : null}
            {activeDepartment ? (
              <span className="inline-flex min-h-[34px] items-center rounded-full bg-[#eef8f8] px-3 text-xs font-bold text-accent">
                {activeDepartment.name}
              </span>
            ) : null}
            {values.city ? (
              <span className="inline-flex min-h-[34px] items-center rounded-full bg-[#f1f4f6] px-3 text-xs font-bold text-[#5d6771]">
                {values.city}
              </span>
            ) : null}
            {values.q ? (
              <span className="inline-flex min-h-[34px] items-center rounded-full bg-[#f1f4f6] px-3 text-xs font-bold text-[#5d6771]">
                {values.q}
              </span>
            ) : null}
            {emergencyChecked ? (
              <span className="inline-flex min-h-[34px] items-center rounded-full bg-amber-500/15 px-3 text-xs font-bold text-amber-800">
                {t("facilities.emergencyBadge")}
              </span>
            ) : null}
          </div>
          <button
            type="submit"
            className="btn-gradient-primary inline-flex min-h-[54px] shrink-0 items-center justify-center rounded-[1.125rem] px-6 text-sm font-extrabold text-white transition hover:brightness-105 sm:min-w-[140px]"
          >
            {t("common.search")}
          </button>
        </div>
      </div>
    </form>
  );
}

function ResetIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M3 12a9 9 0 019-9 9.75 9.75 0 016.74 2.74L21 8" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M21 3v5h-5M21 12a9 9 0 01-9 9 9.75 9.75 0 01-6.74-2.74L3 16" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function BuildingIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function LayersIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function MapPinIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M12 21s7-4.35 7-11a7 7 0 10-14 0c0 6.65 7 11 7 11z" strokeLinecap="round" />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  );
}

function SearchIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <circle cx="11" cy="11" r="7" />
      <path d="M20 20l-3.5-3.5" strokeLinecap="round" />
    </svg>
  );
}
