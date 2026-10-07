"use client";

import { useEffect, useState, type ReactNode } from "react";
import { cn } from "@/lib/cn";

/**
 * A white bar pinned above the mobile bottom tab bar (at --tabbar-space, never
 * bottom-0) for a page's main actions, e.g. „Јави се“ + „Насоки“ on a profile.
 * Mobile only: desktop shows the same actions in the page.
 *
 * It steps aside once the site footer scrolls into view, so it never covers
 * the footer's last lines. A spacer keeps the page's own end clear of it.
 */
export function StickyActionBar({
  label,
  children,
  className,
}: {
  /** Names the region („Брз контакт“). */
  label: string;
  children: ReactNode;
  className?: string;
}) {
  const [footerVisible, setFooterVisible] = useState(false);

  useEffect(() => {
    const footer = document.querySelector("footer");
    if (!footer || typeof IntersectionObserver === "undefined") {
      return;
    }

    const observer = new IntersectionObserver(([entry]) =>
      setFooterVisible(entry.isIntersecting),
    );
    observer.observe(footer);

    return () => observer.disconnect();
  }, []);

  return (
    <>
      <div aria-hidden="true" className="h-20 lg:hidden" />
      <div
        role="region"
        aria-label={label}
        data-sticky-action-bar
        data-hidden={footerVisible || undefined}
        className={cn(
          "fixed inset-x-0 bottom-[calc(var(--tabbar-space)+var(--consent-h,0px))] z-30 rounded-t-sheet bg-white px-5 py-3 shadow-sheet lg:hidden",
          "motion-safe:transition-transform motion-safe:duration-200",
          footerVisible &&
            "invisible translate-y-[calc(100%+var(--tabbar-space))]",
          className,
        )}
      >
        <div className="mx-auto flex max-w-[40rem] gap-2">{children}</div>
      </div>
    </>
  );
}
