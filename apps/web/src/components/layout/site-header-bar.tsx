"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCallback, useState } from "react";
import { HeaderAccountMenu } from "@/components/layout/header-account-menu";
import { HeaderSearch } from "@/components/layout/header-search";
import { MobileNavDrawer } from "@/components/layout/mobile-nav-drawer";
import { SiteBrandMark } from "@/components/layout/site-brand-mark";
import { SiteNav } from "@/components/layout/site-nav";
import type { AuthUser } from "@/lib/api/me";
import { Button, IconButton } from "@/components/ui/button";
import { loginHref } from "@/lib/auth/login-href";
import { cn } from "@/lib/cn";
import type { ModuleFlags } from "@/lib/site-modules";
import { t } from "@/i18n/t";

/** Shown while /me failed for a reason other than 401 (session kept). */
function placeholderUser(): AuthUser {
  return {
    id: 0,
    name: t("nav.account"),
    username: t("nav.account"),
    must_choose_username: false,
    username_changed_at: null,
    username_change_available_at: null,
    email: "",
    role: "member",
    avatar_url: null,
    avatar_initials: t("nav.account").charAt(0),
    profile_avatar: {
      min_messages: 10,
      message_count: 0,
      can_change: false,
    },
    community_roles: [],
  };
}

/**
 * Header rows.
 *  - Mobile (<lg, 60px): logo · menu button (opens the drawer with every
 *    section, search and the account / sign-in).
 *  - Desktop (lg+): row 1 (80px) logo · search pill · account or „Најава“;
 *    row 2 (52px) the section links.
 * No emergency pill: this is a directory, not a hospital. 194/112 live in the
 * symptom-guidance flow and as one quiet line in the footer. Exactly one <nav>
 * (row 2) sits in the banner landmark; the bottom tab bar is a separate
 * landmark outside it.
 */
export function SiteHeaderBar({
  isLoggedIn,
  logoUrl = null,
  user = null,
  modules,
}: {
  isLoggedIn: boolean;
  logoUrl?: string | null;
  user?: AuthUser | null;
  modules: ModuleFlags;
}) {
  const [mobileOpen, setMobileOpen] = useState(false);
  const closeMobile = useCallback(() => setMobileOpen(false), []);
  const pathname = usePathname();
  const accountUser = isLoggedIn ? (user ?? placeholderUser()) : null;

  return (
    <>
      <div className="mx-auto flex h-[60px] w-full max-w-[1240px] items-center justify-between gap-3 px-5 lg:grid lg:h-20 lg:grid-cols-[1fr_minmax(0,600px)_1fr] lg:gap-6 lg:px-6">
        <Link
          href="/"
          className="flex min-h-12 min-w-0 shrink items-center rounded-lg no-underline"
        >
          <SiteBrandMark logoUrl={logoUrl} />
          <span className="sr-only">, {t("nav.homeLink")}</span>
        </Link>

        <div
          data-header-search=""
          className={cn(
            "hidden lg:block",
            // Home: the hero has the same search, so the pill steps aside
            // until the hero search scrolls away (HeroSearchWatcher sets
            // <html data-hero-search>). Opacity and visibility only: the
            // header keeps its size, and while hidden it is out of the tab
            // order — the hero search is right there on screen.
            pathname === "/" &&
              "invisible opacity-0 motion-safe:transition-[opacity,visibility] motion-safe:duration-[var(--duration-base)] [html[data-hero-search=hidden]_&]:visible [html[data-hero-search=hidden]_&]:opacity-100",
          )}
        >
          <HeaderSearch />
        </div>

        <div className="flex shrink-0 items-center gap-2 lg:justify-end lg:gap-3">
          {accountUser ? (
            <div className="hidden lg:block">
              <HeaderAccountMenu user={accountUser} />
            </div>
          ) : (
            <Button
              href={loginHref(pathname)}
              size="sm"
              className="hidden px-5 lg:inline-flex"
            >
              {t("nav.login")}
            </Button>
          )}

          <IconButton
            icon="menu"
            label={t("nav.menu")}
            className="-mr-2 lg:hidden"
            onClick={() => setMobileOpen(true)}
            aria-expanded={mobileOpen}
            aria-haspopup="dialog"
          />
        </div>
      </div>

      <div className="hidden border-t border-transparent lg:block">
        <SiteNav
          modules={modules}
          className="mx-auto w-full max-w-[1240px] px-6"
        />
      </div>

      <MobileNavDrawer
        open={mobileOpen}
        onClose={closeMobile}
        isLoggedIn={isLoggedIn}
        user={accountUser}
        modules={modules}
      />
    </>
  );
}
