import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

export function FilterInputWrap({
  icon,
  children,
  className,
  compact,
}: {
  icon?: ReactNode;
  children: ReactNode;
  className?: string;
  compact?: boolean;
}) {
  return (
    <div
      className={cn(
        "flex items-center gap-3 rounded-[1.125rem] border border-border bg-[#fbfcfc] px-4",
        // The control inside drops its own outline, so the wrap shows focus
        // (same ring as filterInputClassName).
        "transition-[border-color,box-shadow] duration-200 focus-within:border-primary focus-within:ring-2 focus-within:ring-ring/35",
        compact ? "min-h-[52px]" : "min-h-14",
        className,
      )}
    >
      {icon ? <span className="shrink-0 text-[#7a8691]">{icon}</span> : null}
      <div className="min-w-0 flex-1 [&_input]:w-full [&_input]:border-0 [&_input]:bg-transparent [&_input]:text-foreground [&_input]:outline-none [&_select]:w-full [&_select]:border-0 [&_select]:bg-transparent [&_select]:text-foreground [&_select]:outline-none [&_select]:appearance-none">
        {children}
      </div>
    </div>
  );
}
