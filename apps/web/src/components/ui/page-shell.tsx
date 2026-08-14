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
    <main className={`${pageMainClass} ${gapClass} ${className}`.trim()}>
      {children}
    </main>
  );
}
