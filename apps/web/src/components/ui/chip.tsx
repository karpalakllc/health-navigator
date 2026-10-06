import Link from "next/link";
import type { ReactNode } from "react";
import { Icon, type IconName } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { tFormat } from "@/i18n/t";

/*
 * Interactive chips: 44px pills (white, 1px #857369 ring, ink 16/500).
 * Selected = ink fill, white text and a check. 44px is allowed only in rows
 * with ≥8px gaps (use `gap-2`); otherwise use Button size="md".
 */

/** A toggle (aria-pressed), e.g. „Прима нови пациенти“ quick filters. */
export function FilterChip({
  selected,
  icon,
  className,
  children,
  type = "button",
  ...rest
}: Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, "aria-pressed"> & {
  selected: boolean;
  icon?: IconName;
}) {
  return (
    <button
      type={type}
      aria-pressed={selected}
      className={cn("chip", className)}
      {...rest}
    >
      {selected ? (
        <Icon name="check" size={18} />
      ) : icon ? (
        <Icon name={icon} size={18} />
      ) : null}
      <span>{children}</span>
    </button>
  );
}

/** A chip that navigates (popular specialties, quick links). */
export function ChipLink({
  href,
  icon,
  current,
  className,
  children,
}: {
  href: string;
  icon?: IconName;
  /** Marks the chip for the current page/filter (aria-current="page"). */
  current?: boolean;
  className?: string;
  children: ReactNode;
}) {
  return (
    <Link
      href={href}
      aria-current={current ? "page" : undefined}
      className={cn("chip", className)}
    >
      {icon ? <Icon name={icon} size={18} /> : null}
      <span>{children}</span>
    </Link>
  );
}

/**
 * An applied filter with its own remove control („Кардиологија ×“). The label
 * is plain text; the × is the control, named „Отстрани филтер: …“.
 */
export function RemovableChip({
  label,
  onRemove,
  removeHref,
  className,
}: {
  label: string;
  /** Either a handler… */
  onRemove?: () => void;
  /**
   * …or a URL without this filter (works without JS). With both, the link
   * stays the no-JS fallback and a hydrated click runs `onRemove` instead.
   */
  removeHref?: string;
  className?: string;
}) {
  const removeLabel = tFormat("ui.removeFilter", { label });
  const removeClass =
    "-mr-1 inline-flex size-11 items-center justify-center rounded-full hover:bg-white/15";

  return (
    <span className={cn("chip chip-selected cursor-default pr-0", className)}>
      <span>{label}</span>
      {removeHref ? (
        <Link
          href={removeHref}
          aria-label={removeLabel}
          className={removeClass}
          onClick={
            onRemove
              ? (event) => {
                  event.preventDefault();
                  onRemove();
                }
              : undefined
          }
        >
          <Icon name="x" size={18} />
        </Link>
      ) : (
        <button
          type="button"
          aria-label={removeLabel}
          onClick={onRemove}
          className={removeClass}
        >
          <Icon name="x" size={18} />
        </button>
      )}
    </span>
  );
}
