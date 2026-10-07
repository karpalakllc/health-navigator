"use client";

import { useEffect, useRef, type ReactNode } from "react";
import { hasOverlayToken } from "@/lib/ux/overlay-token";
import { statisticsAllowed } from "@/lib/consent/consent";
import { useStatisticsConsent } from "@/lib/consent/use-consent";
import { statisticsHeaders } from "@/lib/consent/header";

/** How long after the first card appears the batch goes out. */
const FLUSH_MS = 2000;

/** The API takes at most this many ids per report. */
const MAX_BATCH = 30;

/**
 * „Прикажана N пати“ (W8-B): reports which review cards (data-review-view-id)
 * were at least half on the visitor's screen, in one small batch per page
 * view. In memory only — no cookie or storage; the API counts each review at
 * most once a day per network and never the author's own views. Pages a
 * crawler fetches without running scripts, or that nobody scrolls to, count
 * nothing. Like the UX tracker, it runs only after the visitor accepted
 * statistics (withdrawing stops it at once; an explicit yes overrides Global
 * Privacy Control / Do Not Track), and a staff heatmap-overlay tab counts
 * nothing either.
 */
export function ReviewViewTracker({
  children,
  excludeId = null,
}: {
  children: ReactNode;
  /** The viewer's own review: never reported. */
  excludeId?: number | null;
}) {
  const container = useRef<HTMLDivElement>(null);
  // Across re-renders (a new page of reviews re-runs the effect): each card
  // is reported at most once per page view.
  const reportedRef = useRef(new Set<number>());
  const statistics = useStatisticsConsent();

  useEffect(() => {
    const root = container.current;

    if (
      !root ||
      typeof IntersectionObserver === "undefined" ||
      !statistics ||
      hasOverlayToken(window)
    ) {
      return;
    }

    const reported = reportedRef.current;
    let queue: number[] = [];
    let timer: ReturnType<typeof setTimeout> | null = null;

    function flush() {
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }

      const ids = queue.slice(0, MAX_BATCH);
      queue = queue.slice(MAX_BATCH);

      if (ids.length === 0) {
        return;
      }

      // Checked at sending: a report queued before consent was withdrawn is
      // dropped.
      if (!statisticsAllowed()) {
        return;
      }

      // keepalive: the report survives the visitor leaving the page.
      void fetch("/api/reviews/views", {
        method: "POST",
        headers: { "Content-Type": "application/json", ...statisticsHeaders() },
        body: JSON.stringify({ ids }),
        keepalive: true,
      }).catch(() => {});

      if (queue.length > 0) {
        flush();
      }
    }

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          // „At least half“: the first callback, or a card barely on screen,
          // arrives intersecting with a smaller ratio. Keep watching it.
          if (!entry.isIntersecting || entry.intersectionRatio < 0.5) {
            continue;
          }

          const id = Number((entry.target as HTMLElement).dataset.reviewViewId);
          observer.unobserve(entry.target);

          if (
            !Number.isSafeInteger(id) ||
            id <= 0 ||
            id === excludeId ||
            reported.has(id)
          ) {
            continue;
          }

          reported.add(id);
          queue.push(id);
          timer ??= setTimeout(flush, FLUSH_MS);
        }
      },
      { threshold: 0.5 },
    );

    for (const card of root.querySelectorAll<HTMLElement>(
      "[data-review-view-id]",
    )) {
      observer.observe(card);
    }

    const onHide = () => {
      if (document.visibilityState === "hidden") {
        flush();
      }
    };
    document.addEventListener("visibilitychange", onHide);
    window.addEventListener("pagehide", flush);

    return () => {
      observer.disconnect();
      document.removeEventListener("visibilitychange", onHide);
      window.removeEventListener("pagehide", flush);
      flush();
    };
  }, [children, excludeId, statistics]);

  return <div ref={container}>{children}</div>;
}
