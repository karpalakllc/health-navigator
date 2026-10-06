import { cn } from "@/lib/cn";

/** Sand placeholder block; pulses only when motion is allowed. */
export function Skeleton({ className }: { className?: string }) {
  return (
    <div
      className={cn("rounded-lg bg-sand motion-safe:animate-pulse", className)}
      aria-hidden
    />
  );
}
