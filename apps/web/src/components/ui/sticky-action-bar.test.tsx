import { act, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { StickyActionBar } from "@/components/ui/sticky-action-bar";

type Callback = (
  entries: Array<{ isIntersecting: boolean; target: Element }>,
) => void;

const observed = new Map<Element, Callback>();

class FakeObserver {
  constructor(private callback: Callback) {}
  observe(element: Element) {
    observed.set(element, this.callback);
  }
  disconnect() {}
}

afterEach(() => {
  observed.clear();
  vi.unstubAllGlobals();
});

describe("StickyActionBar", () => {
  it("steps aside while the element it leads to is on screen", () => {
    vi.stubGlobal("IntersectionObserver", FakeObserver);
    render(
      <>
        <div id="napishi-recenzija">form</div>
        <StickyActionBar
          label="Брзи дејства"
          hideWhileVisible="napishi-recenzija"
        >
          <a href="#napishi-recenzija">Напишете рецензија</a>
        </StickyActionBar>
      </>,
    );
    const bar = screen.getByRole("region", { name: "Брзи дејства" });
    const target = document.getElementById("napishi-recenzija")!;

    expect(bar).not.toHaveAttribute("data-hidden");
    act(() => observed.get(target)?.([{ isIntersecting: true, target }]));
    expect(bar).toHaveAttribute("data-hidden", "true");
    act(() => observed.get(target)?.([{ isIntersecting: false, target }]));
    expect(bar).not.toHaveAttribute("data-hidden");
  });

  it("stays hidden while any of several elements is on screen", () => {
    vi.stubGlobal("IntersectionObserver", FakeObserver);
    render(
      <>
        <div id="kopce">inline CTA</div>
        <div id="napishi-recenzija">form</div>
        <StickyActionBar
          label="Брзи дејства"
          hideWhileVisible={["napishi-recenzija", "kopce"]}
        >
          <a href="#napishi-recenzija">Напишете рецензија</a>
        </StickyActionBar>
      </>,
    );
    const bar = screen.getByRole("region", { name: "Брзи дејства" });
    const cta = document.getElementById("kopce")!;
    const form = document.getElementById("napishi-recenzija")!;
    const show = (target: Element, isIntersecting: boolean) =>
      act(() => observed.get(target)?.([{ isIntersecting, target }]));

    expect(bar).not.toHaveAttribute("data-hidden");
    show(cta, true);
    expect(bar).toHaveAttribute("data-hidden", "true");
    show(form, true);
    show(cta, false);
    // The form is still on screen.
    expect(bar).toHaveAttribute("data-hidden", "true");
    show(form, false);
    expect(bar).not.toHaveAttribute("data-hidden");
  });
});
