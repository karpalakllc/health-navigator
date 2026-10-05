import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

/**
 * Form feedback that screen readers actually hear. A plain <p> appearing after
 * submit is silent to assistive tech; role="alert" (assertive) for errors and
 * role="status" (polite) for confirmations announce it when it is inserted.
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

export function FormSuccess({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <p
      role="status"
      aria-live="polite"
      className={cn(
        "text-sm font-medium text-emerald-700 dark:text-emerald-400",
        className,
      )}
    >
      {children}
    </p>
  );
}
