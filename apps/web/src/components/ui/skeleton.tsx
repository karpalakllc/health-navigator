import { cn } from "@/lib/cn";

/** Sand placeholder block; a light band sweeps it only when motion is allowed. */
export function Skeleton({ className }: { className?: string }) {
  return (
    <div
      className={cn("skeleton-shimmer rounded-lg bg-sand", className)}
      aria-hidden
    />
  );
}
