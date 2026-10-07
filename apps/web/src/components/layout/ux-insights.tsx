"use client";

import { usePathname } from "next/navigation";
import { useEffect, useRef } from "react";
import { readOverlayToken } from "@/lib/ux/overlay-token";
import { privacySignalOn } from "@/lib/ux/privacy-signals";

/**
 * Anonymous click and scroll statistics, and the staff heatmap overlay
 * (docs/ux-heatmaps.md). Renders nothing.
 *
 * After hydration, once the browser is idle, it loads the tracker — unless the
 * browser sends Global Privacy Control or Do Not Track, or this tab falls
 * outside the sample. A tab holding a staff overlay token loads the overlay
 * instead, and is never tracked. Both are separate chunks, so neither costs
 * the first render anything.
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
  const pathRef = useRef(pathname);
  const handle = useRef<Handle | null>(null);

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
    } else if (!privacySignalOn(window) && Math.random() < uxSampleRate()) {
      const cancelIdle = whenIdle(() => {
        void import("@/lib/ux/tracker").then(
          ({ beaconSender, createTracker }) => {
            if (cancelled) return;
            const tracker = createTracker({
              win: window,
              send: beaconSender(window),
            });
            // The first view started with the navigation itself.
            tracker.setPath(pathRef.current, 0);
            handle.current = tracker;
            stop = tracker.stop;
          },
        );
      });
      stop = cancelIdle;
    }

    return () => {
      cancelled = true;
      handle.current = null;
      stop?.();
    };
  }, []);

  useEffect(() => {
    if (pathRef.current === pathname) return;
    pathRef.current = pathname;
    handle.current?.setPath(pathname);
  }, [pathname]);

  return null;
}
