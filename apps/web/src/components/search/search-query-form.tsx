import {
  FilterField,
  FilterForm,
  SEARCH_QUERY_HINT,
  filterInputClassName,
} from "@/components/directory/filter-form";
import { t } from "@/i18n/t";

/**
 * The /search query form. The query is the page's only real control, so it is
 * never collapsed behind the mobile "Филтрирај" toggle, and it takes focus on
 * the empty hub so a visitor arriving from the header search icon can type at
 * once.
 */
export function SearchQueryForm({
  q,
  city,
  autoFocus = false,
}: {
  q?: string;
  city?: string;
  autoFocus?: boolean;
}) {
  return (
    <div className="filters-card filters-card-nested p-4 sm:p-5">
      <FilterForm
        searchHint={SEARCH_QUERY_HINT}
        action="/search"
        method="get"
        fieldsClassName="sm:grid-cols-2"
        collapsible={false}
      >
        <FilterField label={t("search.queryLabel")}>
          <input
            name="q"
            type="search"
            defaultValue={q ?? ""}
            className={filterInputClassName}
            autoComplete="off"
            autoFocus={autoFocus}
          />
        </FilterField>
        <FilterField label={t("search.cityLabel")}>
          <input
            name="city"
            defaultValue={city ?? ""}
            className={filterInputClassName}
            autoComplete="off"
          />
        </FilterField>
      </FilterForm>
    </div>
  );
}
