import { render } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  REVEAL_READY_CLASS,
  RevealObserver,
} from "@/components/layout/reveal-observer";

let observed: Element[] = [];
let trigger: ((target: Element) => void) | null = null;

class FakeObserver {
  constructor(callback: IntersectionObserverCallback) {
    trigger = (target) =>
      callback(
        [{ isIntersecting: true, target } as IntersectionObserverEntry],
        this as unknown as IntersectionObserver,
      );
  }
  observe(el: Element) {
    observed.push(el);
  }
  unobserve(el: Element) {
    observed = observed.filter((x) => x !== el);
  }
  disconnect() {
    observed = [];
  }
}

function stubMotion(reduce: boolean) {
  vi.stubGlobal(
    "matchMedia",
    vi.fn((query: string) => ({
      matches: reduce && query.includes("reduce"),
      media: query,
      addEventListener() {},
      removeEventListener() {},
    })),
  );
}

/** Two sections: one on screen, one below the fold. */
function page() {
  document.body.innerHTML = `
    <section id="above" data-reveal=""></section>
    <section id="below" data-reveal=""><a id="link" href="#x">Link</a></section>`;
  const at = (id: string, top: number) =>
    vi
      .spyOn(document.getElementById(id)!, "getBoundingClientRect")
      .mockReturnValue({ top } as DOMRect);
  at("above", 100);
  at("below", 5000);
}

beforeEach(() => {
  observed = [];
  trigger = null;
  vi.stubGlobal("IntersectionObserver", FakeObserver);
  page();
});

afterEach(() => {
  vi.unstubAllGlobals();
  document.documentElement.classList.remove(REVEAL_READY_CLASS);
});

describe("RevealObserver", () => {
  it("hides only sections below the fold, and shows each once in view", () => {
    stubMotion(false);
    render(<RevealObserver />);

    const above = document.getElementById("above")!;
    const below = document.getElementById("below")!;
    expect(document.documentElement).toHaveClass(REVEAL_READY_CLASS);
    expect(above).toHaveAttribute("data-reveal", "shown");
    expect(below).toHaveAttribute("data-reveal", "pending");
    expect(observed).toEqual([below]);

    trigger!(below);
    expect(below).toHaveAttribute("data-reveal", "shown");
    expect(observed).toEqual([]);
  });

  it("does nothing under reduced motion", () => {
    stubMotion(true);
    render(<RevealObserver />);

    expect(document.documentElement).not.toHaveClass(REVEAL_READY_CLASS);
    expect(document.getElementById("below")).toHaveAttribute("data-reveal", "");
  });

  it("shows anything still pending when it unmounts", () => {
    stubMotion(false);
    const { unmount } = render(<RevealObserver />);
    unmount();

    expect(document.getElementById("below")).toHaveAttribute(
      "data-reveal",
      "shown",
    );
  });

  it("shows a hidden section the moment something inside it takes focus", () => {
    stubMotion(false);
    render(<RevealObserver />);

    const below = document.getElementById("below")!;
    expect(below).toHaveAttribute("data-reveal", "pending");

    document.getElementById("link")!.focus();
    expect(below).toHaveAttribute("data-reveal", "shown");
    expect(observed).toEqual([]);
  });
});
