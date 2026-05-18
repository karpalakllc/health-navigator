"use client";

import type { ReactNode } from "react";
import { useState } from "react";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

type FilterFormProps = {
  children: ReactNode;
  searchHint?: string;
  action?: string;
  method?: "get" | "post";
  fieldsClassName?: string;
};

export function FilterForm({
  children,
  searchHint,
  action,
  method = "get",
  fieldsClassName,
}: FilterFormProps) {
  const [open, setOpen] = useState(false);

  return (
    <form action={action} method={method} className="space-y-3">
      <div className="flex items-center justify-between gap-3 lg:hidden">
        <button
          type="button"
          onClick={() => setOpen((value) => !value)}
          className="inline-flex min-h-[44px] flex-1 items-center justify-between rounded-xl border border-border bg-card px-4 text-sm font-medium text-foreground shadow-sm"
          aria-expanded={open}
        >
          {t("common.filter")}
          <ChevronIcon className={cn("h-4 w-4 transition", open && "rotate-180")} />
        </button>
        <button
          type="submit"
          className="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground"
        >
          {t("common.search")}
        </button>
      </div>

      <div
        className={cn(
          "rounded-2xl border border-border/90 bg-card p-4 shadow-sm",
          !open && "hidden lg:block",
        )}
      >
        <div className={cn("grid gap-3 sm:grid-cols-2 xl:grid-cols-3", fieldsClassName)}>
          {children}
        </div>
        {searchHint ? <p className="mt-3 text-xs text-muted-foreground">{searchHint}</p> : null}
        <button
          type="submit"
          className="mt-4 hidden min-h-[44px] w-full rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground sm:w-auto lg:inline-flex"
        >
          {t("common.search")}
        </button>
      </div>
    </form>
  );
}

function ChevronIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden>
      <path
        fillRule="evenodd"
        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z"
        clipRule="evenodd"
      />
    </svg>
  );
}

export function FilterField({
  label,
  children,
  hint,
}: {
  label: string;
  children: ReactNode;
  /** Reserved under the control so all filter inputs align in one row. */
  hint?: string;
}) {
  return (
    <div className="flex flex-col text-sm">
      <span className="mb-2 font-bold text-[#36414b]">{label}</span>
      {children}
      <p className="mt-2 min-h-[1.25rem] text-xs leading-snug text-[#7b8791]">{hint ?? "\u00a0"}</p>
    </div>
  );
}

export const filterInputClassName =
  "rounded-xl border border-border bg-background px-3 py-2 text-foreground shadow-sm transition-[border-color,box-shadow] duration-200 focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring/35";

export const SEARCH_QUERY_HINT = t("search.queryHint");
