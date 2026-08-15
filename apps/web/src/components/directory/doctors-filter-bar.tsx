"use client";

import Link from "next/link";
import { FilterField } from "@/components/directory/filter-form";
import { FilterInputWrap } from "@/components/directory/filter-input-wrap";
import type { Specialty } from "@/lib/api/types";
import { t } from "@/i18n/t";

export type DoctorsFilterValues = {
  specialty?: string;
  city?: string;
  q?: string;
  sort?: string;
};

export function DoctorsFilterBar({
  specialties,
  values,
}: {
  specialties: Specialty[];
  values: DoctorsFilterValues;
}) {
  const hasFilters = Boolean(
    values.specialty || values.city || values.q || values.sort === "rating",
  );
  const activeSpecialty = specialties.find((s) => s.slug === values.specialty);

  return (
    <form action="/doctors" method="get" className="space-y-0">
      <div className="filters-card filters-card-nested p-5">
        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 className="text-lg font-extrabold tracking-tight text-foreground">
              {t("doctors.filtersTitle")}
            </h2>
            <p className="mt-1.5 text-sm text-muted-foreground">
              {t("doctors.filtersDescription")}
            </p>
          </div>
          {hasFilters ? (
            <Link
              href="/doctors"
              className="inline-flex min-h-11 shrink-0 items-center justify-center gap-2.5 rounded-full border border-border bg-white px-4 text-sm font-bold text-[#55616d] transition hover:bg-[#f7f9fa]"
            >
              <ResetIcon className="h-4 w-4" aria-hidden />
              {t("doctors.resetFilters")}
            </Link>
          ) : null}
        </div>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <FilterField label={t("filters.specialty")}>
            <FilterInputWrap
              icon={<BriefcaseIcon className="h-5 w-5" aria-hidden />}
            >
              <select name="specialty" defaultValue={values.specialty ?? ""}>
                <option value="">{t("doctors.allSpecialties")}</option>
                {specialties.map((specialty) => (
                  <option key={specialty.slug} value={specialty.slug}>
                    {specialty.name}
                    {specialty.doctors_count > 0
                      ? ` (${specialty.doctors_count})`
                      : ""}
                  </option>
                ))}
              </select>
            </FilterInputWrap>
          </FilterField>

          <FilterField label={t("filters.city")}>
            <FilterInputWrap
              icon={<MapPinIcon className="h-5 w-5" aria-hidden />}
            >
              <input
                name="city"
                defaultValue={values.city ?? ""}
                placeholder={t("doctors.cityPlaceholder")}
                autoComplete="address-level2"
              />
            </FilterInputWrap>
          </FilterField>

          <FilterField
            label={t("search.nameLabel")}
            hint={t("search.queryHint")}
          >
            <FilterInputWrap
              icon={<SearchIcon className="h-5 w-5" aria-hidden />}
            >
              <input
                name="q"
                defaultValue={values.q ?? ""}
                placeholder={t("doctors.namePlaceholder")}
              />
            </FilterInputWrap>
          </FilterField>

          <FilterField label={t("filters.sortBy")}>
            <FilterInputWrap
              icon={<SortIcon className="h-5 w-5" aria-hidden />}
            >
              <select name="sort" defaultValue={values.sort ?? "name"}>
                <option value="name">{t("filters.sortByName")}</option>
                <option value="rating">{t("filters.sortByRating")}</option>
              </select>
            </FilterInputWrap>
          </FilterField>
        </div>

        <div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex flex-wrap gap-2">
            {activeSpecialty ? (
              <span className="inline-flex min-h-[34px] items-center rounded-full bg-[#fff1f1] px-3 text-xs font-bold text-primary">
                {activeSpecialty.name}
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
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
      <path
        d="M3 12a9 9 0 019-9 9.75 9.75 0 016.74 2.74L21 8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path
        d="M21 3v5h-5M21 12a9 9 0 01-9 9 9.75 9.75 0 01-6.74-2.74L3 16"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function BriefcaseIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
      <path
        d="M10 6V4h4v2M4 10h16v10H4V10z"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function MapPinIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
      <path
        d="M12 21s7-4.35 7-11a7 7 0 10-14 0c0 6.65 7 11 7 11z"
        strokeLinecap="round"
      />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  );
}

function SearchIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
      <circle cx="11" cy="11" r="7" />
      <path d="M20 20l-3.5-3.5" strokeLinecap="round" />
    </svg>
  );
}

function SortIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
      <path
        d="M7 16V4M7 4L3 8M7 4l4 4M17 8v12M17 20l4-4M17 20l-4-4"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
