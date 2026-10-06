"use client";

import { usePathname } from "next/navigation";
import { useEffect } from "react";

/** The class on <html> that arms the reveal styles in globals.css. */
export const REVEAL_READY_CLASS = "js-reveal";

/**
 * Fades sections marked `data-reveal` in as they first scroll into view.
 *
 * Progressive by construction: the server HTML never hides anything. After
 * mount, only sections still below the fold are marked `pending` (faded and
 * 8px down by the CSS), and each is shown once when it enters the viewport.
 * Sections already on screen are never touched, so nothing above the fold
 * blinks. With reduced motion or no IntersectionObserver it does nothing.
 * Runs again after every client-side navigation.
 */
export function RevealObserver() {
  const pathname = usePathname();

  useEffect(() => {
    if (
      typeof IntersectionObserver === "undefined" ||
      window.matchMedia?.("(prefers-reduced-motion: reduce)").matches
    ) {
      return;
    }

    document.documentElement.classList.add(REVEAL_READY_CLASS);

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            entry.target.setAttribute("data-reveal", "shown");
            observer.unobserve(entry.target);
          }
        }
      },
      { rootMargin: "0px 0px -8% 0px" },
    );

    const viewport = window.innerHeight;
    for (const el of document.querySelectorAll<HTMLElement>(
      '[data-reveal]:not([data-reveal="shown"])',
    )) {
      if (el.getBoundingClientRect().top < viewport) {
        el.setAttribute("data-reveal", "shown");
      } else {
        el.setAttribute("data-reveal", "pending");
        observer.observe(el);
      }
    }

    return () => {
      observer.disconnect();
      // Never leave a section hidden once nobody is watching it.
      for (const el of document.querySelectorAll('[data-reveal="pending"]')) {
        el.setAttribute("data-reveal", "shown");
      }
    };
  }, [pathname]);

  return null;
}
