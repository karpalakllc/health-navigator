"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Icon, type IconName } from "@/components/ui/icons";
import { isActivePath } from "@/components/layout/site-nav";
import { loginHref } from "@/lib/auth/login-href";
import { cn } from "@/lib/cn";
import { isPathEnabled, type ModuleFlags } from "@/lib/site-modules";
import { t, type MessageKey } from "@/i18n/t";

/** Paths that count as "searching" for the centre tab. */
const SEARCH_PATHS = [
  "/search",
  "/doctors",
  "/facilities",
  "/pharmacies",
  "/products",
];

type Tab = {
  key: string;
  href: string;
  labelKey: MessageKey;
  icon: IconName;
  active: (pathname: string) => boolean;
  /** The raised ink circle in the centre slot. */
  raised?: boolean;
  /** Module root; the tab is dropped when that module is switched off. */
  module?: string;
};

function tabsFor(isLoggedIn: boolean, pathname: string): Tab[] {
  return [
    {
      key: "home",
      href: "/",
      labelKey: "nav.home",
      icon: "home",
      active: (p) => p === "/",
    },
    {
      key: "guidance",
      href: "/guidance",
      labelKey: "nav.tabGuidance",
      icon: "compass",
      active: (p) => isActivePath(p, "/guidance"),
      module: "/guidance",
    },
    {
      key: "search",
      href: "/search",
      labelKey: "nav.tabSearch",
      icon: "search",
      raised: true,
      active: (p) => SEARCH_PATHS.some((root) => isActivePath(p, root)),
    },
    {
      key: "forum",
      href: "/forum",
      labelKey: "nav.forum",
      icon: "message-circle",
      active: (p) => isActivePath(p, "/forum"),
      module: "/forum",
    },
    {
      key: "profile",
      href: isLoggedIn ? "/account" : loginHref(pathname),
      labelKey: "nav.tabProfile",
      icon: "user",
      active: (p) =>
        isActivePath(p, "/account") ||
        isActivePath(p, "/login") ||
        isActivePath(p, "/register"),
    },
  ];
}

/**
 * Mobile bottom navigation (hidden from lg): Почетна · Насоки · [Барај] ·
 * Форум · Профил. White bar, 28px top corners, the sheet shadow, 72px plus
 * the safe-area inset. The current tab has aria-current="page", ink 600
 * label, a sand pill behind the icon and a 4px coral tick. Switched-off
 * modules are left out. Nothing pads <body>: the site footer's bottom padding
 * includes --tabbar-space (so its sand runs under the bar and its last line
 * clears it), and html's scroll-padding-bottom keeps a focused or scrolled-to
 * element above the bar (globals.css).
 */
export function BottomTabBar({
  isLoggedIn,
  modules,
}: {
  isLoggedIn: boolean;
  modules: ModuleFlags;
}) {
  const pathname = usePathname();
  const tabs = tabsFor(isLoggedIn, pathname).filter(
    (tab) => !tab.module || isPathEnabled(tab.module, modules),
  );

  return (
    <nav
      aria-label={t("nav.primary")}
      className="fixed inset-x-0 bottom-0 z-40 lg:hidden"
    >
      <div
        aria-hidden="true"
        className="absolute inset-0 rounded-t-[28px] bg-white shadow-sheet"
      />
      <ul
        className="relative m-0 grid list-none px-1 pb-[env(safe-area-inset-bottom,0px)]"
        style={{
          gridTemplateColumns: `repeat(${tabs.length}, minmax(0, 1fr))`,
        }}
      >
        {tabs.map((tab) => {
          const active = tab.active(pathname);
          const label = (
            <span
              className={cn(
                "type-tab",
                active ? "font-semibold text-ink" : "text-ink-2",
              )}
            >
              {t(tab.labelKey)}
            </span>
          );

          return (
            <li key={tab.key} className="flex justify-center">
              {tab.raised ? (
                <Link
                  href={tab.href}
                  aria-current={active ? "page" : undefined}
                  className="-mt-2 flex h-[76px] min-w-16 flex-col items-center gap-1 rounded-2xl no-underline"
                >
                  <span className="inline-flex size-14 items-center justify-center rounded-full bg-ink text-white ring-4 ring-white transition-colors hover:bg-black">
                    <Icon name="search" size={24} />
                  </span>
                  {label}
                </Link>
              ) : (
                <Link
                  href={tab.href}
                  aria-current={active ? "page" : undefined}
                  className="flex h-[72px] min-w-16 flex-col items-center justify-start gap-1.5 rounded-2xl pt-3.5 no-underline"
                >
                  <span
                    className={cn(
                      "relative inline-flex h-8 w-[60px] items-center justify-center rounded-full transition-colors",
                      active
                        ? "bg-sand text-ink"
                        : "text-ink-2 hover:bg-sand/60",
                    )}
                  >
                    <Icon name={tab.icon} size={24} />
                    {active ? (
                      <span
                        aria-hidden="true"
                        className="motion-grow-x absolute inset-x-[18px] -top-3.5 h-1 rounded-b-[4px] bg-coral"
                      />
                    ) : null}
                  </span>
                  {label}
                </Link>
              )}
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
