/**
 * The staff heatmap overlay's pass (docs/ux-heatmaps.md).
 *
 * The admin „UX анализа“ page mints a short-lived signed token and opens the
 * public page with it in the fragment (`#ux-heatmap=…`), which browsers never
 * send to a server. It is moved into this tab's sessionStorage and removed from
 * the address bar, so the overlay follows the staff member from page to page
 * in that tab only. The token carries no data: every heatmap request is
 * re-verified by the API, and an ordinary visitor's page holds nothing.
 */
export const OVERLAY_TOKEN_KEY = "zdravje-ux-heatmap";

const FRAGMENT = /^#ux-heatmap=([A-Za-z0-9_-]{8,400}\.[A-Za-z0-9_-]{20,100})$/;
const TOKEN = /^[A-Za-z0-9_-]{8,400}\.[A-Za-z0-9_-]{20,100}$/;

type TokenWindow = Pick<Window, "location" | "history" | "sessionStorage">;

function storage(win: TokenWindow): Storage | null {
  try {
    return win.sessionStorage;
  } catch {
    return null;
  }
}

/** Reads (and adopts) the overlay token, or null on an ordinary visit. */
export function readOverlayToken(win: TokenWindow): string | null {
  const match = FRAGMENT.exec(win.location.hash);

  if (match) {
    storage(win)?.setItem(OVERLAY_TOKEN_KEY, match[1]);
    // Out of the address bar, so it is not copied along with a shared link.
    win.history.replaceState(
      win.history.state,
      "",
      win.location.pathname + win.location.search,
    );
    return match[1];
  }

  const stored = storage(win)?.getItem(OVERLAY_TOKEN_KEY) ?? null;
  return stored && TOKEN.test(stored) ? stored : null;
}

/**
 * Whether this tab is (or is about to become) a staff overlay tab, without
 * adopting the token: other counters (the review views) stay out of it too.
 */
export function hasOverlayToken(win: TokenWindow): boolean {
  if (FRAGMENT.test(win.location.hash)) return true;
  const stored = storage(win)?.getItem(OVERLAY_TOKEN_KEY) ?? null;
  return stored !== null && TOKEN.test(stored);
}

export function clearOverlayToken(win: TokenWindow): void {
  storage(win)?.removeItem(OVERLAY_TOKEN_KEY);
}
