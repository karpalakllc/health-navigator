import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

type PageSectionProps = {
  title?: ReactNode;
  description?: ReactNode;
  /** e.g. “see all” link aligned with the title on larger screens */
  actions?: ReactNode;
  children: ReactNode;
  /** Framed block for emphasis on the home page and similar. */
  variant?: "default" | "panel";
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
  variant = "default",
  className,
  headerClassName,
}: PageSectionProps) {
  return (
    <section
      className={cn(
        "space-y-4",
        variant === "panel" &&
          "section-panel rounded-3xl border border-border/80 bg-card/35 p-6 shadow-[0_1px_0_0_color-mix(in_srgb,var(--color-border)_70%,transparent)] backdrop-blur-md sm:p-8",
        className,
      )}
    >
      {title != null || description != null || actions != null ? (
        <header
          className={cn(
            "flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between sm:gap-4",
            headerClassName,
          )}
        >
          {title != null || description != null ? (
            <div className="space-y-1">
              {title != null ? (
                <h2 className="text-xl font-semibold tracking-tight">
                  {variant === "panel" ? (
                    <span className="heading-accent">{title}</span>
                  ) : (
                    title
                  )}
                </h2>
              ) : null}
              {description != null ? (
                <p className="text-sm text-muted-foreground">{description}</p>
              ) : null}
            </div>
          ) : null}
          {actions != null ? <div className="shrink-0">{actions}</div> : null}
        </header>
      ) : null}
      {children}
    </section>
  );
}
