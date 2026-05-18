"use client";

import { useEffect } from "react";
import { createPortal } from "react-dom";
import { SiteNav } from "@/components/layout/site-nav";
import Link from "next/link";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

type MobileNavDrawerProps = {
  open: boolean;
  onClose: () => void;
  isLoggedIn: boolean;
};

export function MobileNavDrawer({ open, onClose, isLoggedIn }: MobileNavDrawerProps) {
  useEffect(() => {
    if (!open) {
      return;
    }

    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") {
        onClose();
      }
    }

    document.addEventListener("keydown", onKeyDown);

    return () => {
      document.body.style.overflow = previous;
      document.removeEventListener("keydown", onKeyDown);
    };
  }, [open, onClose]);

  if (!open || typeof document === "undefined") {
    return null;
  }

  return createPortal(
    <div className="fixed inset-0 z-[200] lg:hidden" role="dialog" aria-modal="true" aria-label={t("nav.menu")}>
      <button
        type="button"
        className="absolute inset-0 bg-black/50 backdrop-blur-sm"
        aria-label={t("search.close")}
        onClick={onClose}
      />
      <div
        className={cn(
          "absolute right-0 top-0 flex h-full w-[min(100vw-2.5rem,20rem)] flex-col",
          "border-l border-border bg-card shadow-2xl",
        )}
      >
        <div className="flex items-center justify-between border-b border-border px-4 py-3">
          <span className="font-semibold text-foreground">{t("nav.menu")}</span>
          <button
            type="button"
            className="rounded-lg p-2 text-muted-foreground hover:bg-secondary"
            onClick={onClose}
            aria-label={t("search.close")}
          >
            <CloseIcon className="h-5 w-5" />
          </button>
        </div>
        <div className="flex-1 overflow-y-auto p-3">
          <SiteNav layout="vertical" className="gap-0.5" onNavigate={onClose} />
        </div>
        {!isLoggedIn ? (
          <div className="border-t border-border p-4">
            <Link
              href="/login"
              onClick={onClose}
              className={cn(
                "inline-flex w-full items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm hover:bg-primary/90",
              )}
            >
              {t("nav.login")}
            </Link>
          </div>
        ) : null}
      </div>
    </div>,
    document.body,
  );
}

function CloseIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
  );
}
