/**
 * Holds the clicks made before the tracker chunk has loaded.
 *
 * The tracker loads when the browser is idle, up to 4 s after the page — but
 * the first click often comes sooner, and without it „time to first click“
 * would under-count fast visitors. This listener is part of the layout bundle
 * (no inline script, so the CSP stays strict), is registered at hydration and
 * only remembers, in memory, what the tracker itself would read at click
 * time; the tracker replays the clicks when it starts and then this stops.
 * Nothing is sent from here.
 */

export type EarlyClick = {
  event: MouseEvent;
  /** performance.now() at the click. */
  at: number;
  scrollX: number;
  scrollY: number;
  /** Text was selected (a selection drag is not a click). */
  selected: boolean;
  /** The pathname the click happened on. */
  path: string;
};

/** Enough for a first impression; a burst beyond this is not reading. */
export const MAX_EARLY_CLICKS = 20;

export type EarlyClicks = {
  /** The buffered clicks; stops listening. */
  take: () => EarlyClick[];
  stop: () => void;
};

export function bufferEarlyClicks(
  win: Window,
  path: () => string,
  { trustedOnly = true }: { trustedOnly?: boolean } = {},
): EarlyClicks {
  const doc = win.document;
  let buffered: EarlyClick[] = [];

  const onClick = (event: MouseEvent) => {
    if (trustedOnly && !event.isTrusted) return;
    if (buffered.length >= MAX_EARLY_CLICKS) return;
    const selection = win.getSelection?.();
    buffered.push({
      event,
      at: win.performance.now(),
      scrollX: win.scrollX,
      scrollY: win.scrollY,
      selected: Boolean(selection && !selection.isCollapsed),
      path: path(),
    });
  };

  const stop = () => {
    doc.removeEventListener("click", onClick, { capture: true });
  };

  doc.addEventListener("click", onClick, { capture: true, passive: true });

  return {
    take() {
      stop();
      const out = buffered;
      buffered = [];
      return out;
    },
    stop,
  };
}
