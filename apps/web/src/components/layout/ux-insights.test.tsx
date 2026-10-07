import { render, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { OVERLAY_TOKEN_KEY } from "@/lib/ux/overlay-token";

/**
 * Which chunk the layout component loads: the overlay only for a tab holding a
 * staff token, the tracker only without privacy signals and outside staff
 * overlay sessions, and neither for a visitor who opted out.
 */
const mountOverlay = vi.fn<
  (options: { token: string; pathname: string }) => {
    setPath: () => void;
    destroy: () => void;
  }
>();
const createTracker = vi.fn(() => ({
  setPath: vi.fn(),
  replay: vi.fn(),
  flush: vi.fn(),
  stop: vi.fn(),
}));
// Clicks made before the tracker loaded: one on the page the tracker starts
// on, one on a page left before that.
const earlyStop = vi.fn();
const bufferEarlyClicks = vi.fn((_win: Window, path: () => string) => ({
  take: () => [
    { at: 300, path: path(), marker: "here" },
    { at: 200, path: "/elsewhere", marker: "left" },
  ],
  stop: earlyStop,
}));

vi.mock("@/lib/ux/overlay", () => ({ mountOverlay }));
vi.mock("@/lib/ux/early-clicks", () => ({ bufferEarlyClicks }));
vi.mock("@/lib/ux/tracker", () => ({
  createTracker,
  beaconSender: () => vi.fn(),
}));

const { UxInsights, uxSampleRate } =
  await import("@/components/layout/ux-insights");
const { heatPoints } =
  await vi.importActual<typeof import("@/lib/ux/overlay")>("@/lib/ux/overlay");

const TOKEN = `${"a".repeat(60)}.${"b".repeat(43)}`;

beforeEach(() => {
  mountOverlay.mockImplementation(() => ({
    setPath: vi.fn(),
    destroy: vi.fn(),
  }));
  // Run idle work straight away.
  vi.stubGlobal("requestIdleCallback", (run: () => void) => {
    run();
    return 1;
  });
  vi.stubGlobal("cancelIdleCallback", () => undefined);
  sessionStorage.clear();
  window.history.replaceState(null, "", "/doctors");
});

afterEach(() => {
  mountOverlay.mockClear();
  createTracker.mockClear();
  bufferEarlyClicks.mockClear();
  earlyStop.mockClear();
  Object.defineProperty(navigator, "globalPrivacyControl", {
    configurable: true,
    value: undefined,
  });
});

describe("UxInsights", () => {
  it("loads the tracker, not the overlay, for an ordinary visit", async () => {
    render(<UxInsights />);

    await waitFor(() => expect(createTracker).toHaveBeenCalledOnce());
    expect(mountOverlay).not.toHaveBeenCalled();
  });

  it("hands the tracker the clicks made on its page before it loaded", async () => {
    render(<UxInsights />);

    await waitFor(() => expect(createTracker).toHaveBeenCalledOnce());
    const tracker = createTracker.mock.results[0].value;
    // The view started with the page load, not when the tracker arrived.
    expect(tracker.setPath).toHaveBeenCalledWith(expect.any(String), 0);
    expect(tracker.replay).toHaveBeenCalledWith([
      expect.objectContaining({ marker: "here" }),
    ]);
  });

  it("loads nothing with Global Privacy Control on", async () => {
    Object.defineProperty(navigator, "globalPrivacyControl", {
      configurable: true,
      value: true,
    });

    render(<UxInsights />);
    await new Promise((resolve) => setTimeout(resolve, 20));

    expect(createTracker).not.toHaveBeenCalled();
    expect(mountOverlay).not.toHaveBeenCalled();
    expect(bufferEarlyClicks).not.toHaveBeenCalled();
  });

  it("adopts a token from the fragment, strips it, and shows the overlay instead of tracking", async () => {
    window.history.replaceState(null, "", `/doctors#ux-heatmap=${TOKEN}`);

    render(<UxInsights />);

    await waitFor(() => expect(mountOverlay).toHaveBeenCalledOnce());
    expect(mountOverlay.mock.calls[0][0]).toMatchObject({
      token: TOKEN,
      pathname: "/",
    });
    expect(window.location.hash).toBe("");
    expect(sessionStorage.getItem(OVERLAY_TOKEN_KEY)).toBe(TOKEN);
    expect(createTracker).not.toHaveBeenCalled();
  });

  it("ignores a malformed fragment", async () => {
    window.history.replaceState(null, "", "/doctors#ux-heatmap=1");

    render(<UxInsights />);

    await waitFor(() => expect(createTracker).toHaveBeenCalledOnce());
    expect(mountOverlay).not.toHaveBeenCalled();
  });

  it("reads the sample rate, defaulting to every tab", () => {
    expect(uxSampleRate(undefined)).toBe(1);
    expect(uxSampleRate("0.25")).toBe(0.25);
    expect(uxSampleRate("0")).toBe(0);
    expect(uxSampleRate("7")).toBe(1);
    expect(uxSampleRate("off")).toBe(0);
  });
});

describe("heatPoints", () => {
  const view = {
    docWidth: 1000,
    scrollX: 0,
    scrollY: 100,
    width: 1000,
    height: 500,
    xBuckets: 100,
    yStep: 10,
  };

  it("places cells in viewport pixels, weighted by the busiest", () => {
    const points = heatPoints(
      [
        { x: 49, y: 20, clicks: 4, dead: 1, rage: 0 },
        { x: 0, y: 30, clicks: 2, dead: 2, rage: 0 },
        { x: 10, y: 900, clicks: 9, dead: 0, rage: 0 }, // far below
      ],
      "dead",
      view,
    );

    expect(points).toEqual([
      { x: 495, y: 105, weight: 0.5 },
      { x: 5, y: 205, weight: 1 },
    ]);
  });

  it("draws nothing when the mode has no clicks", () => {
    expect(
      heatPoints([{ x: 1, y: 1, clicks: 3, dead: 0, rage: 0 }], "rage", view),
    ).toEqual([]);
  });
});
