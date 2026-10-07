import { act, render } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ReviewViewTracker } from "@/components/reviews/review-view-tracker";
import { CONSENT_KEY, saveConsent } from "@/lib/consent/consent";
import { OVERLAY_TOKEN_KEY } from "@/lib/ux/overlay-token";
import { mockFetch, requestBody } from "../../../test/fetch";

type Callback = (entries: Partial<IntersectionObserverEntry>[]) => void;

let observers: { callback: Callback; observed: Element[] }[] = [];

class FakeObserver {
  observed: Element[] = [];

  constructor(public callback: Callback) {
    observers.push(this);
  }

  observe(element: Element) {
    this.observed.push(element);
  }

  unobserve() {}

  disconnect() {}
}

function show(...ids: number[]) {
  showAt(1, ...ids);
}

function showAt(ratio: number, ...ids: number[]) {
  const observer = observers[observers.length - 1];

  // The tracker may decline to watch at all (privacy signal, overlay tab).
  if (!observer) return;

  act(() => {
    observer.callback(
      observer.observed
        .filter((element) =>
          ids.includes(Number((element as HTMLElement).dataset.reviewViewId)),
        )
        .map((target) => ({
          isIntersecting: true,
          intersectionRatio: ratio,
          target,
        })),
    );
  });
}

function cards(...ids: number[]) {
  return ids.map((id) => (
    <article key={id} data-review-view-id={id}>
      {id}
    </article>
  ));
}

describe("ReviewViewTracker", () => {
  beforeEach(() => {
    observers = [];
    vi.useFakeTimers();
    vi.stubGlobal("IntersectionObserver", FakeObserver);
    saveConsent(true);
  });

  afterEach(() => {
    window.localStorage.removeItem(CONSENT_KEY);
    vi.useRealTimers();
    vi.unstubAllGlobals();
  });

  it("reports the cards that came on screen, once, in one batch", () => {
    const fetch = mockFetch({ status: 200, body: { data: { counted: 2 } } });
    render(<ReviewViewTracker>{cards(11, 12, 13)}</ReviewViewTracker>);

    show(11);
    show(12, 11);
    expect(fetch).not.toHaveBeenCalled();

    act(() => {
      vi.advanceTimersByTime(2000);
    });

    expect(fetch).toHaveBeenCalledTimes(1);
    expect(fetch.mock.calls[0][0]).toBe("/api/reviews/views");
    expect(requestBody(fetch)).toEqual({ ids: [11, 12] });
  });

  it("counts a card only once at least half of it is on screen", () => {
    const fetch = mockFetch({ status: 200, body: { data: { counted: 1 } } });
    render(<ReviewViewTracker>{cards(21, 22)}</ReviewViewTracker>);

    // The first callback / a sliver of the card: intersecting, ratio < 0.5.
    showAt(0.01, 21, 22);
    showAt(0.49, 21);
    act(() => {
      vi.advanceTimersByTime(5000);
    });
    expect(fetch).not.toHaveBeenCalled();

    showAt(0.5, 21);
    act(() => {
      vi.advanceTimersByTime(2000);
    });
    expect(requestBody(fetch)).toEqual({ ids: [21] });
  });

  it("never reports the viewer's own review and sends nothing when nothing was seen", () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    const { unmount } = render(
      <ReviewViewTracker excludeId={12}>{cards(12)}</ReviewViewTracker>,
    );

    show(12);
    act(() => {
      vi.advanceTimersByTime(5000);
    });
    unmount();

    expect(fetch).not.toHaveBeenCalled();
  });

  it("sends what is pending when the page is left", () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    render(<ReviewViewTracker>{cards(21)}</ReviewViewTracker>);

    show(21);
    act(() => {
      window.dispatchEvent(new Event("pagehide"));
    });

    expect(fetch).toHaveBeenCalledTimes(1);
    expect(requestBody(fetch)).toEqual({ ids: [21] });
  });

  // Statistics run only after an explicit yes in the consent banner.
  it("counts nothing before the visitor accepted statistics", () => {
    window.localStorage.removeItem(CONSENT_KEY);
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    const { unmount } = render(
      <ReviewViewTracker>{cards(31)}</ReviewViewTracker>,
    );

    show(31);
    act(() => {
      vi.advanceTimersByTime(5000);
    });
    unmount();

    expect(fetch).not.toHaveBeenCalled();
  });

  it("counts nothing after a decline", () => {
    saveConsent(false);
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    render(<ReviewViewTracker>{cards(32)}</ReviewViewTracker>);

    show(32);
    act(() => {
      vi.advanceTimersByTime(5000);
    });

    expect(fetch).not.toHaveBeenCalled();
  });

  it("still counts with consent when the browser sends Global Privacy Control, and sends the consent header", () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    vi.stubGlobal("navigator", { ...navigator, globalPrivacyControl: true });
    render(<ReviewViewTracker>{cards(33)}</ReviewViewTracker>);

    show(33);
    act(() => {
      vi.advanceTimersByTime(2000);
    });

    expect(requestBody(fetch)).toEqual({ ids: [33] });
    expect(
      (fetch.mock.calls[0][1] as RequestInit).headers as Record<string, string>,
    ).toMatchObject({ "X-Z360-Consent": "statistics" });
  });

  it("stops at once when consent is withdrawn, dropping what was pending", () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    render(<ReviewViewTracker>{cards(34)}</ReviewViewTracker>);

    show(34);
    act(() => {
      saveConsent(false);
      vi.advanceTimersByTime(5000);
      window.dispatchEvent(new Event("pagehide"));
    });

    expect(fetch).not.toHaveBeenCalled();
  });

  it("counts nothing in a tab showing the staff heatmap overlay", () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    window.sessionStorage.setItem(
      OVERLAY_TOKEN_KEY,
      "abcdefgh.abcdefghijklmnopqrstuvwxyz",
    );
    try {
      const { unmount } = render(
        <ReviewViewTracker>{cards(41)}</ReviewViewTracker>,
      );

      show(41);
      act(() => {
        vi.advanceTimersByTime(5000);
      });
      unmount();
    } finally {
      window.sessionStorage.removeItem(OVERLAY_TOKEN_KEY);
    }

    expect(fetch).not.toHaveBeenCalled();
  });
});
