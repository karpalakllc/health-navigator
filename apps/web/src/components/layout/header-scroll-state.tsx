"use client";

import { useEffect } from "react";

/**
 * Marks the sticky header once the page has scrolled (data-scrolled), so the
 * hairline under it appears only when content passes beneath.
 */
export function HeaderScrollState() {
  useEffect(() => {
    const header = document.getElementById("site-header");
    if (!header) {
      return;
    }

    const update = () => {
      header.toggleAttribute("data-scrolled", window.scrollY > 4);
    };

    update();
    window.addEventListener("scroll", update, { passive: true });

    return () => window.removeEventListener("scroll", update);
  }, []);

  return null;
}
