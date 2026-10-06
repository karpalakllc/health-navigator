"use client";

import {
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  type KeyboardEvent,
  type RefObject,
} from "react";
import { BottomSheet } from "@/components/ui/bottom-sheet";
import { Icon } from "@/components/ui/icons";
import type { LocationCity } from "@/lib/api/locations";
import { cn } from "@/lib/cn";
import {
  cityFilterFor,
  findPlaceByName,
  MK_PLACE_GROUPS,
  placeMatches,
  type CityChoice,
  type Place,
  type PlaceGroup,
} from "@/lib/mk-places";
import { foldScript } from "@/lib/script-fold";
import { t, tCount, tFormat } from "@/i18n/t";

/** Wider than this, the picker drops down under the bar; narrower, a sheet. */
const DESKTOP_QUERY = "(min-width: 64rem)";

type TreeNode =
  | { id: string; kind: "any" }
  | { id: string; kind: "group"; group: PlaceGroup }
  | { id: string; kind: "place"; group: PlaceGroup; place: Place };

type VisibleGroup = {
  group: PlaceGroup;
  expanded: boolean;
  /** Forced open by a filter whose matches sit under it. */
  forced: boolean;
  places: Place[];
};

function visibleGroups(query: string, expanded: ReadonlySet<string>) {
  const result: VisibleGroup[] = [];
  for (const group of MK_PLACE_GROUPS) {
    if (!query.trim()) {
      const open = expanded.has(group.city.id);
      result.push({
        group,
        expanded: open,
        forced: false,
        places: open ? group.places : [],
      });
      continue;
    }
    const matches = group.places.filter((place) => placeMatches(place, query));
    if (matches.length > 0) {
      result.push({ group, expanded: true, forced: true, places: matches });
    } else if (placeMatches(group.city, query)) {
      const open = expanded.has(group.city.id);
      result.push({
        group,
        expanded: open,
        forced: false,
        places: open ? group.places : [],
      });
    }
  }
  return result;
}

function flatten(query: string, groups: VisibleGroup[]): TreeNode[] {
  const nodes: TreeNode[] = query.trim() ? [] : [{ id: "any", kind: "any" }];
  for (const entry of groups) {
    nodes.push({ id: entry.group.city.id, kind: "group", group: entry.group });
    for (const place of entry.places) {
      nodes.push({
        id: `${entry.group.city.id}--${place.id}`,
        kind: "place",
        group: entry.group,
        place,
      });
    }
  }
  return nodes;
}

function initialChoice(city: string | undefined): CityChoice | null {
  const value = city?.trim();
  if (!value) return null;
  const found = findPlaceByName(value);
  return { label: found?.place.name ?? value, city: value };
}

/**
 * The city picker inside the master search bar: a „📍 Скопје ▾“ button that
 * opens the bigger towns of North Macedonia (one per statistical region),
 * each expanding to its region's municipalities — the City of Skopje's ten
 * first. A filter box above the tree matches either script („Карпош“,
 * „Karpoš“, „karposh“).
 *
 * The filter box is an ARIA combobox whose popup is a tree, driven by
 * aria-activedescendant: ↑/↓ move, → opens a town (or steps into it), ←
 * closes it (or steps back to it), Home/End jump, Enter picks, Escape
 * closes. Desktop: a non-modal popover under the bar. Phones: a bottom
 * sheet (modal, focus trapped).
 *
 * The choice is sent as `city`, the filter the API understands: listings
 * store a town, so a Skopje municipality searches „Скопје“, and any other
 * municipality without listings searches its region's main town (the row
 * says so). Without JavaScript the button does nothing and the search
 * covers every city.
 */
export function CityPicker({
  name = "city",
  defaultCity,
  knownCities = [],
  className,
}: {
  name?: string;
  defaultCity?: string;
  knownCities?: LocationCity[];
  className?: string;
}) {
  const uid = useId();
  const triggerRef = useRef<HTMLButtonElement>(null);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const filterRef = useRef<HTMLInputElement>(null);
  const [choice, setChoice] = useState<CityChoice | null>(() =>
    initialChoice(defaultCity),
  );
  const [open, setOpen] = useState(false);
  const [mode, setMode] = useState<"popover" | "sheet">("popover");

  const known = useMemo(() => knownCities.map((c) => c.name), [knownCities]);

  function openPicker() {
    const desktop =
      typeof window.matchMedia !== "function" ||
      window.matchMedia(DESKTOP_QUERY).matches;
    setMode(desktop ? "popover" : "sheet");
    setOpen(true);
  }

  function close(returnFocus = true) {
    setOpen(false);
    if (returnFocus) {
      triggerRef.current?.focus();
    }
  }

  function pick(next: CityChoice | null) {
    setChoice(next);
    close();
  }

  // The popover closes when the pointer goes down outside it.
  useEffect(() => {
    if (!open || mode !== "popover") return;
    function onPointerDown(event: PointerEvent) {
      if (!wrapperRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener("pointerdown", onPointerDown);
    return () => document.removeEventListener("pointerdown", onPointerDown);
  }, [open, mode]);

  const label = choice
    ? tFormat("homeSearch.cityTrigger", { city: choice.label })
    : t("homeSearch.cityTriggerNone");

  const panel = (
    <CityPickerPanel
      uid={uid}
      knownCities={knownCities}
      known={known}
      selected={choice}
      onPick={pick}
      onEscape={() => close()}
      inputRef={filterRef}
      autoFocus={mode === "popover"}
    />
  );

  return (
    <div
      ref={wrapperRef}
      className={cn("relative flex min-w-0", className)}
      onBlur={(event) => {
        // Tabbing out of the popover closes it (focus stays where it went).
        if (
          mode === "popover" &&
          open &&
          event.relatedTarget instanceof Node &&
          !wrapperRef.current?.contains(event.relatedTarget)
        ) {
          setOpen(false);
        }
      }}
    >
      <button
        ref={triggerRef}
        type="button"
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-label={label}
        onClick={() => (open ? close() : openPicker())}
        className="flex h-12 min-w-0 max-w-full items-center gap-1.5 rounded-pill pl-2.5 pr-2 text-left font-ui text-base font-medium text-ink transition-colors hover:bg-sand lg:h-12 lg:pl-3 lg:pr-3"
      >
        <Icon name="map-pin" size={20} className="shrink-0 text-ink-2" />
        <span
          className={cn("min-w-0 truncate", !choice && "text-ink-2")}
          data-city-label=""
        >
          {choice?.label ?? t("homeSearch.cityNone")}
        </span>
        <Icon name="chevron-down" size={18} className="shrink-0 text-ink-2" />
      </button>
      {choice ? <input type="hidden" name={name} value={choice.city} /> : null}

      {open && mode === "popover" ? (
        <div
          role="dialog"
          aria-label={t("homeSearch.cityDialogTitle")}
          className="motion-fade-in absolute right-0 top-[calc(100%+0.75rem)] z-50 flex max-h-[min(30rem,70vh)] w-[22.5rem] flex-col overflow-hidden rounded-card bg-white shadow-card-hover ring-1 ring-line"
        >
          {panel}
        </div>
      ) : null}
      <BottomSheet
        open={open && mode === "sheet"}
        onClose={() => setOpen(false)}
        title={t("homeSearch.cityDialogTitle")}
        closeLabel={t("homeSearch.cityClose")}
        initialFocusRef={filterRef}
      >
        {panel}
      </BottomSheet>
    </div>
  );
}

function CityPickerPanel({
  uid,
  knownCities,
  known,
  selected,
  onPick,
  onEscape,
  inputRef,
  autoFocus,
}: {
  uid: string;
  knownCities: LocationCity[];
  known: string[];
  selected: CityChoice | null;
  onPick: (choice: CityChoice | null) => void;
  onEscape: () => void;
  inputRef: RefObject<HTMLInputElement | null>;
  autoFocus: boolean;
}) {
  const inputId = `${uid}-filter`;
  const treeId = `${uid}-tree`;
  const hintId = `${uid}-hint`;
  const [query, setQuery] = useState("");
  const [expanded, setExpanded] = useState<ReadonlySet<string>>(() => {
    const found = selected ? findPlaceByName(selected.label) : undefined;
    return new Set(
      found && found.place !== found.group.city ? [found.group.city.id] : [],
    );
  });
  const [activeId, setActiveId] = useState<string | null>(null);

  const groups = useMemo(
    () => visibleGroups(query, expanded),
    [query, expanded],
  );
  const nodes = useMemo(() => flatten(query, groups), [query, groups]);
  const activeIndex = nodes.findIndex((node) => node.id === activeId);
  const domId = (id: string) => `${uid}-node-${id}`;

  const counts = useMemo(() => {
    const map = new Map<string, number>();
    for (const city of knownCities) {
      const key = foldScript(city.name);
      map.set(
        key,
        (map.get(key) ?? 0) + city.doctors_count + city.facilities_count,
      );
    }
    return map;
  }, [knownCities]);

  useEffect(() => {
    if (autoFocus) inputRef.current?.focus();
  }, [autoFocus, inputRef]);

  useEffect(() => {
    if (!activeId) return;
    const el = document.getElementById(domId(activeId));
    if (el && typeof el.scrollIntoView === "function") {
      el.scrollIntoView({ block: "nearest" });
    }
    // domId is derived from uid only.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeId]);

  function setGroupOpen(group: PlaceGroup, open: boolean) {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (open) next.add(group.city.id);
      else next.delete(group.city.id);
      return next;
    });
  }

  function choose(node: TreeNode) {
    if (node.kind === "any") {
      onPick(null);
    } else if (node.kind === "group") {
      onPick({ label: node.group.city.name, city: node.group.city.name });
    } else {
      onPick({
        label: node.place.name,
        city: cityFilterFor(node.place, node.group, known),
      });
    }
  }

  function groupEntry(group: PlaceGroup) {
    return groups.find((entry) => entry.group === group);
  }

  function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    const active = activeIndex >= 0 ? nodes[activeIndex] : undefined;
    switch (event.key) {
      case "ArrowDown": {
        event.preventDefault();
        const next = Math.min(activeIndex + 1, nodes.length - 1);
        setActiveId(nodes[next]?.id ?? null);
        return;
      }
      case "ArrowUp": {
        event.preventDefault();
        const prev = Math.max(activeIndex - 1, 0);
        setActiveId(nodes[prev]?.id ?? null);
        return;
      }
      case "Home":
      case "End": {
        if (!active) return;
        event.preventDefault();
        const target = event.key === "Home" ? nodes[0] : nodes.at(-1);
        setActiveId(target?.id ?? null);
        return;
      }
      case "ArrowRight": {
        if (active?.kind !== "group") return;
        event.preventDefault();
        const entry = groupEntry(active.group);
        if (!entry?.expanded) {
          setGroupOpen(active.group, true);
        } else if (entry.places[0]) {
          setActiveId(`${active.group.city.id}--${entry.places[0].id}`);
        }
        return;
      }
      case "ArrowLeft": {
        if (!active || active.kind === "any") return;
        event.preventDefault();
        if (active.kind === "place") {
          setActiveId(active.group.city.id);
        } else if (!groupEntry(active.group)?.forced) {
          setGroupOpen(active.group, false);
        }
        return;
      }
      case "Enter": {
        // Never submits the search form around the popover.
        event.preventDefault();
        // Nothing highlighted: the first row that itself matches the filter
        // (a town listed only because a municipality under it matches
        // doesn't count).
        const target =
          active ??
          (query.trim()
            ? nodes.find(
                (n) =>
                  n.kind === "place" ||
                  (n.kind === "group" && placeMatches(n.group.city, query)),
              )
            : undefined);
        if (target) choose(target);
        return;
      }
      case "Escape": {
        event.preventDefault();
        event.stopPropagation();
        onEscape();
        return;
      }
    }
  }

  function countFor(placeName: string) {
    return counts.get(foldScript(placeName));
  }

  /** Does this municipality search its group's main town instead of itself? */
  function searchesGroupTown(group: PlaceGroup, place: Place) {
    const filter = cityFilterFor(place, group, known);
    return (
      foldScript(filter) !== foldScript(place.seat ?? place.name) &&
      foldScript(filter) === foldScript(group.city.name)
    );
  }

  /**
   * A row's second line: its profile count when it holds listings. Rows
   * that search the group's town say nothing — the group says it once — and
   * only a fallback to some other town is spelled out on the row.
   */
  function renderMeta(group: PlaceGroup, place: Place) {
    const filter = cityFilterFor(place, group, known);
    const own = foldScript(filter) === foldScript(place.seat ?? place.name);
    if (own) {
      const count = countFor(filter);
      return count ? tCount("homeSearch.cityProfiles", count) : null;
    }
    return searchesGroupTown(group, place)
      ? null
      : tFormat("homeSearch.cityFallback", { city: filter });
  }

  const anySelected = selected === null;

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="shrink-0 p-3 pb-2">
        <label htmlFor={inputId} className="sr-only">
          {t("homeSearch.cityFilterLabel")}
        </label>
        <div className="relative">
          <Icon
            name="search"
            size={20}
            className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-2"
          />
          <input
            ref={inputRef}
            id={inputId}
            type="text"
            role="combobox"
            aria-expanded="true"
            aria-controls={treeId}
            aria-haspopup="tree"
            aria-autocomplete="list"
            aria-activedescendant={activeId ? domId(activeId) : undefined}
            aria-describedby={hintId}
            autoComplete="off"
            spellCheck={false}
            value={query}
            placeholder={t("homeSearch.cityFilterPlaceholder")}
            onChange={(event) => {
              setQuery(event.target.value);
              setActiveId(null);
            }}
            onKeyDown={onKeyDown}
            className="field-control h-12 w-full rounded-input border border-line-strong bg-white pl-11 pr-3 type-body text-ink placeholder:text-ink-2"
          />
        </div>
        <p id={hintId} className="sr-only">
          {t("homeSearch.cityFilterHint")}
        </p>
      </div>

      <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-2 pb-3">
        {nodes.length === 0 ? (
          <p role="status" className="type-meta px-3 py-4 text-ink-2">
            {tFormat("homeSearch.cityNoMatch", { q: query.trim() })}
          </p>
        ) : null}
        <ul
          role="tree"
          id={treeId}
          aria-label={t("homeSearch.cityTreeLabel")}
          // Clicks pick without moving focus out of the filter box.
          onMouseDown={(event) => event.preventDefault()}
          className="flex flex-col"
        >
          {query.trim() ? null : (
            <li
              role="treeitem"
              id={domId("any")}
              aria-level={1}
              aria-selected={anySelected}
              onClick={() => onPick(null)}
              className={rowClass(activeId === "any", anySelected)}
            >
              <Icon name="globe" size={20} className="shrink-0 text-ink-2" />
              <span className="flex-1 font-ui font-semibold">
                {t("homeSearch.cityAny")}
              </span>
              {anySelected ? <Icon name="check" size={20} /> : null}
            </li>
          )}
          {groups.map((entry) => {
            const { group } = entry;
            const isSelected =
              selected !== null &&
              selected.label === group.city.name &&
              foldScript(selected.city) === foldScript(group.city.name);
            const count = countFor(group.city.name);
            // Said once on the open group, not on every row under it.
            const groupNote =
              entry.expanded &&
              entry.places.some((place) => searchesGroupTown(group, place))
                ? tFormat("homeSearch.cityGroupFallback", {
                    city: group.city.name,
                  })
                : null;
            const groupLabel = [
              group.city.name,
              group.region,
              count ? tCount("homeSearch.cityProfiles", count) : null,
              groupNote,
            ]
              .filter(Boolean)
              .join(", ");

            return (
              <li
                key={group.city.id}
                role="treeitem"
                id={domId(group.city.id)}
                aria-level={1}
                aria-expanded={entry.expanded}
                aria-selected={isSelected}
                aria-label={groupLabel}
              >
                <div
                  onClick={() =>
                    onPick({ label: group.city.name, city: group.city.name })
                  }
                  className={rowClass(activeId === group.city.id, isSelected)}
                >
                  <span
                    aria-hidden="true"
                    title={tCount(
                      "homeSearch.cityMunicipalities",
                      group.places.length,
                    )}
                    data-expand-toggle=""
                    onClick={(event) => {
                      event.stopPropagation();
                      if (!entry.forced) {
                        setGroupOpen(group, !entry.expanded);
                      }
                    }}
                    className="-my-1 -ml-1 flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-ink-2 hover:bg-chip-tint"
                  >
                    <Icon
                      name="chevron-right"
                      size={18}
                      className={cn(
                        "transition-transform",
                        entry.expanded && "rotate-90",
                      )}
                    />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block font-ui font-semibold leading-5">
                      {group.city.name}
                    </span>
                    <span className="block text-sm leading-5 text-ink-2">
                      {group.region}
                      {count ? (
                        <>
                          {" · "}
                          {tCount("homeSearch.cityProfiles", count)}
                        </>
                      ) : null}
                    </span>
                    {groupNote ? (
                      <span
                        data-group-note=""
                        className="mt-0.5 block text-sm leading-5 text-ink-2"
                      >
                        {groupNote}
                      </span>
                    ) : null}
                  </span>
                  {isSelected ? <Icon name="check" size={20} /> : null}
                </div>
                {entry.expanded && entry.places.length > 0 ? (
                  <ul role="group" className="flex flex-col">
                    {entry.places.map((place) => {
                      const id = `${group.city.id}--${place.id}`;
                      const meta = renderMeta(group, place);
                      const placeSelected =
                        selected !== null && selected.label === place.name;
                      return (
                        <li
                          key={place.id}
                          role="treeitem"
                          id={domId(id)}
                          aria-level={2}
                          aria-selected={placeSelected}
                          onClick={() =>
                            choose({ id, kind: "place", group, place })
                          }
                          className={cn(
                            rowClass(activeId === id, placeSelected),
                            "pl-12",
                          )}
                        >
                          <span className="min-w-0 flex-1">
                            <span className="block leading-5">
                              {place.name}
                            </span>
                            {meta ? (
                              <span className="block text-sm leading-5 text-ink-2">
                                {meta}
                              </span>
                            ) : null}
                          </span>
                          {placeSelected ? (
                            <Icon name="check" size={20} />
                          ) : null}
                        </li>
                      );
                    })}
                  </ul>
                ) : null}
              </li>
            );
          })}
        </ul>
      </div>
    </div>
  );
}

function rowClass(active: boolean, selected: boolean) {
  return cn(
    "flex min-h-12 cursor-pointer items-center gap-2 rounded-lg px-3 py-1.5 text-base text-ink transition-colors hover:bg-sand",
    selected && "bg-chip-tint",
    // The active row (keyboard) gets the ink outline the focus ring uses.
    active && "outline outline-2 -outline-offset-2 outline-ink",
  );
}
