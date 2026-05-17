"use client";

import Link from "next/link";
import { useState } from "react";
import { HeaderAccountMenu } from "@/components/layout/header-account-menu";
import { SiteBrandMark } from "@/components/layout/site-brand-mark";
import { SiteNav } from "@/components/layout/site-nav";
import type { AuthUser } from "@/lib/api/me";
import { Button } from "@/components/ui/button";
import { pageContainerClass } from "@/components/ui/layout";
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

  return (
    <>
      <div
        className={cn(
          pageContainerClass,
          "flex items-center gap-3 py-3",
          "justify-between lg:justify-start lg:gap-4",
        )}
      >
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
              }}
            />
          ) : (
            <Button href="/login" className="hidden h-10 px-4 text-sm sm:inline-flex">
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

      {mobileOpen ? (
        <div className="fixed inset-0 z-[90] lg:hidden" role="presentation">
          <button
            type="button"
            className="absolute inset-0 bg-black/40 backdrop-blur-sm"
            aria-label={t("search.close")}
            onClick={() => setMobileOpen(false)}
          />
          <div className="absolute right-0 top-0 flex h-full w-[min(100vw-3rem,20rem)] flex-col border-l border-border bg-card shadow-xl">
            <div className="flex items-center justify-between border-b border-border px-4 py-3">
              <span className="font-semibold text-foreground">{t("nav.menu")}</span>
              <button
                type="button"
                className="rounded-lg p-2 text-muted-foreground hover:bg-secondary"
                onClick={() => setMobileOpen(false)}
                aria-label={t("search.close")}
              >
                <CloseIcon className="h-5 w-5" />
              </button>
            </div>
            <div className="flex-1 overflow-y-auto p-3">
              <SiteNav
                layout="vertical"
                className="gap-0.5"
                onNavigate={() => setMobileOpen(false)}
              />
            </div>
            {!isLoggedIn ? (
              <div className="border-t border-border p-4">
                <Button href="/login" className="w-full justify-center">
                  {t("nav.login")}
                </Button>
              </div>
            ) : null}
          </div>
        </div>
      ) : null}
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

function CloseIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
  );
}
