import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

export type TrustItem = {
  text: string;
  icon: ReactNode;
  tone?: "teal" | "red";
};

export function TrustRibbon({
  items,
  className,
  columns = 4,
  variant = "default",
}: {
  items: TrustItem[];
  className?: string;
  columns?: 3 | 4;
  variant?: "default" | "compact";
}) {
  if (variant === "compact") {
    return (
      <ul className={cn("mt-3.5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3", className)}>
        {items.map((item) => (
          <li
            key={item.text}
            className="trust-badge-compact flex items-center gap-3 px-3.5 py-2"
          >
            <span
              className={cn(
                "inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl",
                item.tone === "red" ? "icon-soft-red" : "icon-soft-teal",
              )}
            >
              {item.icon}
            </span>
            <span className="text-sm font-semibold leading-snug text-[#586570]">{item.text}</span>
          </li>
        ))}
      </ul>
    );
  }

  return (
    <ul
      className={cn(
        "grid gap-3",
        columns === 4 ? "sm:grid-cols-2 xl:grid-cols-4" : "sm:grid-cols-2 lg:grid-cols-3",
        className,
      )}
    >
      {items.map((item) => (
        <li
          key={item.text}
          className="surface-glass flex min-h-[58px] items-center gap-3 rounded-[1.25rem] px-3.5 py-2"
        >
          <span
            className={cn(
              "inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl",
              item.tone === "red" ? "icon-soft-red" : "icon-soft-teal",
            )}
          >
            {item.icon}
          </span>
          <span className="text-sm font-semibold leading-snug text-[#586570]">{item.text}</span>
        </li>
      ))}
    </ul>
  );
}
