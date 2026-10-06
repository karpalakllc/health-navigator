import type { ReactNode } from "react";
import {
  AccountSubNav,
  type AccountSection,
} from "@/components/account/account-sub-nav";

type AccountLayoutProps = {
  current: AccountSection;
  children: ReactNode;
};

/** Section nav (chips on a phone, a sticky side list on desktop) + content. */
export function AccountLayout({ current, children }: AccountLayoutProps) {
  return (
    <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-10">
      <AccountSubNav
        current={current}
        className="lg:sticky lg:top-[calc(var(--header-h)+1.5rem)] lg:w-64 lg:shrink-0"
      />
      <div className="flex min-w-0 flex-1 flex-col gap-6">{children}</div>
    </div>
  );
}

/**
 * The account pages' container: the 1240 column with the 20px mobile gutter.
 * The page header (AccountPageHero) and AccountLayout go inside.
 */
export function AccountPage({ children }: { children: ReactNode }) {
  return (
    <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-5 pb-14 pt-6 lg:gap-10 lg:px-6 lg:pb-20 lg:pt-10">
      {children}
    </div>
  );
}
