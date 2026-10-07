"use client";

import { useEffect } from "react";

/**
 * Brings `#id` into view once the page has mounted, when the address ends in
 * it. The browser's own fragment scroll misses a box that is not there yet:
 * arriving from another page shows the loading skeleton first (Next keeps the
 * old scroll position), and a full load can settle before hydration. Used for
 * the reply box that „Одговори“ links point at (forumAnswerHref).
 */
export function ScrollToHash({ id }: { id: string }) {
  useEffect(() => {
    if (window.location.hash !== `#${id}`) {
      return;
    }

    // scrollIntoView honours the page's scroll-padding (sticky header).
    document.getElementById(id)?.scrollIntoView({ block: "start" });
  }, [id]);

  return null;
}
