"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { LogoutButton } from "@/components/auth/logout-button";
import { UserAvatar } from "@/components/ui/user-avatar";
import type { AuthUser } from "@/lib/api/me";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

export function HeaderAccountMenu({ user }: { user: AuthUser }) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const router = useRouter();

  useEffect(() => {
    if (!open) {
      return;
    }

    function onDocClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }

    function onEscape(e: KeyboardEvent) {
      if (e.key === "Escape") {
        setOpen(false);
      }
    }

    document.addEventListener("mousedown", onDocClick);
    document.addEventListener("keydown", onEscape);

    return () => {
      document.removeEventListener("mousedown", onDocClick);
      document.removeEventListener("keydown", onEscape);
    };
  }, [open]);

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className={cn(
          "flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl p-0",
          "hover:opacity-90",
          open && "ring-2 ring-primary/40",
        )}
        aria-expanded={open}
        aria-haspopup="true"
        aria-label={t("nav.account")}
      >
        <UserAvatar
          name={user.name}
          avatarUrl={user.avatar_url}
          initials={user.avatar_initials}
          className="!h-10 !w-10 !rounded-xl !border-0"
        />
      </button>
      {open ? (
        <div
          className="absolute right-0 z-50 mt-2 min-w-[12rem] rounded-xl border border-border bg-card py-1 shadow-lg"
          role="menu"
        >
          <Link
            href="/account"
            className="block px-4 py-2.5 text-sm font-medium text-foreground hover:bg-secondary"
            role="menuitem"
            onClick={() => setOpen(false)}
          >
            {t("nav.account")}
          </Link>
          <Link
            href="/account/reviews"
            className="block px-4 py-2.5 text-sm text-muted-foreground hover:bg-secondary hover:text-foreground"
            role="menuitem"
            onClick={() => setOpen(false)}
          >
            {t("nav.myReviews")}
          </Link>
          <Link
            href="/account/forum"
            className="block px-4 py-2.5 text-sm text-muted-foreground hover:bg-secondary hover:text-foreground"
            role="menuitem"
            onClick={() => setOpen(false)}
          >
            {t("nav.myForum")}
          </Link>
          <div className="border-t border-border px-2 py-2">
            <LogoutButton
              className="w-full justify-center rounded-lg border border-border bg-transparent px-3 py-2 text-sm font-medium text-foreground hover:bg-secondary"
              onLoggedOut={() => {
                setOpen(false);
                router.refresh();
              }}
            />
          </div>
        </div>
      ) : null}
    </div>
  );
}
