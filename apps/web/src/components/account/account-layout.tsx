import type { ReactNode } from "react";
import type { AccountSection } from "@/components/account/account-sub-nav";
import { AccountSubNav } from "@/components/account/account-sub-nav";

type AccountLayoutProps = {
  current: AccountSection;
  children: ReactNode;
};

export function AccountLayout({ current, children }: AccountLayoutProps) {
  return (
    <div className="flex flex-col gap-8 lg:flex-row lg:items-start">
      <AccountSubNav
        current={current}
        className="lg:sticky lg:top-24 lg:w-52 lg:shrink-0"
      />
      <div className="min-w-0 flex-1 space-y-6">{children}</div>
    </div>
  );
}
