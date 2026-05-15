import { Button } from "@/components/ui/button";
import { cn } from "@/lib/cn";

export function EmptyState({
  title,
  description,
  clearHref,
  clearLabel,
  className,
}: {
  title: string;
  description?: string;
  clearHref?: string;
  clearLabel?: string;
  className?: string;
}) {
  return (
    <div
      role="status"
      className={cn(
        "rounded-2xl border border-dashed border-border bg-card px-6 py-14 text-center",
        className,
      )}
    >
      <div
        className="mx-auto mb-5 h-14 w-14 rounded-2xl bg-gradient-to-br from-primary/15 to-accent/15 ring-1 ring-border"
        aria-hidden
      />
      <p className="text-lg font-semibold text-foreground">{title}</p>
      {description ? (
        <p className="mt-2 text-sm text-muted-foreground">{description}</p>
      ) : null}
      {clearHref && clearLabel ? (
        <div className="mt-6">
          <Button href={clearHref} variant="outline">
            {clearLabel}
          </Button>
        </div>
      ) : null}
    </div>
  );
}
