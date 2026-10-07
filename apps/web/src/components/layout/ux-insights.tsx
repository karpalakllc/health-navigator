"use client";

import { usePathname } from "next/navigation";
import { useEffect, useRef } from "react";
import { bufferEarlyClicks } from "@/lib/ux/early-clicks";
import { readOverlayToken } from "@/lib/ux/overlay-token";
import { useStatisticsConsent } from "@/lib/consent/use-consent";

/**
 * Anonymous click and scroll statistics, and the staff heatmap overlay
 * (docs/ux-heatmaps.md). Renders nothing.
 *
 * After hydration, once the browser is idle, it loads the tracker — but only
 * once the visitor accepted statistics in the consent banner (an explicit yes
 * also overrides Global Privacy Control / Do Not Track), and unless this tab
 * falls outside the sample. Withdrawing consent stops it at once. A tab holding a staff overlay token loads the overlay
 * instead, and is never tracked. Both are separate chunks, so neither costs
 * the first render anything.
 *
 * Until the tracker has loaded, a small listener holds the clicks (in memory)
 * so the first click and its timing are not lost; the tracker counts those
 * made on the page it starts on. A page left by client navigation before the
 * tracker loaded is not counted as a view (docs/ux-heatmaps.md, caveats).
 */

type Handle = { setPath: (pathname: string) => void };

/** Share of tabs tracked, 0–1 (NEXT_PUBLIC_UX_SAMPLE_RATE; default all). */
export function uxSampleRate(
  raw = process.env.NEXT_PUBLIC_UX_SAMPLE_RATE,
): number {
  if (raw === undefined || raw.trim() === "") return 1;
  const rate = Number(raw);
  return Number.isFinite(rate) ? Math.min(1, Math.max(0, rate)) : 0;
}

function whenIdle(run: () => void): () => void {
  if (typeof window.requestIdleCallback === "function") {
    const id = window.requestIdleCallback(run, { timeout: 4_000 });
    return () => window.cancelIdleCallback(id);
  }
  const id = window.setTimeout(run, 1_500);
  return () => window.clearTimeout(id);
}

export function UxInsights() {
  const pathname = usePathname();
  const statistics = useStatisticsConsent();
  const pathRef = useRef(pathname);
  const handle = useRef<Handle | null>(null);
  // performance.now() of the last navigation before the tracker loaded.
  const navigatedAt = useRef<number | null>(null);

  useEffect(() => {
    let cancelled = false;
    let stop: (() => void) | undefined;

    const token = readOverlayToken(window);

    if (token) {
      void import("@/lib/ux/overlay").then(({ mountOverlay }) => {
        if (cancelled) return;
        const overlay = mountOverlay({
          win: window,
          token,
          pathname: pathRef.current,
        });
        handle.current = overlay;
        stop = overlay.destroy;
      });
    } else if (statistics && Math.random() < uxSampleRate()) {
      const early = bufferEarlyClicks(window, () => pathRef.current);
      const cancelIdle = whenIdle(() => {
        void import("@/lib/ux/tracker").then(
          ({ beaconSender, createTracker }) => {
            if (cancelled) return;
            const tracker = createTracker({
              win: window,
              send: beaconSender(window),
            });
            // The view started with the page load itself, or with the last
            // client navigation if there was one in the meantime.
            tracker.setPath(pathRef.current, navigatedAt.current ?? 0);
            tracker.replay(
              early.take().filter((click) => click.path === pathRef.current),
            );
            handle.current = tracker;
            stop = tracker.stop;
          },
        );
      });
      stop = () => {
        cancelIdle();
        early.stop();
      };
    }

    return () => {
      cancelled = true;
      handle.current = null;
      stop?.();
    };
  }, [statistics]);

  useEffect(() => {
    if (pathRef.current === pathname) return;
    pathRef.current = pathname;
    if (handle.current) {
      handle.current.setPath(pathname);
    } else {
      navigatedAt.current = window.performance.now();
    }
  }, [pathname]);

  return null;
}
