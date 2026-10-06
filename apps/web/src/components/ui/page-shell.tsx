import type { ReactNode } from "react";
import { pageMainClass } from "@/components/ui/layout";

type PageShellProps = {
  children: ReactNode;
  className?: string;
  gap?: "default" | "loose";
};

export function PageShell({
  children,
  className = "",
  gap = "default",
}: PageShellProps) {
  const gapClass = gap === "loose" ? "gap-10" : "gap-8";

  return (
    // A <div>: the layout's <main id="main"> already wraps every page, and a
    // page may hold only one main landmark.
    <div className={`${pageMainClass} ${gapClass} ${className}`.trim()}>
      {children}
    </div>
  );
}
