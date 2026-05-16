"use client";

import { useId } from "react";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

type SponsoredBadgeProps = {
  className?: string;
  size?: "sm" | "md";
};

const sizeStyles = {
  sm: "gap-1 px-2 py-0.5 text-[0.65rem]",
  md: "gap-1.5 px-2.5 py-1 text-xs",
} as const;

/**
 * Partner / paid-placement label — distinct from organic directory rows.
 */
export function SponsoredBadge({ className, size = "sm" }: SponsoredBadgeProps) {
  const tipId = useId();

  return (
    <span className={cn("group relative inline-flex shrink-0", className)}>
      <span
        className={cn(
          "inline-flex cursor-help items-center rounded-full border font-semibold leading-none tracking-tight text-primary",
          "border-primary/25 bg-gradient-to-b from-primary/[0.14] via-primary/[0.07] to-accent/[0.08]",
          "shadow-[0_1px_2px_rgb(0_0_0/0.04),inset_0_1px_0_0_rgb(255_255_255/0.55)]",
          sizeStyles[size],
        )}
        aria-describedby={tipId}
      >
        <SparkIcon className={size === "sm" ? "h-2.5 w-2.5 opacity-90" : "h-3 w-3 opacity-90"} />
        {t("doctors.sponsored")}
      </span>
      <span
        id={tipId}
        role="tooltip"
        className={cn(
          "pointer-events-auto absolute left-1/2 top-full z-[200] w-[min(20rem,calc(100vw-1.5rem))] -translate-x-1/2",
          "border border-border bg-card px-3 py-2.5 text-left text-xs font-normal leading-relaxed text-foreground shadow-lg",
          "rounded-xl",
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

function SparkIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor" aria-hidden>
      <path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.898 20.553L16.5 21.75l-.398-1.197a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.197-.398a2.25 2.25 0 001.423-1.423L16.5 15.75l.398 1.197a2.25 2.25 0 001.423 1.423L19.5 18.75l-1.197.398a2.25 2.25 0 00-1.423 1.423z" />
    </svg>
  );
}
