import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

/** Forum pages: 1240 container, 20px mobile gutter, 24px desktop. */
export const forumPageClass =
  "mx-auto flex w-full min-w-0 max-w-[1240px] flex-col overflow-x-clip px-5 pt-4 pb-14 lg:px-6 lg:pt-8 lg:pb-20";

/** 8 + 4 columns on desktop; one column (main, then aside) on mobile. */
export function ForumColumns({
  main,
  aside,
  className,
}: {
  main: ReactNode;
  aside?: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "grid grid-cols-[minmax(0,1fr)] items-start gap-8 lg:grid-cols-[minmax(0,8fr)_minmax(0,4fr)] lg:gap-6",
        className,
      )}
    >
      <div className="flex min-w-0 flex-col gap-6">{main}</div>
      {aside}
    </div>
  );
}

/** Nothing to list: a calm white card with an optional way back. */
export function ForumEmpty({
  title,
  action,
}: {
  title: ReactNode;
  action?: ReactNode;
}) {
  return (
    <div className="card flex flex-col items-start gap-4 p-6">
      <p className="type-body text-ink">{title}</p>
      {action}
    </div>
  );
}

/** Page head: H1, an optional lead and meta line, actions on the right. */
export function ForumPageHead({
  title,
  lead,
  meta,
  actions,
  above,
}: {
  title: ReactNode;
  lead?: ReactNode;
  meta?: ReactNode;
  actions?: ReactNode;
  /** Tags or a back pill shown above the title. */
  above?: ReactNode;
}) {
  return (
    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between lg:gap-8">
      <div className="flex min-w-0 flex-col gap-2">
        {above}
        <h1 className="type-h1 text-ink">{title}</h1>
        {lead ? <p className="measure type-body text-ink-2">{lead}</p> : null}
        {meta ? <p className="type-meta text-ink-2">{meta}</p> : null}
      </div>
      {actions ? <div className="shrink-0">{actions}</div> : null}
    </div>
  );
}
