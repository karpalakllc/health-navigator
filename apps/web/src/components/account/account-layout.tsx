import type { ReactNode } from "react";
import {
  AccountSubNav,
  type AccountSection,
} from "@/components/account/account-sub-nav";
import { fetchManagedDoctor } from "@/lib/api/me";

type AccountLayoutProps = {
  current: AccountSection;
  children: ReactNode;
};

/**
 * Section nav (chips on a phone, a sticky side list on desktop) + content.
 * „Мој профил“ appears only for an account that manages a doctor profile
 * (one cached /me per request).
 */
export async function AccountLayout({ current, children }: AccountLayoutProps) {
  const managedDoctor = await fetchManagedDoctor();

  return (
    <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-10">
      <AccountSubNav
        current={current}
        showDoctor={managedDoctor !== null}
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
