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
import type { DoctorLanguage } from "@/lib/api/languages";
import type { Specialty } from "@/lib/api/types";
import { t } from "@/i18n/t";

export type DoctorsFilterValues = {
  q: string;
  specialty: string;
  language: string;
  city: string;
  min_reviews: string;
  sort: string;
};

/** The doctors list: search strip, filter sheet / rail, results. */
export function DoctorsDirectory({
  specialties,
  languages = [],
  applied,
  total,
  children,
}: {
  specialties: Specialty[];
  /** GET /languages; the filter is left out when empty. */
  languages?: DoctorLanguage[];
  applied: DoctorsFilterValues;
  total: number;
  children: ReactNode;
}) {
  const specialtyName = specialties.find(
    (s) => s.slug === applied.specialty,
  )?.name;
  const popular = [...specialties]
    .sort((a, b) => b.doctors_count - a.doctors_count)
    .slice(0, 6);

  const activeFilters: ActiveFilter[] = [
    applied.q ? { name: "q", label: `„${applied.q}“` } : null,
    applied.specialty
      ? { name: "specialty", label: specialtyName ?? applied.specialty }
      : null,
    applied.language
      ? {
          name: "language",
          label:
            languages.find((l) => l.slug === applied.language)?.name ??
            applied.language,
        }
      : null,
    applied.city ? { name: "city", label: applied.city } : null,
    applied.min_reviews
      ? { name: "min_reviews", label: t("doctors.hasReviews") }
      : null,
  ].filter((filter): filter is ActiveFilter => filter !== null);

  return (
    <DirectoryListView
      basePath="/doctors"
      title={t("doctors.title")}
      countKey="doctors.resultsCount"
      total={total}
      applied={applied}
      search={{
        name: "q",
        label: t("doctors.queryLabel"),
        // The short form: the long one is cut off in the 390px strip.
        placeholder: t("doctors.queryLabel"),
      }}
      activeFilters={activeFilters}
      quickChips={popular.map((s) => ({
        name: "specialty",
        value: s.slug,
        label: s.name,
      }))}
      sort={{
        name: "sort",
        options: [
          { value: "name", label: t("filters.sortByName") },
          { value: "rating", label: t("filters.sortByRating") },
        ],
      }}
      aboutData={[
        t("doctors.trustRatingsInfo"),
        t("doctors.trustModeratedInfo"),
        t("home.trustLocal"),
      ]}
      renderFields={({ values, set }, place) => (
        <>
          {place === "rail" ? (
            <TextFilter
              label={t("doctors.queryLabel")}
              hint={t("search.queryHint")}
              name="q"
              icon="search"
              value={values.q}
              placeholder={t("doctors.namePlaceholder")}
              onChange={(value) => set("q", value, { debounce: true })}
            />
          ) : null}
          <ChoiceChips
            legend={t("filters.specialty")}
            name="specialty"
            value={values.specialty}
            allLabel={t("doctors.allSpecialties")}
            options={specialties.map((s) => ({ value: s.slug, label: s.name }))}
            onChange={(value) => set("specialty", value)}
          />
          {languages.length > 0 ? (
            <ChoiceChips
              legend={t("filters.language")}
              name="language"
              value={values.language}
              allLabel={t("doctors.allLanguages")}
              options={languages.map((l) => ({ value: l.slug, label: l.name }))}
              onChange={(value) => set("language", value)}
            />
          ) : null}
          <TextFilter
            label={t("filters.city")}
            name="city"
            icon="map-pin"
            value={values.city}
            placeholder={t("doctors.cityPlaceholder")}
            autoComplete="address-level2"
            onChange={(value) => set("city", value, { debounce: true })}
          />
          <FilterGroup legend={t("directory.availability")}>
            <SwitchRow
              name="min_reviews"
              label={t("doctors.hasReviews")}
              checked={values.min_reviews === "1"}
              onChange={(checked) => set("min_reviews", checked ? "1" : "")}
            />
          </FilterGroup>
        </>
      )}
    >
      {children}
    </DirectoryListView>
  );
}
