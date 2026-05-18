"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { HeaderAccountMenu } from "@/components/layout/header-account-menu";
import { MobileNavDrawer } from "@/components/layout/mobile-nav-drawer";
import { SiteBrandMark } from "@/components/layout/site-brand-mark";
import { SiteNav } from "@/components/layout/site-nav";
import type { AuthUser } from "@/lib/api/me";
import { Button } from "@/components/ui/button";
import { pageContainerClass } from "@/components/ui/layout";
import { loginHref } from "@/lib/auth/login-href";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

export function SiteHeaderBar({
  isLoggedIn,
  logoUrl = null,
  user = null,
}: {
  isLoggedIn: boolean;
  logoUrl?: string | null;
  user?: AuthUser | null;
}) {
  const [mobileOpen, setMobileOpen] = useState(false);
  const pathname = usePathname();

  return (
    <>
      <div className={cn(pageContainerClass, "flex items-center gap-3 py-3", "justify-between lg:justify-start lg:gap-4")}>
        <Link
          href="/"
          className="flex min-w-0 shrink-0 items-center"
          aria-label={t("meta.title")}
        >
          <SiteBrandMark logoUrl={logoUrl} />
        </Link>

        <div className="hidden min-w-0 flex-1 items-center justify-center lg:flex">
          <SiteNav className="justify-center gap-0.5" />
        </div>

        <div className="flex shrink-0 items-center gap-1 sm:gap-2 lg:ml-auto">
          <Link
            href="/search"
            className={cn(
              "flex h-10 w-10 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground shadow-sm",
              "hover:border-primary/40 hover:bg-primary/5 hover:text-primary",
            )}
            aria-label={t("nav.searchPage")}
            title={t("nav.searchOpen")}
          >
            <SearchIcon className="h-5 w-5" />
          </Link>

          {isLoggedIn && user ? (
            <HeaderAccountMenu user={user} />
          ) : isLoggedIn ? (
            <HeaderAccountMenu
              user={{
                id: 0,
                name: t("nav.account"),
                email: "",
                role: "member",
                avatar_url: null,
                avatar_initials: t("nav.account").charAt(0),
                profile_avatar: { min_messages: 10, message_count: 0, can_change: false },
                community_roles: [],
              }}
            />
          ) : (
            <Button href={loginHref(pathname)} className="hidden h-10 px-4 text-sm sm:inline-flex">
              {t("nav.login")}
            </Button>
          )}

          <button
            type="button"
            className="flex h-10 w-10 items-center justify-center rounded-xl border border-border bg-card text-foreground lg:hidden"
            onClick={() => setMobileOpen(true)}
            aria-expanded={mobileOpen}
            aria-label={t("nav.menu")}
          >
            <MenuIcon className="h-5 w-5" />
          </button>
        </div>
      </div>

      <MobileNavDrawer
        open={mobileOpen}
        onClose={() => setMobileOpen(false)}
        isLoggedIn={isLoggedIn}
      />
    </>
  );
}

function SearchIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden>
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
      />
    </svg>
  );
}

function MenuIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
    </svg>
  );
}
