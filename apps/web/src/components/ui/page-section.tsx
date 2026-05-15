import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

type PageSectionProps = {
  title?: ReactNode;
  description?: ReactNode;
  /** e.g. “see all” link aligned with the title on larger screens */
  actions?: ReactNode;
  children: ReactNode;
  className?: string;
  headerClassName?: string;
};

/**
 * Standard vertical rhythm for stacked sections (title + optional blurb + body).
 */
export function PageSection({
  title,
  description,
  actions,
  children,
  className,
  headerClassName,
}: PageSectionProps) {
  return (
    <section className={cn("space-y-4", className)}>
      {title != null || description != null || actions != null ? (
        <header
          className={cn(
            "flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between sm:gap-4",
            headerClassName,
          )}
        >
          {title != null || description != null ? (
            <div className="space-y-1">
              {title != null ? <h2 className="text-xl font-semibold tracking-tight">{title}</h2> : null}
              {description != null ? <p className="text-sm text-muted-foreground">{description}</p> : null}
            </div>
          ) : null}
          {actions != null ? <div className="shrink-0">{actions}</div> : null}
        </header>
      ) : null}
      {children}
    </section>
  );
}
