import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

/**
 * Form feedback that screen readers actually hear. A plain <p> appearing after
 * submit is silent to assistive tech; role="alert" (assertive) for errors and
 * FormSuccess (role="status", polite) for confirmations, kept mounted.
 */
export function FormError({
  children,
  id,
  className,
}: {
  children: ReactNode;
  id?: string;
  className?: string;
}) {
  return (
    <p
      id={id}
      role="alert"
      className={cn("text-sm text-destructive", className)}
    >
      {children}
    </p>
  );
}

/**
 * A polite live region that is always in the DOM. Screen readers watch a
 * region for *changes*; one inserted already holding its text is often not
 * announced at all. So render this unconditionally and pass the message as
 * children once there is one — empty, it is visually hidden and out of flow.
 */
export function FormSuccess({
  children,
  className,
  tone = "success",
}: {
  children?: ReactNode;
  className?: string;
  tone?: "success" | "muted";
}) {
  const filled =
    children !== null && children !== undefined && children !== false;

  return (
    <p
      role="status"
      aria-live="polite"
      className={
        filled
          ? cn(
              "text-sm",
              tone === "success"
                ? "font-medium text-emerald-700 dark:text-emerald-400"
                : "text-muted-foreground",
              className,
            )
          : "sr-only"
      }
    >
      {filled ? children : null}
    </p>
  );
}
