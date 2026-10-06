import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
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
        "card flex flex-col items-center px-6 py-12 text-center",
        className,
      )}
    >
      <span
        aria-hidden="true"
        className="mb-5 inline-flex size-14 items-center justify-center rounded-full bg-sand text-ink"
      >
        <Icon name="search" size={26} />
      </span>
      <p className="type-h3 text-ink">{title}</p>
      {description ? (
        <p className="type-meta measure mt-2 text-ink-2">{description}</p>
      ) : null}
      {clearHref && clearLabel ? (
        <div className="mt-6">
          <Button href={clearHref} variant="secondary">
            {clearLabel}
          </Button>
        </div>
      ) : null}
    </div>
  );
}
