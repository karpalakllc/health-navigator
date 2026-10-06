"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useId, useRef } from "react";
import { createPortal } from "react-dom";
import { LogoutButton } from "@/components/auth/logout-button";
import { SiteNav } from "@/components/layout/site-nav";
import { Button, IconButton } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { UserAvatar } from "@/components/ui/user-avatar";
import type { AuthUser } from "@/lib/api/me";
import { loginHref } from "@/lib/auth/login-href";
import type { ModuleFlags } from "@/lib/site-modules";
import { t } from "@/i18n/t";

type MobileNavDrawerProps = {
  open: boolean;
  onClose: () => void;
  isLoggedIn: boolean;
  user?: AuthUser | null;
  modules: ModuleFlags;
};

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const accountLinkClass =
  "flex min-h-12 items-center rounded-xl px-2 type-body text-ink no-underline hover:bg-sand";

/**
 * The full section list on mobile (the bottom bar holds only the five main
 * destinations). A modal dialog: focus moves into it on open, Tab/Shift+Tab
 * stay inside, Escape and the backdrop close it, and focus returns to the
 * control that opened it. The page behind does not scroll while it is open.
 */
export function MobileNavDrawer({
  open,
  onClose,
  isLoggedIn,
  user = null,
  modules,
}: MobileNavDrawerProps) {
  const pathname = usePathname();
  const router = useRouter();
  const panelRef = useRef<HTMLDivElement>(null);
  const titleId = useId();

  // Close when the route changes (a link inside was followed).
  const openedAt = useRef(pathname);
  useEffect(() => {
    if (open && pathname !== openedAt.current) {
      onClose();
    }
    openedAt.current = pathname;
  }, [pathname, open, onClose]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const opener =
      document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;
    const focusables = () =>
      Array.from(
        panelRef.current?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? [],
      );

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") {
        event.preventDefault();
        onClose();
        return;
      }

      if (event.key !== "Tab") {
        return;
      }

      const items = focusables();
      if (items.length === 0) {
        event.preventDefault();
        return;
      }

      const first = items[0];
      const last = items[items.length - 1];
      const active = document.activeElement;
      const inside = panelRef.current?.contains(active) ?? false;

      if (event.shiftKey && (active === first || !inside)) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && (active === last || !inside)) {
        event.preventDefault();
        first.focus();
      }
    }

    document.addEventListener("keydown", onKeyDown);
    focusables()[0]?.focus();

    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener("keydown", onKeyDown);
      // Hand focus back to the menu button (or whatever opened the drawer).
      if (opener?.isConnected) {
        opener.focus();
      }
    };
  }, [open, onClose]);

  if (!open || typeof document === "undefined") {
    return null;
  }

  return createPortal(
    <div className="fixed inset-0 z-[200] lg:hidden">
      <div
        aria-hidden="true"
        className="motion-fade-in absolute inset-0 bg-ink/50"
        onClick={onClose}
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className="motion-drawer-in absolute inset-y-0 right-0 flex w-[min(100vw-2.5rem,22rem)] flex-col overflow-hidden rounded-l-[28px] bg-cream shadow-sheet"
      >
        <div className="flex h-[60px] shrink-0 items-center justify-between pl-5 pr-2">
          <h2 id={titleId} className="type-h3 text-ink">
            {t("nav.menu")}
          </h2>
          <IconButton icon="x" label={t("search.close")} onClick={onClose} />
        </div>

        <div className="flex-1 overflow-y-auto px-3 pb-6">
          <SiteNav layout="vertical" onNavigate={onClose} modules={modules} />

          <Link
            href="/search"
            onClick={onClose}
            className="mt-1 flex min-h-14 items-center gap-3 rounded-2xl px-2 type-body font-medium text-ink no-underline hover:bg-sand"
          >
            <span className="inline-flex size-10 items-center justify-center rounded-full bg-sand">
              <Icon name="search" size={22} />
            </span>
            <span className="flex-1">{t("nav.search")}</span>
            <Icon name="chevron-right" size={20} className="text-ink-2" />
          </Link>

          <div className="mt-4 rounded-[20px] bg-white p-4 shadow-card">
            {isLoggedIn ? (
              <>
                {user ? (
                  <div className="mb-2 flex items-center gap-3">
                    <UserAvatar
                      name={user.name}
                      avatarUrl={user.avatar_url}
                      initials={user.avatar_initials}
                    />
                    <span className="type-body font-semibold text-ink">
                      {user.display_name}
                    </span>
                  </div>
                ) : null}
                <Link
                  href="/account"
                  onClick={onClose}
                  className={accountLinkClass}
                >
                  {t("nav.account")}
                </Link>
                <Link
                  href="/account/reviews"
                  onClick={onClose}
                  className={accountLinkClass}
                >
                  {t("nav.myReviews")}
                </Link>
                <Link
                  href="/account/forum"
                  onClick={onClose}
                  className={accountLinkClass}
                >
                  {t("nav.myForum")}
                </Link>
                <LogoutButton
                  className="mt-2 w-full"
                  onLoggedOut={() => {
                    onClose();
                    router.refresh();
                  }}
                />
              </>
            ) : (
              <Button
                href={loginHref(pathname)}
                onClick={onClose}
                size="lg"
                fullWidth
              >
                {t("nav.login")}
              </Button>
            )}
          </div>
        </div>
      </div>
    </div>,
    document.body,
  );
}
