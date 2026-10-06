"use client";

import { useEffect } from "react";
import { HERO_SEARCH_ATTR } from "@/components/home/hero-search-ids";

/**
 * Tells the header whether the hero search is on screen, through
 * `<html data-hero-search="visible|hidden">`, so the desktop header pill
 * steps aside while the hero offers the same search and fades in once the
 * hero search has scrolled under the sticky header. Removed on leaving the
 * page. Without IntersectionObserver it reports "hidden" (pill shown).
 */
export function HeroSearchWatcher({ targetId }: { targetId: string }) {
  useEffect(() => {
    const root = document.documentElement;
    const target = document.getElementById(targetId);

    if (!target || typeof IntersectionObserver === "undefined") {
      root.setAttribute(HERO_SEARCH_ATTR, "hidden");
      return () => root.removeAttribute(HERO_SEARCH_ATTR);
    }

    // The sticky header covers the top of the viewport: a hero search
    // behind it is out of view.
    const headerHeight = document.getElementById("site-header")?.offsetHeight;
    const observer = new IntersectionObserver(
      (entries) => {
        const entry = entries.at(-1);
        if (entry) {
          root.setAttribute(
            HERO_SEARCH_ATTR,
            entry.isIntersecting ? "visible" : "hidden",
          );
        }
      },
      { rootMargin: `-${headerHeight ?? 0}px 0px 0px 0px` },
    );
    observer.observe(target);

    return () => {
      observer.disconnect();
      root.removeAttribute(HERO_SEARCH_ATTR);
    };
  }, [targetId]);

  return null;
}
