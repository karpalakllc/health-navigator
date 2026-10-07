import { isUxRoute } from "@/lib/ux/routes";
import {
  UX_MAX_CLICKS_PER_BATCH,
  UX_MAX_TFI_BUCKET,
  UX_MAX_VIEWS_PER_BATCH,
  UX_MAX_WIDTH,
  UX_MAX_X,
  UX_MAX_Y,
  UX_SCROLL_MILESTONES,
  UX_TARGET_KEY_PATTERN,
  UX_VIEWPORT_CLASSES,
  UX_WIDTH_STEP,
  type UxBatch,
  type UxClick,
  type UxView,
} from "@/lib/ux/schema";

/**
 * Rebuilds a tracker batch from untrusted JSON, field by field: only known
 * keys with in-range values are copied, so nothing else (free text, an
 * identifier, a full address) can ride along to the API. Invalid entries are
 * dropped rather than failing the batch; null when nothing valid is left.
 */

function int(value: unknown, min: number, max: number): number | null {
  return typeof value === "number" &&
    Number.isInteger(value) &&
    value >= min &&
    value <= max
    ? value
    : null;
}

function viewportOk(value: unknown): value is UxClick["vc"] {
  return (UX_VIEWPORT_CLASSES as readonly unknown[]).includes(value);
}

function click(raw: unknown): UxClick | null {
  if (!raw || typeof raw !== "object") return null;
  const c = raw as Record<string, unknown>;
  const wb = int(c.wb, 0, UX_MAX_WIDTH);
  const x = int(c.x, 0, UX_MAX_X);
  const y = c.y === null ? null : int(c.y, 0, UX_MAX_Y);

  if (
    !isUxRoute(c.r) ||
    !viewportOk(c.vc) ||
    wb === null ||
    wb % UX_WIDTH_STEP !== 0 ||
    x === null ||
    (c.y !== null && y === null) ||
    typeof c.k !== "string" ||
    !UX_TARGET_KEY_PATTERN.test(c.k) ||
    typeof c.d !== "boolean" ||
    typeof c.g !== "boolean"
  ) {
    return null;
  }

  return { r: c.r, vc: c.vc, wb, x, y, k: c.k, d: c.d, g: c.g };
}

function view(raw: unknown): UxView | null {
  if (!raw || typeof raw !== "object") return null;
  const v = raw as Record<string, unknown>;
  const t = v.t === null ? null : int(v.t, 0, UX_MAX_TFI_BUCKET);

  if (
    !isUxRoute(v.r) ||
    !viewportOk(v.vc) ||
    !(UX_SCROLL_MILESTONES as readonly unknown[]).includes(v.s) ||
    (v.t !== null && t === null)
  ) {
    return null;
  }

  return { r: v.r, vc: v.vc, s: v.s as UxView["s"], t };
}

export function parseUxBatch(value: unknown): UxBatch | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) return null;
  const raw = value as Record<string, unknown>;
  const rawClicks = Array.isArray(raw.clicks) ? raw.clicks : [];
  const rawViews = Array.isArray(raw.views) ? raw.views : [];

  const clicks = rawClicks
    .slice(0, UX_MAX_CLICKS_PER_BATCH)
    .map(click)
    .filter((entry): entry is UxClick => entry !== null);
  const views = rawViews
    .slice(0, UX_MAX_VIEWS_PER_BATCH)
    .map(view)
    .filter((entry): entry is UxView => entry !== null);

  return clicks.length > 0 || views.length > 0 ? { clicks, views } : null;
}
