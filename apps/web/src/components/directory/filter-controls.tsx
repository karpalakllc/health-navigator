"use client";

import { useId, useState, type ReactNode } from "react";
import { Icon, type IconName } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { t, tFormat } from "@/i18n/t";

/*
 * The controls inside the filter rail (desktop) and the filter sheet
 * (mobile). They are real form controls with names, so the surrounding
 * GET form still works before hydration; once hydrated every change goes
 * through useLiveFilters and applies straight away.
 */

/** Same ring as the global :focus-visible, for chips whose input is hidden. */
const chipFocusRing = "focus-ring-within";

export function FilterGroup({
  legend,
  children,
  className,
}: {
  legend: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <fieldset className={cn("flex min-w-0 flex-col gap-3", className)}>
      <legend className="type-label mb-3 text-ink">{legend}</legend>
      {children}
    </fieldset>
  );
}

export type ChoiceOption = { value: string; label: string };

/**
 * A single choice shown as chips (a radio group): „Сите“ + the options.
 * Long lists show the first `visible` options and a „Сите N“ toggle.
 */
export function ChoiceChips({
  legend,
  name,
  value,
  options,
  allLabel,
  onChange,
  visible = 8,
}: {
  legend: string;
  name: string;
  value: string;
  options: ChoiceOption[];
  allLabel: string;
  onChange: (value: string) => void;
  visible?: number;
}) {
  const [expanded, setExpanded] = useState(false);
  const listId = useId();
  const selectedIndex = options.findIndex((o) => o.value === value);
  // Never hide the chosen option behind „Сите N“.
  const limit = Math.max(visible, selectedIndex + 1);
  const shown = expanded ? options : options.slice(0, limit);
  const hidden = options.length - shown.length;

  return (
    <FilterGroup legend={legend}>
      <div id={listId} className="flex flex-wrap gap-2">
        {[{ value: "", label: allLabel }, ...shown].map((option) => {
          const checked = option.value === value;

          return (
            <label
              key={option.value || "__all"}
              className={cn("chip", checked && "chip-selected", chipFocusRing)}
            >
              <input
                type="radio"
                name={name}
                value={option.value}
                checked={checked}
                onChange={() => onChange(option.value)}
                className="sr-only"
              />
              {checked ? <Icon name="check" size={18} /> : null}
              <span>{option.label}</span>
            </label>
          );
        })}
      </div>
      {hidden > 0 || expanded ? (
        <button
          type="button"
          aria-expanded={expanded}
          aria-controls={listId}
          onClick={() => setExpanded((open) => !open)}
          className="link-underline inline-flex min-h-12 items-center self-start font-semibold text-ink"
        >
          {expanded
            ? t("directory.showFewerOptions")
            : tFormat("directory.showAllOptions", {
                count: options.length,
              })}
        </button>
      ) : null}
    </FilterGroup>
  );
}

/** An on/off filter as a switch row (56px): label left, switch right. */
export function SwitchRow({
  name,
  label,
  checked,
  onChange,
  icon,
}: {
  name: string;
  label: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  icon?: IconName;
}) {
  const id = useId();

  return (
    <div className="flex min-h-14 items-center justify-between gap-4">
      <label
        htmlFor={id}
        className="flex flex-1 cursor-pointer items-center gap-2 type-body text-ink"
      >
        {icon ? <Icon name={icon} size={20} className="text-ink-2" /> : null}
        {label}
      </label>
      <span className="relative inline-flex shrink-0">
        <input
          id={id}
          type="checkbox"
          role="switch"
          name={name}
          value="1"
          checked={checked}
          onChange={(event) => onChange(event.target.checked)}
          className="peer h-8 w-[3.25rem] cursor-pointer appearance-none rounded-full bg-line-strong transition-colors checked:bg-ink"
        />
        <span
          aria-hidden="true"
          className="pointer-events-none absolute left-1 top-1 inline-flex size-6 items-center justify-center rounded-full bg-white text-ink transition-transform peer-checked:translate-x-5 [&>svg]:opacity-0 peer-checked:[&>svg]:opacity-100"
        >
          <Icon name="check" size={16} />
        </span>
      </span>
    </div>
  );
}

/** A labelled text filter (city, name) with a leading icon. */
export function TextFilter({
  label,
  name,
  value,
  placeholder,
  icon,
  onChange,
  autoComplete,
  hint,
}: {
  label: string;
  name: string;
  value: string;
  placeholder?: string;
  icon: IconName;
  onChange: (value: string) => void;
  autoComplete?: string;
  hint?: string;
}) {
  const id = useId();
  const hintId = `${id}-hint`;

  return (
    <div className="flex flex-col gap-2">
      <label htmlFor={id} className="type-label text-ink">
        {label}
      </label>
      {hint ? (
        <p id={hintId} className="type-meta -mt-1 text-ink-2">
          {hint}
        </p>
      ) : null}
      <div className="relative">
        <Icon
          name={icon}
          size={20}
          className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-2"
        />
        <input
          id={id}
          type="search"
          name={name}
          value={value}
          placeholder={placeholder}
          autoComplete={autoComplete}
          enterKeyHint="search"
          aria-describedby={hint ? hintId : undefined}
          onChange={(event) => onChange(event.target.value)}
          className="field-control pl-12"
        />
      </div>
    </div>
  );
}

/** A native select dressed as a pill („Подреди: По име“). */
export function SortSelect({
  name,
  value,
  options,
  onChange,
  className,
  label = t("directory.sortLabel"),
}: {
  name?: string;
  value: string;
  options: ChoiceOption[];
  onChange: (value: string) => void;
  className?: string;
  /** Visible inside the pill from sm up; always the accessible name. */
  label?: string;
}) {
  const id = useId();

  return (
    <div
      className={cn(
        "relative inline-flex min-h-11 items-center rounded-pill bg-sand pl-4 sm:bg-white sm:shadow-card",
        className,
      )}
    >
      <label
        htmlFor={id}
        className="mr-1.5 type-meta text-ink-2 max-sm:sr-only"
      >
        {label}:
      </label>
      <select
        id={id}
        name={name}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="min-h-11 cursor-pointer appearance-none rounded-pill bg-transparent pr-10 font-semibold text-ink [field-sizing:content]"
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      <Icon
        name="chevron-down"
        size={20}
        className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-ink"
      />
    </div>
  );
}
