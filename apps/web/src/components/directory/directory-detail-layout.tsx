import type { ReactNode } from "react";

type DirectoryDetailLayoutProps = {
  main: ReactNode;
  sidebar: ReactNode;
};

/**
 * Two-column detail template: main content + sticky sidebar (lg+).
 */
export function DirectoryDetailLayout({ main, sidebar }: DirectoryDetailLayoutProps) {
  return (
    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.1fr)_350px]">
      <div className="space-y-5">{main}</div>
      {sidebar}
    </div>
  );
}
