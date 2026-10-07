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

/*
 * Target keys are `context/element`, both from closed lists, so a key can
 * never carry free text and the number of distinct keys is fixed. The lists
 * are mirrored in apps/api/app/Support/Ux/UxSchema.php (UxRoutesParityTest
 * compares the marked blocks).
 */

/** Our `data-track` names, then the landmarks, then `page` (none of those). */
export const UX_TARGET_CONTEXTS = [
  // ux-target-contexts:start
  "doctor-card",
  "facility-card",
  "pharmacy-card",
  "forum-topic",
  "site-nav",
  "tab-bar",
  "breadcrumbs",
  "pagination",
  "home-how-it-works",
  "home-forum-band",
  "home-help",
  "review-prompt",
  "header",
  "nav",
  "main",
  "footer",
  "aside",
  "search",
  "dialog",
  "form",
  "page",
  // ux-target-contexts:end
] as const;

/** Kinds of element, besides `input-<type>` and `role-<role>`. */
export const UX_TARGET_ELEMENTS = [
  // ux-target-elements:start
  "link",
  "button",
  "select",
  "textarea",
  "summary",
  "label",
  "focusable",
  "pointer",
  "disabled",
  "heading",
  "img",
  "icon",
  "text",
  "table",
  "media",
  "area",
  // ux-target-elements:end
] as const;

/** `input-<type>`; any other type is `input-other`. */
export const UX_INPUT_TYPES = [
  // ux-input-types:start
  "text",
  "search",
  "email",
  "password",
  "tel",
  "url",
  "number",
  "checkbox",
  "radio",
  "range",
  "date",
  "time",
  "file",
  "submit",
  "button",
  "reset",
  // ux-input-types:end
] as const;

/** ARIA roles that make an element interactive: `role-<role>`, except button and link. */
export const UX_INTERACTIVE_ROLES = [
  // ux-interactive-roles:start
  "button",
  "link",
  "checkbox",
  "radio",
  "switch",
  "tab",
  "menuitem",
  "menuitemcheckbox",
  "menuitemradio",
  "option",
  "combobox",
  "slider",
  "spinbutton",
  "textbox",
  "searchbox",
  "treeitem",
  // ux-interactive-roles:end
] as const;

const TARGET_CONTEXTS = new Set<string>(UX_TARGET_CONTEXTS);
const TARGET_ELEMENTS = new Set<string>([
  ...UX_TARGET_ELEMENTS,
  ...UX_INPUT_TYPES.map((type) => `input-${type}`),
  "input-other",
  ...UX_INTERACTIVE_ROLES.filter(
    (role) => role !== "button" && role !== "link",
  ).map((role) => `role-${role}`),
]);

/** A `data-track` name the statistics know (any other is ignored). */
export function isUxTrackName(value: string): boolean {
  return TARGET_CONTEXTS.has(value);
}

/** `context/element` with both parts from the closed lists. */
export function isUxTargetKey(value: unknown): value is string {
  if (typeof value !== "string") return false;
  const slash = value.indexOf("/");
  return (
    slash > 0 &&
    TARGET_CONTEXTS.has(value.slice(0, slash)) &&
    TARGET_ELEMENTS.has(value.slice(slash + 1))
  );
}

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
