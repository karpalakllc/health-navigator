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
 * `hideWhileVisible` names more elements it steps aside for while any of them
 * is on screen (e.g. the review form its only button leads to, and the inline
 * „Напишете рецензија“ — either would otherwise sit under a second copy of
 * the same action).
 */
export function StickyActionBar({
  label,
  children,
  className,
  hideWhileVisible,
}: {
  /** Names the region („Брз контакт“). */
  label: string;
  children: ReactNode;
  className?: string;
  /** Ids of elements whose appearance on screen hides the bar too. */
  hideWhileVisible?: string | readonly string[];
}) {
  const [footerVisible, setFooterVisible] = useState(false);
  const [targetVisible, setTargetVisible] = useState(false);
  // A stable dependency for the effect, whether one id or a fresh array.
  const targetIds = (
    typeof hideWhileVisible === "string"
      ? [hideWhileVisible]
      : (hideWhileVisible ?? [])
  ).join(" ");

  useEffect(() => {
    const targets = targetIds
      .split(" ")
      .filter(Boolean)
      .map((id) => document.getElementById(id))
      .filter((el): el is HTMLElement => el !== null);
    if (targets.length === 0 || typeof IntersectionObserver === "undefined") {
      return;
    }

    const onScreen = new Set<Element>();
    const observer = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          onScreen.add(entry.target);
        } else {
          onScreen.delete(entry.target);
        }
      }
      setTargetVisible(onScreen.size > 0);
    });
    for (const target of targets) {
      observer.observe(target);
    }

    return () => observer.disconnect();
  }, [targetIds]);

  const hidden = footerVisible || targetVisible;

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
        data-hidden={hidden || undefined}
        className={cn(
          "fixed inset-x-0 bottom-[calc(var(--tabbar-space)+var(--consent-h,0px))] z-30 rounded-t-sheet bg-white px-5 py-3 shadow-sheet lg:hidden",
          "motion-safe:transition-transform motion-safe:duration-200",
          hidden && "invisible translate-y-[calc(100%+var(--tabbar-space))]",
          className,
        )}
      >
        <div className="mx-auto flex max-w-[40rem] gap-2">{children}</div>
      </div>
    </>
  );
}
