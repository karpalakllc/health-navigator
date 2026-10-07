import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { privacySignalHeader, privacySignalOn } from "@/lib/ux/privacy-signals";
import { isUxTargetKey, type UxBatch } from "@/lib/ux/schema";
import type { EarlyClick } from "@/lib/ux/early-clicks";
import {
  BATCH_CLICKS,
  BATCH_DELAY_MS,
  createTracker,
  type Tracker,
} from "@/lib/ux/tracker";

/**
 * The tracker against a real (jsdom) document. jsdom has no layout, so the
 * page geometry the tracker reads is stubbed per test.
 */

const SECRET = "Болка во градите од вчера";

let sent: UxBatch[];
let clock: number;
let tracker: Tracker;

function geometry({ width = 390, height = 844, docHeight = 3000 } = {}) {
  Object.defineProperty(window, "innerWidth", {
    configurable: true,
    value: width,
  });
  Object.defineProperty(window, "innerHeight", {
    configurable: true,
    value: height,
  });
  Object.defineProperty(document.documentElement, "scrollHeight", {
    configurable: true,
    value: docHeight,
  });
  Object.defineProperty(document.documentElement, "scrollWidth", {
    configurable: true,
    value: width,
  });
}

/** A pointer click (detail 1); keyboard activations have detail 0. */
function click(el: Element, x = 100, y = 200) {
  el.dispatchEvent(
    new MouseEvent("click", {
      bubbles: true,
      clientX: x,
      clientY: y,
      detail: 1,
    }),
  );
}

function allClicks() {
  return sent.flatMap((batch) => batch.clicks);
}

beforeEach(() => {
  vi.useFakeTimers();
  geometry();
  sent = [];
  clock = 0;
  document.body.innerHTML = `
    <header><a href="/doctors" id="nav-link">Лекари</a></header>
    <main>
      <article data-track="doctor-card">
        <h3 id="name">${SECRET}</h3>
        <p id="para">${SECRET}</p>
        <a href="/doctors/x" id="profile"><span id="inner">Профил</span></a>
        <button id="btn" aria-label="${SECRET}">Јави се</button>
        <button id="off" disabled>Исклучено</button>
      </article>
      <form><label for="f" id="lbl">Име</label><input id="f" value="${SECRET}"/></form>
      <div id="blank"></div>
    </main>`;
  tracker = createTracker({
    win: window,
    send: (batch) => sent.push(batch),
    now: () => clock,
    trustedOnly: false,
  });
  tracker.setPath("/doctors/ivan-petrov", 0);
});

afterEach(() => {
  tracker.stop();
  vi.useRealTimers();
});

describe("dead clicks", () => {
  it("flags clicks on things that do nothing, not on links, buttons or labelled controls", () => {
    click(document.getElementById("name")!);
    click(document.getElementById("para")!, 300, 400);
    click(document.getElementById("inner")!, 50, 600);
    click(document.getElementById("btn")!, 50, 700);
    click(document.getElementById("lbl")!, 50, 800);
    click(document.getElementById("off")!, 200, 900);
    tracker.flush();

    expect(allClicks().map((c) => [c.k, c.d])).toEqual([
      ["doctor-card/heading", true],
      ["doctor-card/text", true],
      ["doctor-card/link", false],
      ["doctor-card/button", false],
      ["form/label", false],
      ["doctor-card/disabled", true],
    ]);
  });

  it("does not count a text-selection drag", () => {
    const range = document.createRange();
    range.selectNodeContents(document.getElementById("para")!);
    window.getSelection()!.addRange(range);

    click(document.getElementById("para")!);
    // A double click also leaves a selection, but is a click.
    document
      .getElementById("para")!
      .dispatchEvent(new MouseEvent("click", { bubbles: true, detail: 2 }));
    tracker.flush();

    expect(allClicks().map((c) => c.k)).toEqual(["doctor-card/text"]);
    window.getSelection()!.removeAllRanges();
  });
});

describe("target keys", () => {
  it("ignore a data-track name outside the closed list", () => {
    document.getElementById("blank")!.innerHTML =
      `<section data-track="secret-word"><p id="free">x</p></section>`;
    click(document.getElementById("free")!);
    tracker.flush();

    expect(allClicks()[0].k).toBe("main/text");
  });

  it("are always in the vocabulary the API accepts", () => {
    document.getElementById("blank")!.innerHTML = `
      <input type="hidden-ish" id="odd" />
      <input type="email" id="mail" />
      <div role="tab" id="tab">x</div>
      <div role="madeup" id="madeup">x</div>
      <select id="sel"></select>
      <span tabindex="0" id="focus">x</span>
      <svg><path id="path"></path></svg>`;
    for (const id of ["odd", "mail", "tab", "madeup", "sel", "focus", "path"]) {
      click(document.getElementById(id)!);
    }
    tracker.flush();

    const keys = allClicks().map((c) => c.k);
    expect(keys).toEqual([
      "main/input-other",
      "main/input-email",
      "main/role-tab",
      "main/area",
      "main/select",
      "main/focusable",
      "main/icon",
    ]);
    expect(keys.every((k) => isUxTargetKey(k))).toBe(true);
  });
});

describe("rage clicks", () => {
  it("flags the third rapid click on one spot, once per burst", () => {
    const el = document.getElementById("blank")!;
    for (const at of [0, 150, 300, 450]) {
      clock = at;
      click(el, 100 + at / 50, 200);
    }
    tracker.flush();

    expect(allClicks().map((c) => c.g)).toEqual([false, false, true, false]);
  });

  it("needs the clicks within 700 ms and 30 px", () => {
    const el = document.getElementById("blank")!;
    clock = 0;
    click(el, 100, 200);
    clock = 400;
    click(el, 100, 200);
    clock = 800; // first click left the window
    click(el, 100, 200);
    clock = 900;
    click(el, 200, 200); // too far away
    tracker.flush();

    expect(allClicks().some((c) => c.g)).toBe(false);
  });
});

describe("keyboard and assistive-technology activations", () => {
  it("count for their target, but have no position and are never rage clicks", () => {
    // Enter/Space on a button, implicit form submission and screen readers
    // fire a click with detail 0 at clientX = clientY = 0.
    const btn = document.getElementById("btn")!;
    for (const at of [0, 100, 200]) {
      clock = at;
      btn.dispatchEvent(new MouseEvent("click", { bubbles: true, detail: 0 }));
    }
    tracker.flush();

    expect(allClicks()).toHaveLength(3);
    for (const c of allClicks()) {
      expect(c).toMatchObject({
        k: "doctor-card/button",
        d: false,
        y: null,
        g: false,
      });
    }
  });
});

describe("what is sent", () => {
  it("contains no page text, form value, label, slug or identifier", () => {
    for (const id of ["name", "para", "btn", "f", "lbl", "profile"]) {
      click(document.getElementById(id)!);
    }
    tracker.flush(true);

    const payload = JSON.stringify(sent);
    expect(payload).not.toContain(SECRET);
    expect(payload).not.toContain("Болка");
    expect(payload).not.toContain("ivan-petrov");
    expect(payload).not.toContain("Профил");
    expect(payload).not.toMatch(/"(id|session|sid|tab|uid|text|value|href)"/);

    for (const c of allClicks()) {
      expect(Object.keys(c).sort()).toEqual(
        ["d", "g", "k", "r", "vc", "wb", "x", "y"].sort(),
      );
      expect(c.r).toBe("/doctors/[slug]");
    }
  });

  it("buckets position, device class and width", () => {
    Object.defineProperty(window, "scrollY", {
      configurable: true,
      value: 500,
    });
    click(document.getElementById("para")!, 195, 123);
    tracker.flush();
    Object.defineProperty(window, "scrollY", { configurable: true, value: 0 });

    expect(allClicks()[0]).toMatchObject({
      vc: "mobile",
      wb: 320,
      x: 50,
      y: 62, // (123 + 500) / 10
    });
  });

  it("leaves clicks on fixed or sticky elements off the map", () => {
    const header = document.querySelector("header")!;
    header.style.position = "sticky";
    click(document.getElementById("nav-link")!);
    tracker.flush();

    expect(allClicks()[0]).toMatchObject({ k: "header/link", y: null });
  });
});

describe("page views", () => {
  it("reports the deepest scroll milestone and time to first click once per view", () => {
    clock = 2_500;
    click(document.getElementById("para")!);
    Object.defineProperty(window, "scrollY", {
      configurable: true,
      value: 1_500,
    });
    window.dispatchEvent(new Event("scroll"));
    vi.advanceTimersByTime(50); // animation frame
    Object.defineProperty(window, "scrollY", { configurable: true, value: 0 });

    tracker.setPath("/doctors");
    tracker.flush(true);

    const views = sent.flatMap((batch) => batch.views);
    expect(views).toEqual([
      // (1500 + 844) / 3000 = 78 %
      { r: "/doctors/[slug]", vc: "mobile", s: 75, t: 1 },
      { r: "/doctors", vc: "mobile", s: 25, t: null },
    ]);
  });

  it("never tracks account, sign-in or form pages", () => {
    for (const path of [
      "/account",
      "/account/reviews",
      "/login",
      "/register",
      "/forgot-password",
      "/reset-password/new",
      "/verify-email",
      "/doctors/ivan-petrov/claim",
      "/doctors/ivan-petrov/correction",
      "/forum/new",
    ]) {
      tracker.setPath(path); // ends (and queues) the previous view
      tracker.flush();
      sent = [];
      click(document.getElementById("btn")!);
      tracker.flush(true);
      expect(sent, path).toEqual([]);
    }
  });
});

describe("batching", () => {
  it("sends a batch at 25 clicks, else 10 s after the first click, and on page hide", () => {
    const el = document.getElementById("blank")!;
    for (let i = 0; i < BATCH_CLICKS; i++) {
      clock = i * 1_000; // no rage
      click(el, (i * 40) % 380, 200);
    }
    expect(sent).toHaveLength(1);
    expect(sent[0].clicks).toHaveLength(BATCH_CLICKS);

    click(el);
    expect(sent).toHaveLength(1);
    vi.advanceTimersByTime(BATCH_DELAY_MS);
    expect(sent).toHaveLength(2);

    click(el);
    window.dispatchEvent(new Event("pagehide"));
    expect(sent).toHaveLength(3);
    expect(sent[2].clicks).toHaveLength(1);
    expect(sent[2].views).toHaveLength(1);

    // The view is closed: leaving again sends nothing more.
    window.dispatchEvent(new Event("pagehide"));
    expect(sent).toHaveLength(3);
  });
});

describe("navigation", () => {
  it("queues the finished view and sends it with the 10 s timer, not one request per page", () => {
    tracker.setPath("/doctors");
    tracker.setPath("/facilities");
    expect(sent).toEqual([]);

    vi.advanceTimersByTime(BATCH_DELAY_MS);
    expect(sent).toHaveLength(1);
    expect(sent[0].views.map((v) => v.r)).toEqual([
      "/doctors/[slug]",
      "/doctors",
    ]);
  });
});

describe("clicks made before the tracker loaded", () => {
  function early(id: string, at: number, scrollY = 0): EarlyClick {
    const event = new MouseEvent("click", {
      bubbles: true,
      clientX: 195,
      clientY: 100,
      detail: 1,
    });
    Object.defineProperty(event, "target", {
      value: document.getElementById(id),
    });
    return {
      event,
      at,
      scrollX: 0,
      scrollY,
      selected: false,
      path: "/doctors/ivan-petrov",
    };
  }

  it("are counted with their own time and scroll position", () => {
    clock = 5_000; // the tracker loaded at idle, long after the click
    tracker.replay([early("para", 600, 1_000)]);
    tracker.flush(true);

    expect(allClicks()).toEqual([
      expect.objectContaining({ k: "doctor-card/text", y: 110, x: 50 }),
    ]);
    // Time to first click: 600 ms, not 5 s.
    expect(sent.flatMap((b) => b.views)[0].t).toBe(0);
  });

  it("skip a target that is no longer on the page", () => {
    const gone = early("para", 600);
    document.getElementById("para")!.remove();
    tracker.replay([gone]);
    tracker.flush(true);

    expect(allClicks()).toEqual([]);
    expect(sent.flatMap((b) => b.views)[0].t).toBeNull();
  });
});

describe("back/forward cache", () => {
  it("starts a new view when the page is restored", () => {
    window.dispatchEvent(new Event("pagehide"));
    expect(sent.flatMap((b) => b.views)).toHaveLength(1);

    const restored = new Event("pageshow");
    Object.defineProperty(restored, "persisted", { value: true });
    clock = 60_000;
    window.dispatchEvent(restored);
    clock = 60_500;
    click(document.getElementById("para")!);
    window.dispatchEvent(new Event("pagehide"));

    expect(sent.flatMap((b) => b.views)).toEqual([
      { r: "/doctors/[slug]", vc: "mobile", s: 25, t: null },
      // The restored page is a view of its own, clicked within a second.
      { r: "/doctors/[slug]", vc: "mobile", s: 25, t: 0 },
    ]);
  });
});

describe("privacy signals", () => {
  it("are on with Global Privacy Control or Do Not Track", () => {
    expect(privacySignalOn({ navigator: { globalPrivacyControl: true } })).toBe(
      true,
    );
    expect(privacySignalOn({ navigator: { doNotTrack: "1" } })).toBe(true);
    expect(privacySignalOn({ doNotTrack: "1" })).toBe(true);
    expect(privacySignalOn({ navigator: { doNotTrack: "0" } })).toBe(false);
    expect(privacySignalOn({ navigator: {} })).toBe(false);
  });

  it("are read from request headers too", () => {
    expect(privacySignalHeader(new Headers({ "Sec-GPC": "1" }))).toBe(true);
    expect(privacySignalHeader(new Headers({ DNT: "1" }))).toBe(true);
    expect(privacySignalHeader(new Headers())).toBe(false);
  });
});
