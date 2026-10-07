/**
 * The bounded vocabulary of the anonymous UX statistics (docs/ux-heatmaps.md).
 *
 * Everything the tracker sends is a page template, a device class, a coarse
 * width, a position bucket, a structural target key or a yes/no flag — never
 * page text, form values, keystrokes, addresses or identifiers. Mirrors
 * apps/api/app/Support/Ux/UxSchema.php; the API rejects anything else.
 */

export const UX_VIEWPORT_CLASSES = ["mobile", "tablet", "desktop"] as const;
export type UxViewportClass = (typeof UX_VIEWPORT_CLASSES)[number];

/** Viewport widths are reported rounded down to this step, in px. */
export const UX_WIDTH_STEP = 80;
export const UX_MAX_WIDTH = 3840;

/** x: whole percentage of the page width, 0–99. */
export const UX_MAX_X = 99;
/** y: 10 px band from the top of the document, 0–1999 (20 000 px). */
export const UX_Y_STEP = 10;
export const UX_MAX_Y = 1999;

export const UX_SCROLL_MILESTONES = [0, 25, 50, 75, 90, 100] as const;
export type UxScrollMilestone = (typeof UX_SCROLL_MILESTONES)[number];

/** Upper bounds (ms) of the time-to-first-click buckets 0–3; 4 is anything longer. */
export const UX_TFI_BOUNDS_MS = [1_000, 3_000, 10_000, 30_000] as const;
export const UX_MAX_TFI_BUCKET = UX_TFI_BOUNDS_MS.length;

/** `context/element`: a data-track name or landmark, then the kind of element. */
export const UX_TARGET_KEY_PATTERN =
  /^[a-z][a-z0-9-]{0,47}\/[a-z][a-z0-9-]{0,31}$/;

export const UX_MAX_CLICKS_PER_BATCH = 50;
export const UX_MAX_VIEWS_PER_BATCH = 20;

export type UxClick = {
  /** Route template, e.g. `/doctors/[slug]`. */
  r: string;
  vc: UxViewportClass;
  /** Viewport width rounded down to UX_WIDTH_STEP. */
  wb: number;
  x: number;
  /** null for a click on a fixed or sticky element (no stable page position). */
  y: number | null;
  /** Structural target key. */
  k: string;
  /** Dead click: the target does nothing when clicked. */
  d: boolean;
  /** Rage click: the third or later rapid click on the same spot. */
  g: boolean;
};

export type UxView = {
  r: string;
  vc: UxViewportClass;
  /** Deepest scroll milestone reached. */
  s: UxScrollMilestone;
  /** Time-to-first-click bucket, null when nothing was clicked. */
  t: number | null;
};

export type UxBatch = { clicks: UxClick[]; views: UxView[] };

export function viewportClass(width: number): UxViewportClass {
  if (width < 640) return "mobile";
  if (width < 1024) return "tablet";
  return "desktop";
}

export function widthBucket(width: number): number {
  const bucket = Math.floor(Math.max(0, width) / UX_WIDTH_STEP) * UX_WIDTH_STEP;
  return Math.min(UX_MAX_WIDTH, bucket);
}

export function scrollMilestone(depth: number): UxScrollMilestone {
  // 0.99: the last pixel row is often unreachable because of rounding.
  if (depth >= 0.99) return 100;
  for (const milestone of [90, 75, 50, 25] as const) {
    if (depth * 100 >= milestone) return milestone;
  }
  return 0;
}

export function tfiBucket(elapsedMs: number): number {
  const index = UX_TFI_BOUNDS_MS.findIndex((bound) => elapsedMs < bound);
  return index === -1 ? UX_MAX_TFI_BUCKET : index;
}
