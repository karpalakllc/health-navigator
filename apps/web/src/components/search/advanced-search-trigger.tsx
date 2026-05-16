"use client";

import { useSearchDialog } from "@/components/layout/search-dialog-context";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

type AdvancedSearchTriggerProps = {
  className?: string;
  variant?: "primary" | "outline" | "ghost";
};

export function AdvancedSearchTrigger({
  className,
  variant = "outline",
}: AdvancedSearchTriggerProps) {
  const { openAdvancedSearch } = useSearchDialog();

  const variants = {
    primary:
      "bg-primary text-primary-foreground hover:bg-primary/90 border border-transparent",
    outline: "border border-border bg-card hover:bg-secondary",
    ghost: "text-primary underline-offset-4 hover:underline border-0 bg-transparent",
  } as const;

  return (
    <button
      type="button"
      onClick={() => openAdvancedSearch()}
      className={cn(
        "inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition",
        variants[variant],
        className,
      )}
    >
      <span>{t("search.advancedButton")}</span>
      <span className="text-xs font-normal text-muted-foreground">{t("search.advancedShortHint")}</span>
    </button>
  );
}
