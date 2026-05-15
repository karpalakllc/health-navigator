import type { ReactNode } from "react";
import { t } from "@/i18n/t";

type FilterFormProps = {
  children: ReactNode;
  searchHint?: string;
};

export function FilterForm({ children, searchHint }: FilterFormProps) {
  return (
    <form className="sticky top-[4.5rem] z-30 flex flex-col gap-3 rounded-2xl border border-border bg-card/95 p-4 shadow-sm backdrop-blur-md">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{children}</div>
      {searchHint ? <p className="text-xs text-muted-foreground">{searchHint}</p> : null}
      <button
        type="submit"
        className="min-h-[44px] w-full rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 sm:w-auto sm:self-start"
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
  "rounded-xl border border-border bg-background px-3 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring/30";

export const SEARCH_QUERY_HINT = t("search.queryHint");
