import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mk } from "@/i18n/mk";
import { mountOverlay, type Overlay } from "@/lib/ux/overlay";
import { OVERLAY_TOKEN_KEY } from "@/lib/ux/overlay-token";

/** The staff overlay panel: accessible, out of the way, easy to close. */

const TOKEN = `${"a".repeat(60)}.${"b".repeat(43)}`;
const DATA = {
  route: "/doctors/[slug]",
  viewport_class: "mobile",
  from: "2026-09-08",
  to: "2026-10-07",
  x_buckets: 100,
  y_step: 10,
  cells: [{ x: 10, y: 10, clicks: 3, dead: 1, rage: 0 }],
  page: { views: 4, scroll: { "25": 3, "50": 2, "75": 1, "90": 0 } },
};

let overlay: Overlay | null = null;

beforeEach(() => {
  sessionStorage.setItem(OVERLAY_TOKEN_KEY, TOKEN);
  vi.stubGlobal(
    "fetch",
    vi.fn(async () => Response.json({ data: DATA })),
  );
  // jsdom has no canvas.
  vi.spyOn(HTMLCanvasElement.prototype, "getContext").mockReturnValue(null);
});

afterEach(() => {
  overlay?.destroy();
  overlay = null;
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
  document.body.innerHTML = "";
  sessionStorage.clear();
});

function mount() {
  overlay = mountOverlay({ win: window, token: TOKEN, pathname: "/doctors/x" });
  return document.getElementById("ux-heatmap-overlay")!;
}

const settle = () => new Promise((resolve) => setTimeout(resolve, 0));

describe("overlay panel", () => {
  it("keeps one live region and updates its text", async () => {
    const layer = mount();
    const live = layer.querySelector("[aria-live]")!;
    expect(live.textContent).toBe(mk.uxOverlay.loading);

    await settle();
    expect(layer.querySelectorAll("[aria-live]")).toHaveLength(1);
    expect(layer.querySelector("[aria-live]")).toBe(live);
    expect(live.textContent).toContain("4 посети");

    (layer.querySelector("button[aria-pressed]") as HTMLElement).click();
    expect(layer.querySelector("[aria-live]")).toBe(live);
  });

  it("has touch-sized buttons", async () => {
    const layer = mount();
    await settle();

    for (const button of layer.querySelectorAll("button")) {
      expect(button.style.minHeight).toBe("44px");
    }
  });

  it("closes with Escape", async () => {
    mount();
    await settle();

    document.dispatchEvent(
      new KeyboardEvent("keydown", { key: "Escape", bubbles: true }),
    );

    expect(document.getElementById("ux-heatmap-overlay")).toBeNull();
    expect(sessionStorage.getItem(OVERLAY_TOKEN_KEY)).toBeNull();
  });

  it("leaves Escape to an open dialog", async () => {
    document.body.innerHTML = `<div role="dialog" aria-modal="true"></div>`;
    mount();
    await settle();

    document.dispatchEvent(
      new KeyboardEvent("keydown", { key: "Escape", bubbles: true }),
    );

    expect(document.getElementById("ux-heatmap-overlay")).not.toBeNull();
  });

  it("sits above a sticky action bar", async () => {
    Object.defineProperty(window, "innerHeight", {
      configurable: true,
      value: 800,
    });
    document.body.innerHTML = `<div data-sticky-action-bar></div>`;
    vi.spyOn(
      document.querySelector("[data-sticky-action-bar]")!,
      "getBoundingClientRect",
    ).mockReturnValue({ top: 660, height: 80, bottom: 740 } as DOMRect);

    const layer = mount();
    await settle();
    await new Promise((resolve) => requestAnimationFrame(resolve));

    const panel = layer.querySelector("[role=region]") as HTMLElement;
    // 800 - 660 = 140 px of bar and tab bar below, plus the 12 px gap.
    expect(panel.style.bottom).toBe("152px");
  });
});
