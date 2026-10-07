import { mk } from "@/i18n/mk";
import {
  UX_HEATMAP_PATH,
  UX_OVERLAY_TOKEN_HEADER,
  type UxHeatCell,
  type UxHeatmapData,
} from "@/lib/ux/overlay-api";
import { clearOverlayToken } from "@/lib/ux/overlay-token";
import { uxRouteTemplate } from "@/lib/ux/routes";
import {
  viewportClass,
  widthBucket,
  type UxViewportClass,
} from "@/lib/ux/schema";

/**
 * The staff-only heatmap layer drawn over the public page
 * (docs/ux-heatmaps.md). Loaded as its own chunk, and only when the tab holds
 * an overlay token: an ordinary visit never downloads it, and the page itself
 * carries no heatmap data — it is fetched with the token, which the API checks
 * on every request.
 *
 * Plain DOM rather than React: it sits above the app, must not affect its
 * layout or state, and goes away completely on „Затвори“.
 */

export type HeatMode = "all" | "dead" | "rage";

export type HeatPoint = { x: number; y: number; weight: number };

const LAYER_ID = "ux-heatmap-overlay";
const SCROLL_LINES = [25, 50, 75, 90] as const;

function value(cell: UxHeatCell, mode: HeatMode): number {
  if (mode === "dead") return cell.dead;
  if (mode === "rage") return cell.rage;
  return cell.clicks;
}

/**
 * Cells → points in viewport pixels, weighted 0–1 against the busiest cell.
 * Only cells within `margin` of the viewport are returned.
 */
export function heatPoints(
  cells: UxHeatCell[],
  mode: HeatMode,
  view: {
    docWidth: number;
    scrollX: number;
    scrollY: number;
    width: number;
    height: number;
    xBuckets: number;
    yStep: number;
  },
  margin = 40,
): HeatPoint[] {
  const max = Math.max(0, ...cells.map((cell) => value(cell, mode)));
  if (max === 0) return [];

  const points: HeatPoint[] = [];

  for (const cell of cells) {
    const v = value(cell, mode);
    if (v === 0) continue;

    const x = ((cell.x + 0.5) / view.xBuckets) * view.docWidth - view.scrollX;
    const y = cell.y * view.yStep + view.yStep / 2 - view.scrollY;

    if (
      x < -margin ||
      y < -margin ||
      x > view.width + margin ||
      y > view.height + margin
    ) {
      continue;
    }

    points.push({ x, y, weight: v / max });
  }

  return points;
}

/** Blue → green → yellow → red, by intensity 0–255. */
function palette(): Uint8ClampedArray {
  const stops: [number, [number, number, number]][] = [
    [0, [0, 0, 255]],
    [0.35, [0, 200, 255]],
    [0.55, [0, 220, 0]],
    [0.75, [255, 230, 0]],
    [1, [255, 0, 0]],
  ];
  const out = new Uint8ClampedArray(256 * 3);

  for (let i = 0; i < 256; i++) {
    const p = i / 255;
    const upper = stops.findIndex(([at]) => at >= p);
    const [a, ca] = stops[Math.max(0, upper - 1)];
    const [b, cb] = stops[Math.max(0, upper)];
    const f = b === a ? 0 : (p - a) / (b - a);
    for (let c = 0; c < 3; c++) out[i * 3 + c] = ca[c] + (cb[c] - ca[c]) * f;
  }

  return out;
}

function format(template: string, values: Record<string, string>): string {
  return template.replace(/\{(\w+)\}/g, (_, key: string) => values[key] ?? "");
}

export type Overlay = {
  setPath: (pathname: string) => void;
  destroy: () => void;
};

export function mountOverlay({
  win,
  token,
  pathname,
}: {
  win: Window;
  token: string;
  pathname: string;
}): Overlay {
  const doc = win.document;
  const text = mk.uxOverlay;
  doc.getElementById(LAYER_ID)?.remove();

  const layer = doc.createElement("div");
  layer.id = LAYER_ID;
  layer.setAttribute("data-ux-ignore", "");
  layer.style.cssText =
    "position:fixed;inset:0;z-index:2147483000;pointer-events:none;";

  const canvas = doc.createElement("canvas");
  canvas.setAttribute("aria-hidden", "true");
  canvas.style.cssText = "position:absolute;inset:0;width:100%;height:100%;";
  layer.appendChild(canvas);

  const panel = doc.createElement("div");
  panel.setAttribute("role", "region");
  panel.setAttribute("aria-label", text.title);
  panel.style.cssText = [
    "position:absolute;left:12px;bottom:calc(12px + var(--tabbar-space, 0px));max-width:min(360px,calc(100vw - 24px))",
    "pointer-events:auto;background:#1f1a17;color:#fff;border-radius:14px",
    "padding:12px 14px;font:14px/1.4 system-ui,sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.3)",
  ].join(";");
  layer.appendChild(panel);
  doc.body.appendChild(layer);

  const colors = palette();
  let mode: HeatMode = "all";
  let sameWidth = false;
  let data: UxHeatmapData | null = null;
  let status: string = text.loading;
  let route = uxRouteTemplate(pathname);
  let frame = 0;
  let request = 0;

  const device = (): UxViewportClass => viewportClass(win.innerWidth);

  const button = (label: string, pressed: boolean, onClick: () => void) => {
    const el = doc.createElement("button");
    el.type = "button";
    el.textContent = label;
    el.setAttribute("aria-pressed", String(pressed));
    el.style.cssText = `min-height:32px;padding:4px 10px;border-radius:999px;border:1px solid #fff;cursor:pointer;font:inherit;${
      pressed
        ? "background:#fff;color:#1f1a17;"
        : "background:transparent;color:#fff;"
    }`;
    el.addEventListener("click", onClick);
    return el;
  };

  const renderPanel = () => {
    panel.replaceChildren();

    const title = doc.createElement("strong");
    title.textContent = text.title;
    title.style.display = "block";
    panel.appendChild(title);

    const line = doc.createElement("p");
    line.style.margin = "4px 0 8px";
    line.setAttribute("aria-live", "polite");
    if (data && route) {
      const clicks = data.cells.reduce((sum, cell) => sum + cell.clicks, 0);
      line.textContent = `${route} · ${format(text.summary, {
        views: String(data.page.views),
        clicks: String(clicks),
        device: text.devices[device()],
      })}`;
    } else {
      line.textContent = route ? status : text.notTracked;
    }
    panel.appendChild(line);

    const row = doc.createElement("div");
    row.style.cssText =
      "display:flex;flex-wrap:wrap;gap:6px;align-items:center;";
    const modeLabel = doc.createElement("span");
    modeLabel.textContent = `${text.modeLabel}:`;
    row.appendChild(modeLabel);
    for (const [key, label] of [
      ["all", text.modeAll],
      ["dead", text.modeDead],
      ["rage", text.modeRage],
    ] as const) {
      row.appendChild(
        button(label, mode === key, () => {
          mode = key;
          renderPanel();
          schedule();
        }),
      );
    }
    panel.appendChild(row);

    const row2 = doc.createElement("div");
    row2.style.cssText = "display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;";
    row2.appendChild(
      button(text.sameWidth, sameWidth, () => {
        sameWidth = !sameWidth;
        void load();
      }),
    );
    row2.appendChild(
      button(text.close, false, () => {
        clearOverlayToken(win);
        destroy();
      }),
    );
    panel.appendChild(row2);

    const note = doc.createElement("p");
    note.textContent = text.fixedNote;
    note.style.cssText = "margin:8px 0 0;font-size:12px;opacity:.8;";
    panel.appendChild(note);
  };

  const draw = () => {
    frame = 0;
    const ratio = win.devicePixelRatio || 1;
    const width = win.innerWidth;
    const height = win.innerHeight;
    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);

    const ctx = canvas.getContext("2d");
    if (!ctx || !data) return;

    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, width, height);

    const radius = device() === "mobile" ? 16 : 22;
    const points = heatPoints(data.cells, mode, {
      docWidth: Math.max(doc.documentElement.scrollWidth, 1),
      scrollX: win.scrollX,
      scrollY: win.scrollY,
      width,
      height,
      xBuckets: data.x_buckets,
      yStep: data.y_step,
    });

    // Intensity first (alpha only), then coloured through the palette.
    for (const point of points) {
      const gradient = ctx.createRadialGradient(
        point.x,
        point.y,
        0,
        point.x,
        point.y,
        radius,
      );
      gradient.addColorStop(0, `rgba(0,0,0,${0.15 + 0.85 * point.weight})`);
      gradient.addColorStop(1, "rgba(0,0,0,0)");
      ctx.fillStyle = gradient;
      ctx.fillRect(point.x - radius, point.y - radius, radius * 2, radius * 2);
    }

    const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const pixels = image.data;
    for (let i = 0; i < pixels.length; i += 4) {
      const alpha = pixels[i + 3];
      if (alpha === 0) continue;
      pixels[i] = colors[alpha * 3];
      pixels[i + 1] = colors[alpha * 3 + 1];
      pixels[i + 2] = colors[alpha * 3 + 2];
      pixels[i + 3] = Math.min(210, 60 + alpha);
    }
    ctx.putImageData(image, 0, 0);

    // Scroll depth: how many views reached each quarter of the page.
    const views = data.page.views;
    if (views > 0) {
      const docHeight = Math.max(doc.documentElement.scrollHeight, 1);
      ctx.font = "600 12px system-ui, sans-serif";
      for (const depth of SCROLL_LINES) {
        const y = (depth / 100) * docHeight - win.scrollY;
        if (y < 0 || y > height) continue;
        const reached = Math.round(
          ((data.page.scroll[String(depth)] ?? 0) / views) * 100,
        );
        ctx.setLineDash([6, 4]);
        ctx.strokeStyle = "rgba(31,26,23,.75)";
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(width, y);
        ctx.stroke();
        const label = format(text.scrollLine, { pct: `${reached}%` });
        const labelWidth = ctx.measureText(label).width + 12;
        ctx.fillStyle = "rgba(31,26,23,.9)";
        ctx.fillRect(width - labelWidth - 8, y - 20, labelWidth, 18);
        ctx.fillStyle = "#fff";
        ctx.fillText(label, width - labelWidth - 2, y - 7);
      }
    }
  };

  const schedule = () => {
    if (!frame) frame = win.requestAnimationFrame(draw);
  };

  const load = async () => {
    const current = ++request;
    data = null;
    status = text.loading;
    renderPanel();
    schedule();

    if (!route) return;

    const query = new URLSearchParams({ route, vc: device() });
    if (sameWidth) query.set("wb", String(widthBucket(win.innerWidth)));

    try {
      const response = await win.fetch(`${UX_HEATMAP_PATH}?${query}`, {
        headers: { [UX_OVERLAY_TOKEN_HEADER]: token },
        cache: "no-store",
        credentials: "same-origin",
      });

      if (current !== request) return;

      if (response.status === 403) {
        clearOverlayToken(win);
        status = text.expired;
      } else if (!response.ok) {
        status = text.failed;
      } else {
        const body = (await response.json()) as { data?: UxHeatmapData };
        data = body.data ?? null;
        if (!data) status = text.failed;
      }
    } catch {
      if (current === request) status = text.failed;
    }

    if (current === request) {
      renderPanel();
      schedule();
    }
  };

  let lastDevice = device();
  const onResize = () => {
    if (device() !== lastDevice || sameWidth) {
      lastDevice = device();
      void load();
    } else {
      schedule();
    }
  };

  win.addEventListener("scroll", schedule, { passive: true });
  win.addEventListener("resize", onResize, { passive: true });

  function destroy() {
    request += 1;
    if (frame) win.cancelAnimationFrame(frame);
    win.removeEventListener("scroll", schedule);
    win.removeEventListener("resize", onResize);
    layer.remove();
  }

  void load();

  return {
    setPath(next) {
      route = uxRouteTemplate(next);
      void load();
    },
    destroy,
  };
}
