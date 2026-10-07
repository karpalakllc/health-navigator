import { uxRouteTemplate, type UxRoute } from "@/lib/ux/routes";
import {
  scrollMilestone,
  tfiBucket,
  UX_MAX_CLICKS_PER_BATCH,
  UX_MAX_X,
  UX_MAX_Y,
  UX_Y_STEP,
  viewportClass,
  widthBucket,
  type UxBatch,
  type UxClick,
  type UxView,
} from "@/lib/ux/schema";
import type { EarlyClick } from "@/lib/ux/early-clicks";
import { describeTarget, eventElement, inFixedBox } from "@/lib/ux/target";

/**
 * Anonymous click and scroll statistics (docs/ux-heatmaps.md).
 *
 * Per page view it keeps, in memory only: the route template, the deepest
 * scroll milestone and when the first click happened; per click: a position
 * bucket, a structural target key and two flags (dead, rage). Batches go to
 * our own /api/ux/events. There is no identifier of any kind — no cookie, no
 * storage, no session or tab id in the payload — and no text, form value,
 * keystroke or full address is ever read.
 */

/** Rage click: this many clicks… */
export const RAGE_CLICKS = 3;
/** …within this long… */
export const RAGE_WINDOW_MS = 700;
/** …and this close to each other (px). */
export const RAGE_RADIUS_PX = 30;

/** A batch is sent at this many clicks, or this long after the first one. */
export const BATCH_CLICKS = 25;
export const BATCH_DELAY_MS = 10_000;

/** Clicks recorded per page view at most; beyond that it is not a person reading. */
export const MAX_CLICKS_PER_VIEW = 300;

/** The first scroll-depth reading waits for late content (streaming, images). */
const FIRST_DEPTH_DELAY_MS = 1_500;

export const UX_EVENTS_PATH = "/api/ux/events";

type RecentClick = { at: number; x: number; y: number; burst: boolean };

/** What the page looked like at a click (now, or for an early click, then). */
type ClickMoment = {
  at: number;
  scrollX: number;
  scrollY: number;
  selected: boolean;
};

type PageView = {
  route: UxRoute;
  startedAt: number;
  depth: number;
  firstClickAt: number | null;
  clicks: number;
  reported: boolean;
};

export type TrackerOptions = {
  win: Window;
  send: (batch: UxBatch) => void;
  now?: () => number;
  /**
   * Ignore clicks a script dispatched (`element.click()`). Only tests turn
   * this off, since jsdom cannot produce trusted events.
   */
  trustedOnly?: boolean;
};

export type Tracker = {
  /** A new page view (client navigation included). */
  setPath: (pathname: string, startedAt?: number) => void;
  /**
   * Counts clicks buffered before the tracker loaded (early-clicks.ts) for
   * the current view, with their own time and scroll position; a target no
   * longer on the page is skipped.
   */
  replay: (early: EarlyClick[]) => void;
  /** Sends whatever is queued, plus the current page view. */
  flush: (endView?: boolean) => void;
  stop: () => void;
};

/** Sends with sendBeacon (survives page unload); fetch keepalive otherwise. */
export function beaconSender(win: Window): (batch: UxBatch) => void {
  return (batch) => {
    const body = JSON.stringify(batch);
    const blob = new Blob([body], { type: "application/json" });

    try {
      if (win.navigator.sendBeacon?.(UX_EVENTS_PATH, blob)) return;
    } catch {
      // Fall through to fetch.
    }

    void win
      .fetch(UX_EVENTS_PATH, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body,
        keepalive: true,
        credentials: "same-origin",
      })
      .catch(() => undefined);
  };
}

export function createTracker({
  win,
  send,
  now = () => win.performance.now(),
  trustedOnly = true,
}: TrackerOptions): Tracker {
  const doc = win.document;
  let view: PageView | null = null;
  let clicks: UxClick[] = [];
  let views: UxView[] = [];
  let recent: RecentClick[] = [];
  let timer: ReturnType<typeof setTimeout> | null = null;
  let depthTimer: ReturnType<typeof setTimeout> | null = null;
  let scrollFrame = 0;
  // A click on a <label> makes the browser click its control too; that second
  // click is the same gesture and is not counted again.
  let labelled: Element | null = null;
  // The current page's path, for a view restored from the back/forward cache.
  let pathname: string | null = null;

  const viewport = () => {
    const width = win.innerWidth;
    return { vc: viewportClass(width), wb: widthBucket(width) };
  };

  const measureDepth = () => {
    if (!view) return;
    const root = doc.documentElement;
    const height = Math.max(root.scrollHeight, doc.body?.scrollHeight ?? 0);
    const seen = win.scrollY + win.innerHeight;
    const depth = height > 0 ? Math.min(1, seen / height) : 1;
    view.depth = Math.max(view.depth, depth);
  };

  const endView = () => {
    if (!view || view.reported) return;
    measureDepth();
    view.reported = true;
    views.push({
      r: view.route,
      vc: viewport().vc,
      s: scrollMilestone(view.depth),
      t:
        view.firstClickAt === null
          ? null
          : tfiBucket(view.firstClickAt - view.startedAt),
    });
  };

  const flush = (closeView = false) => {
    if (timer) {
      clearTimeout(timer);
      timer = null;
    }
    if (closeView) endView();
    if (clicks.length === 0 && views.length === 0) return;

    while (clicks.length > 0 || views.length > 0) {
      const batch: UxBatch = {
        clicks: clicks.slice(0, UX_MAX_CLICKS_PER_BATCH),
        views: views.slice(0, 20),
      };
      clicks = clicks.slice(UX_MAX_CLICKS_PER_BATCH);
      views = views.slice(20);
      send(batch);
    }
  };

  /** Sends what is queued BATCH_DELAY_MS from now, unless already scheduled. */
  const arm = () => {
    if (!timer) timer = setTimeout(() => flush(), BATCH_DELAY_MS);
  };

  const isRage = (at: number, x: number, y: number): boolean => {
    recent = recent.filter((click) => at - click.at <= RAGE_WINDOW_MS);
    const near = recent.filter(
      (click) => Math.hypot(click.x - x, click.y - y) <= RAGE_RADIUS_PX,
    );
    const burstOpen = near.some((click) => click.burst);
    const rage = near.length + 1 >= RAGE_CLICKS;
    recent.push({ at, x, y, burst: rage });
    // One rage click per burst: the third click flags it, the fourth and
    // later ones in the same burst do not count again.
    return rage && !burstOpen;
  };

  const record = (event: MouseEvent, moment: ClickMoment) => {
    if (!view || view.clicks >= MAX_CLICKS_PER_VIEW) return;

    const el = eventElement(event.target);
    if (!el || !el.isConnected) return;

    if (labelled && el === labelled) {
      labelled = null;
      return;
    }
    const label = el.closest("label");
    labelled = label?.control ?? null;

    // Enter or Space on a control, an implicit form submission or a screen
    // reader: a click with detail 0 and no pointer position (clientX/Y are 0).
    // It counts for its target, but is not placed on the map and is never part
    // of a rage burst.
    const keyboard = event.detail === 0;

    // A drag that selected text is reading, not clicking. (Double and triple
    // clicks select words too, but those are clicks: detail > 1.)
    if (event.detail === 1 && moment.selected) return;

    const at = moment.at;
    const rage = !keyboard && isRage(at, event.clientX, event.clientY);
    const target = describeTarget(
      el,
      (node) => win.getComputedStyle(node).cursor === "pointer",
    );
    const fixed = inFixedBox(el, (node) => win.getComputedStyle(node).position);

    const docWidth = Math.max(doc.documentElement.scrollWidth, 1);
    let clientX = event.clientX;
    if (keyboard) {
      const rect = el.getBoundingClientRect();
      clientX = rect.left + rect.width / 2;
    }
    const pageX = clientX + moment.scrollX;
    const pageY = event.clientY + moment.scrollY;
    const yBucket = Math.floor(pageY / UX_Y_STEP);
    const { vc, wb } = viewport();

    view.firstClickAt ??= at;
    view.clicks += 1;
    clicks.push({
      r: view.route,
      vc,
      wb,
      x: Math.min(UX_MAX_X, Math.max(0, Math.floor((pageX / docWidth) * 100))),
      y:
        keyboard || fixed || yBucket < 0 || yBucket > UX_MAX_Y ? null : yBucket,
      k: target.key,
      d: !target.interactive,
      g: rage,
    });

    if (clicks.length >= BATCH_CLICKS) {
      flush();
    } else {
      arm();
    }
  };

  const onClick = (event: MouseEvent) => {
    if (trustedOnly && !event.isTrusted) return;
    const selection = win.getSelection?.();
    record(event, {
      at: now(),
      scrollX: win.scrollX,
      scrollY: win.scrollY,
      selected: Boolean(selection && !selection.isCollapsed),
    });
  };

  const startView = (next: string, startedAt: number) => {
    // The previous view ends with the navigation. It waits in the queue
    // with any clicks (one request per page would quickly use up a shared
    // address's limit); leaving the page still sends it at once.
    endView();
    if (views.length > 0 || clicks.length > 0) arm();
    recent = [];

    pathname = next;
    const route = uxRouteTemplate(next);
    view = route
      ? {
          route,
          startedAt,
          depth: 0,
          firstClickAt: null,
          clicks: 0,
          reported: false,
        }
      : null;

    if (depthTimer) clearTimeout(depthTimer);
    depthTimer = route ? setTimeout(measureDepth, FIRST_DEPTH_DELAY_MS) : null;
  };

  const onScroll = () => {
    if (scrollFrame) return;
    scrollFrame = win.requestAnimationFrame(() => {
      scrollFrame = 0;
      measureDepth();
    });
  };

  // Leaving the page (or the tab going to the background, which on mobile is
  // often the last chance): send everything, and close the view once.
  const onHide = () => {
    if (doc.visibilityState === "hidden") flush(true);
  };
  const onPageHide = () => flush(true);
  // Restored from the back/forward cache: the view closed on pagehide, so this
  // is a new one (clicks would otherwise join a view already reported).
  const onPageShow = (event: PageTransitionEvent) => {
    if (event.persisted && pathname !== null) startView(pathname, now());
  };

  doc.addEventListener("click", onClick, { capture: true, passive: true });
  win.addEventListener("scroll", onScroll, { passive: true });
  doc.addEventListener("visibilitychange", onHide);
  win.addEventListener("pagehide", onPageHide);
  win.addEventListener("pageshow", onPageShow);

  return {
    setPath(next, startedAt) {
      startView(next, startedAt ?? now());
    },
    replay(early) {
      for (const click of early) record(click.event, click);
    },
    flush,
    stop() {
      flush(true);
      view = null;
      if (timer) clearTimeout(timer);
      if (depthTimer) clearTimeout(depthTimer);
      if (scrollFrame) win.cancelAnimationFrame(scrollFrame);
      doc.removeEventListener("click", onClick, { capture: true });
      win.removeEventListener("scroll", onScroll);
      doc.removeEventListener("visibilitychange", onHide);
      win.removeEventListener("pagehide", onPageHide);
      win.removeEventListener("pageshow", onPageShow);
    },
  };
}
