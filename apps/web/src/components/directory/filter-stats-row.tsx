import Link from "next/link";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

type FilterStatsRowProps = {
  /** Pre-formatted count line (e.g. from tFormat). */
  label: string;
  clearHref?: string;
  className?: string;
};

export function FilterStatsRow({
  label,
  clearHref,
  className,
}: FilterStatsRowProps) {
  return (
    <div
      className={cn(
        "col-span-full flex flex-col gap-2 border-b border-border/80 pb-3 sm:flex-row sm:items-center sm:justify-between",
        className,
      )}
    >
      <p
        className="text-sm tabular-nums text-muted-foreground"
        aria-live="polite"
      >
        {label}
      </p>
      {clearHref ? (
        <Link
          href={clearHref}
          className="text-sm font-medium text-primary underline-offset-4 transition hover:underline"
        >
          {t("common.clearFilters")}
        </Link>
      ) : null}
    </div>
  );
}
