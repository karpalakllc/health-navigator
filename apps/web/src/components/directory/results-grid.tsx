import type { ReactNode } from "react";

/** Result cards: one column on phones, two inside the 8-column results area. */
export function ResultsGrid({ children }: { children: ReactNode }) {
  return (
    <ul className="m-0 grid list-none grid-cols-1 gap-3 p-0 md:grid-cols-2 lg:gap-5">
      {children}
    </ul>
  );
}
