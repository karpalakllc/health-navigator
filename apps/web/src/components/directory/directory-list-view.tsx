"use client";

import {
  useId,
  useRef,
  useState,
  useSyncExternalStore,
  type ReactNode,
} from "react";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import {
  SortSelect,
  type ChoiceOption,
} from "@/components/directory/filter-controls";
import {
  activeFilterCount,
  useLiveFilters,
  type FilterValues,
  type LiveFilters,
} from "@/components/directory/live-filters";
import { BottomSheet } from "@/components/ui/bottom-sheet";
import { Button } from "@/components/ui/button";
import { FilterChip, RemovableChip } from "@/components/ui/chip";
import { Icon } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { t, tCount, tFormat, type MessageKey } from "@/i18n/t";

export type ActiveFilter = {
  /** The URL parameter it sets. */
  name: string;
  /** What the chip says („Кардиологија“, „Скопје“). */
  label: string;
};

export type QuickChip = {
  name: string;
  value: string;
  label: string;
};

type DirectoryListViewProps = {
  basePath: string;
  title: string;
  /** Count line, pluralised: e.g. "doctors.resultsCount". */
  countKey: MessageKey;
  total: number;
  applied: FilterValues;
  /** The search strip's parameter and copy. */
  search: { name: string; label: string; placeholder: string };
  /** Filters shown as removable chips when set (the query is one of them). */
  activeFilters: ActiveFilter[];
  /** One-tap toggles above the results. */
  quickChips?: QuickChip[];
  sort?: { name: string; options: ChoiceOption[] };
  /** The sheet / rail controls. `inRail` adds the query field on desktop. */
  renderFields?: (filters: LiveFilters, place: "rail" | "sheet") => ReactNode;
  /** „За овие податоци“ lines. */
  aboutData?: string[];
  children: ReactNode;
};

const noopSubscribe = () => () => {};

/**
 * One layout for every directory list (D2a „Праска“):
 *
 * - mobile: a sticky search strip with „Филтри (N)“ that opens a full-height
 *   sheet, the title and count, quick chips and removable active filters,
 *   then the results — which start above the fold;
 * - desktop: breadcrumb, title, count and sort, quick chips, then a sticky
 *   4-column filter card beside an 8-column results area.
 *
 * Filters apply as they change (useLiveFilters). Before hydration the rail is
 * a plain GET form with an „Примени“ button, so it works without JS.
 */
export function DirectoryListView({
  basePath,
  title,
  countKey,
  total,
  applied,
  search,
  activeFilters,
  quickChips = [],
  sort,
  renderFields,
  aboutData = [],
  children,
}: DirectoryListViewProps) {
  const filters = useLiveFilters({ basePath, applied });
  const { values, set, clear, flush, pending, hrefFor } = filters;
  const [sheetOpen, setSheetOpen] = useState(false);
  const hydrated = useSyncExternalStore(
    noopSubscribe,
    () => true,
    () => false,
  );
  const headingRef = useRef<HTMLHeadingElement>(null);
  const resultsId = useId();
  const countLine = tCount(countKey, total);

  const sheetNames = Object.keys(applied).filter(
    (name) => name !== search.name && name !== sort?.name,
  );
  const sheetCount = activeFilterCount(applied, [
    search.name,
    ...(sort ? [sort.name] : []),
  ]);
  const anyActive = activeFilterCount(applied) > 0;

  function showResults() {
    flush();
    setSheetOpen(false);
    // After the sheet hands focus back, move it to the results' heading
    // (focusing scrolls it into view, clear of the sticky header).
    requestAnimationFrame(() => headingRef.current?.focus());
  }

  const searchStrip = (
    <form
      role="search"
      aria-label={t("directory.searchLabel")}
      action={basePath}
      method="get"
      onSubmit={(event) => {
        event.preventDefault();
        set(search.name, values[search.name] ?? "");
      }}
      className="flex items-center gap-2"
    >
      {Object.entries(applied).map(([name, value]) =>
        name !== search.name && value ? (
          <input key={name} type="hidden" name={name} value={value} />
        ) : null,
      )}
      <div className="relative min-w-0 flex-1">
        <label htmlFor={`${resultsId}-q`} className="sr-only">
          {search.label}
        </label>
        <Icon
          name="search"
          size={22}
          className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink"
        />
        <input
          id={`${resultsId}-q`}
          type="search"
          name={search.name}
          value={values[search.name] ?? ""}
          onChange={(event) =>
            set(search.name, event.target.value, { debounce: true })
          }
          placeholder={search.placeholder}
          enterKeyHint="search"
          className="h-14 w-full rounded-pill bg-white pl-12 pr-4 text-ink shadow-card"
        />
      </div>
      {renderFields ? (
        <Button
          variant="soft"
          leadingIcon="sliders"
          className="h-14 shrink-0 px-4"
          aria-haspopup="dialog"
          aria-expanded={sheetOpen}
          onClick={() => setSheetOpen(true)}
        >
          {sheetCount > 0
            ? tFormat("directory.filtersButton", { count: sheetCount })
            : t("directory.filters")}
        </Button>
      ) : null}
    </form>
  );

  const quickRow =
    quickChips.length > 0 ? (
      <div
        role="group"
        aria-label={t("directory.filters")}
        className="scroll-row -mx-5 flex gap-2 px-5 py-1 lg:mx-0 lg:flex-wrap lg:px-0"
      >
        {quickChips.map((chip) => {
          const selected = values[chip.name] === chip.value;

          return (
            <FilterChip
              key={`${chip.name}-${chip.value}`}
              selected={selected}
              onClick={() => set(chip.name, selected ? "" : chip.value)}
            >
              {chip.label}
            </FilterChip>
          );
        })}
      </div>
    ) : null;

  const activeRow =
    activeFilters.length > 0 ? (
      <div className="flex flex-wrap items-center gap-2">
        <h2 className="sr-only">{t("filters.filtersActive")}</h2>
        {activeFilters.map((filter) => (
          <RemovableChip
            key={filter.name}
            label={filter.label}
            removeHref={hrefFor({ ...applied, [filter.name]: "" })}
            onRemove={() => {
              set(filter.name, "");
              // The chip is about to disappear; don't drop focus on <body>.
              headingRef.current?.focus();
            }}
          />
        ))}
      </div>
    ) : null;

  return (
    <div className="mx-auto flex w-full max-w-[1240px] min-w-0 flex-col px-5 pb-10 lg:px-6 lg:pb-20">
      {/* Mobile: the search strip stays under the header while scrolling. */}
      <div
        data-sticky-search-strip
        className="sticky top-[var(--header-h)] z-20 -mx-5 bg-cream px-5 pb-3 pt-2 lg:hidden"
      >
        {searchStrip}
      </div>

      <Breadcrumbs
        className="mb-0 hidden pt-8 lg:flex"
        items={[{ label: t("common.home"), href: "/" }, { label: title }]}
      />

      <div className="flex flex-wrap items-end justify-between gap-x-4 gap-y-2 pt-2 lg:pt-4">
        <div className="min-w-0">
          <h1
            ref={headingRef}
            tabIndex={-1}
            id={`${resultsId}-title`}
            className="type-h2 text-ink lg:text-[2.5rem] lg:font-bold lg:leading-[2.875rem]"
          >
            {title}
          </h1>
          <p
            className="type-meta mt-1 text-ink-2"
            aria-live="polite"
            aria-busy={pending || undefined}
          >
            {countLine}
          </p>
        </div>
        {sort ? (
          <SortSelect
            name={sort.name}
            value={values[sort.name] || sort.options[0].value}
            options={sort.options}
            onChange={(value) =>
              set(sort.name, value === sort.options[0].value ? "" : value)
            }
          />
        ) : null}
      </div>

      <div className="mt-4 flex flex-col gap-3 empty:hidden lg:mt-6">
        {quickRow}
        {activeRow}
      </div>

      <div className="mt-5 grid min-w-0 items-start gap-5 lg:mt-8 lg:grid-cols-12 lg:gap-5">
        {renderFields ? (
          <aside
            aria-labelledby={`${resultsId}-rail`}
            className="card sticky top-[calc(var(--header-h)+1rem)] hidden p-6 lg:col-span-4 lg:block"
          >
            <div className="mb-5 flex items-center justify-between gap-3">
              <h2 id={`${resultsId}-rail`} className="type-h3 text-ink">
                {t("directory.filters")}
              </h2>
              {anyActive ? (
                <a
                  href={basePath}
                  onClick={(event) => {
                    event.preventDefault();
                    clear();
                  }}
                  className="link-underline inline-flex min-h-12 items-center font-semibold text-ink"
                >
                  {t("common.clearFilters")}
                </a>
              ) : null}
            </div>
            <form
              action={basePath}
              method="get"
              onSubmit={(event) => {
                event.preventDefault();
                flush();
              }}
              className="flex flex-col gap-7"
            >
              {renderFields(filters, "rail")}
              {!hydrated ? (
                <Button type="submit" fullWidth>
                  {t("directory.apply")}
                </Button>
              ) : null}
            </form>
          </aside>
        ) : null}

        <section
          aria-labelledby={`${resultsId}-title`}
          aria-busy={pending || undefined}
          className={cn(
            "flex min-w-0 flex-col gap-5 motion-safe:transition-opacity",
            renderFields ? "lg:col-span-8" : "lg:col-span-12",
            pending && "opacity-60",
          )}
        >
          {children}
        </section>
      </div>

      {aboutData.length > 0 ? (
        <details className="card group mt-8 p-0">
          <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 rounded-card px-5 type-body font-semibold text-ink [&::-webkit-details-marker]:hidden">
            <span className="flex items-center gap-3">
              <Icon name="info" size={22} />
              {t("directory.aboutData")}
            </span>
            <Icon
              name="chevron-down"
              size={20}
              className="transition-transform group-open:rotate-180"
            />
          </summary>
          <ul className="flex flex-col gap-2 px-5 pb-5 type-body text-ink-2">
            {aboutData.map((line) => (
              <li key={line} className="flex gap-2">
                <Icon name="check" size={20} className="mt-0.5 text-care" />
                {line}
              </li>
            ))}
          </ul>
        </details>
      ) : null}

      {renderFields ? (
        <BottomSheet
          open={sheetOpen}
          onClose={() => setSheetOpen(false)}
          title={t("directory.filters")}
          footer={
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => clear(sheetNames)}
                className="link-underline inline-flex min-h-12 shrink-0 items-center px-1 font-semibold text-ink"
              >
                {t("directory.clear")}
              </button>
              <Button
                size="lg"
                className="flex-1"
                loading={pending}
                onClick={showResults}
              >
                {tCount("directory.showResults", total)}
              </Button>
            </div>
          }
        >
          <form
            action={basePath}
            method="get"
            onSubmit={(event) => {
              event.preventDefault();
              showResults();
            }}
            className="flex flex-col gap-7 pt-2"
          >
            {renderFields(filters, "sheet")}
          </form>
          <p className="sr-only" aria-live="polite">
            {pending ? t("ui.loading") : countLine}
          </p>
        </BottomSheet>
      ) : null}
    </div>
  );
}
