import type { ReactNode } from "react";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

type FilterFormProps = {
  children: ReactNode;
  searchHint?: string;
  action?: string;
  method?: "get" | "post";
  /** Merged into the inner fields grid (e.g. `xl:grid-cols-4`). */
  fieldsClassName?: string;
};

export function FilterForm({
  children,
  searchHint,
  action,
  method = "get",
  fieldsClassName,
}: FilterFormProps) {
  return (
    <form
      action={action}
      method={method}
      className="sticky top-[4.5rem] z-30 flex flex-col gap-3 rounded-2xl border border-border/90 bg-card/90 p-4 shadow-[0_12px_40px_-24px_rgb(15_23_42/0.35)] backdrop-blur-md transition-[box-shadow,transform] duration-300 focus-within:shadow-[0_16px_48px_-20px_rgb(15_23_42/0.4)] focus-within:ring-2 focus-within:ring-ring/25"
    >
      <div className={cn("grid gap-3 sm:grid-cols-2 xl:grid-cols-3", fieldsClassName)}>{children}</div>
      {searchHint ? <p className="text-xs text-muted-foreground">{searchHint}</p> : null}
      <button
        type="submit"
        className="min-h-[44px] w-full rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-[0_4px_14px_-4px_color-mix(in_srgb,var(--color-primary)_55%,transparent)] transition-[transform,box-shadow,background-color] duration-200 hover:bg-primary/92 hover:shadow-[0_6px_20px_-6px_color-mix(in_srgb,var(--color-primary)_60%,transparent)] active:scale-[0.98] motion-reduce:transition-none motion-reduce:active:scale-100 sm:w-auto sm:self-start"
      >
        {t("common.search")}
      </button>
    </form>
  );
}

export function FilterField({
  label,
  children,
}: {
  label: string;
  children: ReactNode;
}) {
  return (
    <label className="grid gap-1 text-sm">
      <span className="font-medium text-foreground">{label}</span>
      {children}
    </label>
  );
}

export const filterInputClassName =
  "rounded-xl border border-border bg-background px-3 py-2 text-foreground shadow-sm transition-[border-color,box-shadow] duration-200 focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring/35 focus:shadow-[0_0_0_3px_color-mix(in_srgb,var(--color-ring)_18%,transparent)]";

export const SEARCH_QUERY_HINT = t("search.queryHint");
