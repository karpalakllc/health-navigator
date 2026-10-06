"use client";

import { useId } from "react";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

type SponsoredBadgeProps = {
  className?: string;
  size?: "sm" | "md";
};

// Neutral outline tag (ink-2 on white, 7.6:1) — sponsored must never look
// urgent, so no coral. `size` is kept for the existing callers.
const sizeStyles = {
  sm: "min-h-8 px-3 text-[0.9375rem]",
  md: "min-h-8 px-3 text-[0.9375rem]",
} as const;

/**
 * Partner / paid-placement label — distinct from organic directory rows.
 */
export function SponsoredBadge({
  className,
  size = "sm",
}: SponsoredBadgeProps) {
  const tipId = useId();

  return (
    <span className={cn("group relative inline-flex shrink-0", className)}>
      <span
        className={cn(
          "inline-flex cursor-help items-center rounded-full bg-white font-medium leading-5 text-ink-2",
          "shadow-[inset_0_0_0_1px_var(--color-line-strong)]",
          sizeStyles[size],
        )}
        aria-describedby={tipId}
      >
        {t("doctors.sponsored")}
      </span>
      <span
        id={tipId}
        role="tooltip"
        className={cn(
          "pointer-events-auto absolute left-1/2 top-full z-[200] w-[min(20rem,calc(100vw-1.5rem))] -translate-x-1/2",
          "bg-white px-4 py-3 text-left text-[0.9375rem] font-normal leading-[1.375rem] text-ink shadow-card",
          "rounded-2xl",
          "invisible opacity-0 group-hover:visible group-hover:opacity-100",
        )}
        onClick={(e) => {
          e.preventDefault();
          e.stopPropagation();
        }}
      >
        {t("doctors.sponsoredHoverExplanation")}
      </span>
    </span>
  );
}
