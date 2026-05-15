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
    <div className="grid gap-8 lg:grid-cols-3">
      <div className="space-y-6 lg:col-span-2">{main}</div>
      {sidebar}
    </div>
  );
}
