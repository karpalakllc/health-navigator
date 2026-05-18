import type { ReactNode } from "react";

type AuthSplitLayoutProps = {
  aside: ReactNode;
  children: ReactNode;
};

export function AuthSplitLayout({ aside, children }: AuthSplitLayoutProps) {
  return (
    <div className="grid gap-10 lg:grid-cols-5 lg:items-start lg:gap-14">
      <aside className="hidden min-w-0 lg:col-span-2 lg:block">{aside}</aside>
      <div className="min-w-0 space-y-6 lg:col-span-3">{children}</div>
    </div>
  );
}
