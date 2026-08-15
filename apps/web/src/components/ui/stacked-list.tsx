import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

/** Bordered list container for stacked rows (forum/account lists). */
export function StackedList({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <ul
      className={cn(
        "m-0 list-none divide-y divide-border rounded-xl border border-border bg-card p-0",
        className,
      )}
    >
      {children}
    </ul>
  );
}
