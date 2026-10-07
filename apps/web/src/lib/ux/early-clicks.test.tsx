import { afterEach, describe, expect, it } from "vitest";
import { bufferEarlyClicks, MAX_EARLY_CLICKS } from "@/lib/ux/early-clicks";

/** The listener that holds clicks until the tracker chunk has loaded. */

afterEach(() => {
  Object.defineProperty(window, "scrollY", { configurable: true, value: 0 });
  document.body.innerHTML = "";
});

function click(el: Element) {
  el.dispatchEvent(new MouseEvent("click", { bubbles: true, detail: 1 }));
}

describe("bufferEarlyClicks", () => {
  it("remembers each click with its page, scroll position and time, then stops", () => {
    document.body.innerHTML = `<p id="p">x</p>`;
    let path = "/doctors";
    const early = bufferEarlyClicks(window, () => path, {
      trustedOnly: false,
    });

    Object.defineProperty(window, "scrollY", {
      configurable: true,
      value: 640,
    });
    click(document.getElementById("p")!);
    path = "/facilities";
    click(document.getElementById("p")!);

    const taken = early.take();
    expect(taken.map((c) => [c.path, c.scrollY, c.selected])).toEqual([
      ["/doctors", 640, false],
      ["/facilities", 640, false],
    ]);
    expect(taken[0].at).toBeTypeOf("number");
    expect(taken[0].event.target).toBe(document.getElementById("p"));

    // Taken once: the tracker listens from here on.
    click(document.getElementById("p")!);
    expect(early.take()).toEqual([]);
  });

  it("keeps a bounded number and ignores script clicks by default", () => {
    document.body.innerHTML = `<p id="p">x</p>`;
    const all = bufferEarlyClicks(window, () => "/", { trustedOnly: false });
    const trusted = bufferEarlyClicks(window, () => "/");

    for (let i = 0; i < MAX_EARLY_CLICKS + 5; i++) {
      click(document.getElementById("p")!);
    }

    expect(all.take()).toHaveLength(MAX_EARLY_CLICKS);
    expect(trusted.take()).toEqual([]);
  });
});
