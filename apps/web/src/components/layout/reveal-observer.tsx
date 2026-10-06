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
 * blinks. A section is also shown the moment anything inside it takes
 * keyboard focus. With reduced motion or no IntersectionObserver it does nothing.
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
      // No negative margin: a section peeking into the viewport is shown,
      // so nothing on screen stays faded out.
      { rootMargin: "0px" },
    );

    // Tabbing into a section that has not scrolled into view yet (the
    // browser scrolls it in, but the observer may not have fired) must
    // never leave the focused control invisible.
    const onFocusIn = (event: FocusEvent) => {
      let el =
        event.target instanceof Element
          ? event.target.closest('[data-reveal="pending"]')
          : null;
      while (el) {
        el.setAttribute("data-reveal", "shown");
        observer.unobserve(el);
        el = el.parentElement?.closest('[data-reveal="pending"]') ?? null;
      }
    };
    document.addEventListener("focusin", onFocusIn);

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
      document.removeEventListener("focusin", onFocusIn);
      observer.disconnect();
      // Never leave a section hidden once nobody is watching it.
      for (const el of document.querySelectorAll('[data-reveal="pending"]')) {
        el.setAttribute("data-reveal", "shown");
      }
    };
  }, [pathname]);

  return null;
}
