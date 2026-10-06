"use client";

import { useId, useState } from "react";
import { Checkbox, Fieldset, Input } from "@/components/ui/field";
import type { DashboardOption } from "@/lib/api/doctor-dashboard-types";
import { cn } from "@/lib/cn";

/**
 * A titled group of checkboxes over a list of published options (languages,
 * services, specialties, workplaces). Long lists get a filter box and scroll
 * inside a bounded panel; checked options stay listed whatever the filter.
 */
export function OptionChecklist({
  legend,
  hint,
  options,
  selected,
  onChange,
  filterLabel,
  describe,
}: {
  legend: string;
  hint?: string;
  options: DashboardOption[];
  selected: number[];
  onChange: (ids: number[]) => void;
  /** Shown above the list when given (for long lists). */
  filterLabel?: string;
  /** Extra text after an option's name, e.g. a facility's city. */
  describe?: (option: DashboardOption) => string | null;
}) {
  const [query, setQuery] = useState("");
  const filterId = useId();
  const needle = query.trim().toLocaleLowerCase("mk");
  const visible =
    needle === ""
      ? options
      : options.filter(
          (option) =>
            selected.includes(option.id) ||
            option.name.toLocaleLowerCase("mk").includes(needle),
        );

  function toggle(id: number, checked: boolean) {
    onChange(
      checked
        ? [...selected, id]
        : selected.filter((selectedId) => selectedId !== id),
    );
  }

  return (
    <Fieldset legend={legend} hint={hint}>
      {filterLabel ? (
        <Input
          id={`filter${filterId.replace(/[^a-zA-Z0-9_-]/g, "")}`}
          label={filterLabel}
          type="search"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          className="mb-2"
        />
      ) : null}
      <div
        className={cn(
          "grid gap-x-6 sm:grid-cols-2",
          options.length > 12 &&
            "max-h-80 overflow-y-auto rounded-2xl border border-line px-4",
        )}
      >
        {visible.map((option) => {
          const extra = describe?.(option);

          return (
            <Checkbox
              key={option.id}
              name={legend}
              value={option.id}
              checked={selected.includes(option.id)}
              onChange={(event) => toggle(option.id, event.target.checked)}
              label={extra ? `${option.name} · ${extra}` : option.name}
            />
          );
        })}
      </div>
    </Fieldset>
  );
}
