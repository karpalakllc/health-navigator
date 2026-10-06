"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { LogoutButton } from "@/components/auth/logout-button";
import { UserAvatar } from "@/components/ui/user-avatar";
import type { AuthUser } from "@/lib/api/me";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

const itemClass =
  "flex min-h-12 items-center rounded-xl px-4 text-base text-ink no-underline hover:bg-sand";

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
          "flex h-11 items-center gap-2.5 rounded-full bg-sand py-1 pl-1 pr-4 text-base font-semibold text-ink hover:bg-line",
        )}
        aria-expanded={open}
        aria-haspopup="true"
        aria-label={t("nav.account")}
      >
        <UserAvatar
          name={user.name}
          avatarUrl={user.avatar_url}
          initials={user.avatar_initials}
          className="!size-9 !bg-white !text-[0.875rem]"
        />
        <span aria-hidden="true" className="max-w-[10rem] truncate">
          {user.display_name}
        </span>
      </button>
      {open ? (
        <div
          className="absolute right-0 z-50 mt-2 min-w-[14rem] rounded-2xl bg-white p-2 shadow-card"
          role="menu"
        >
          <Link
            href="/account"
            className={cn(itemClass, "font-semibold")}
            role="menuitem"
            onClick={() => setOpen(false)}
          >
            {t("nav.account")}
          </Link>
          <Link
            href="/account/reviews"
            className={itemClass}
            role="menuitem"
            onClick={() => setOpen(false)}
          >
            {t("nav.myReviews")}
          </Link>
          <Link
            href="/account/forum"
            className={itemClass}
            role="menuitem"
            onClick={() => setOpen(false)}
          >
            {t("nav.myForum")}
          </Link>
          <div className="mt-1 border-t border-line pt-2">
            <LogoutButton
              className="w-full"
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
