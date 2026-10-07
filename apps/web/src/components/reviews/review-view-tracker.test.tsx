import { act, render } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ReviewViewTracker } from "@/components/reviews/review-view-tracker";
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
  const observer = observers[observers.length - 1];

  act(() => {
    observer.callback(
      observer.observed
        .filter((element) =>
          ids.includes(Number((element as HTMLElement).dataset.reviewViewId)),
        )
        .map((target) => ({ isIntersecting: true, target })),
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
  });

  afterEach(() => {
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
});
