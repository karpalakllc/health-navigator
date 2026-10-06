import type { ReactNode } from "react";
import { TextLink } from "@/components/ui/button";
import { cn } from "@/lib/cn";

/**
 * A section title (h2 22/28 → 30/36 by default) with an optional one-line
 * description and a right-aligned „Сите …“ link. Pass `id` and point the
 * section's aria-labelledby at it.
 */
export function SectionHeader({
  title,
  id,
  level = 2,
  description,
  action,
  className,
}: {
  title: ReactNode;
  id?: string;
  level?: 2 | 3;
  description?: ReactNode;
  /** A link shown at the right: { href, label }. Or pass any node. */
  action?: { href: string; label: ReactNode } | ReactNode;
  className?: string;
}) {
  const Heading = level === 2 ? "h2" : "h3";
  const actionNode =
    action && typeof action === "object" && "href" in action ? (
      <TextLink href={action.href} trailingIcon="arrow-right">
        {action.label}
      </TextLink>
    ) : (
      (action as ReactNode)
    );

  return (
    <div
      className={cn(
        "flex flex-wrap items-end justify-between gap-x-4 gap-y-1",
        className,
      )}
    >
      <div className="flex min-w-0 flex-col gap-1">
        <Heading
          id={id}
          className={cn(level === 2 ? "type-h2" : "type-h3", "text-ink")}
        >
          {title}
        </Heading>
        {description ? (
          <p className="type-meta text-ink-2">{description}</p>
        ) : null}
      </div>
      {actionNode ? <div className="shrink-0">{actionNode}</div> : null}
    </div>
  );
}
