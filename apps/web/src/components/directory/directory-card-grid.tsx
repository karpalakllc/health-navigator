import type { ReactNode } from "react";

/** Shared responsive grid for directory list pages. */
export function DirectoryCardGrid({ children }: { children: ReactNode }) {
  return (
    <ul className="m-0 grid list-none gap-4 p-0 sm:grid-cols-2 lg:grid-cols-3">{children}</ul>
  );
}
