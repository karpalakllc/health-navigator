import { act, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { HeroMasterSearch } from "@/components/home/hero-master-search";
import {
  HERO_SEARCH_ATTR,
  HERO_SEARCH_FORM_ID,
  HERO_SEARCH_INPUT_ID,
} from "@/components/home/hero-search-ids";
import { HEADER_SEARCH_INPUT_ID } from "@/components/layout/header-search";
import { SearchDialogProvider } from "@/components/layout/search-dialog-context";
import { SiteHeaderBar } from "@/components/layout/site-header-bar";
import { router, setPathname } from "../../../test/next-navigation";

const modules = {
  public_guidance: true,
  public_products: true,
  public_pharmacies: true,
  public_forum: true,
};
const heroModules = { pharmacies: true, products: true, forum: true };

/** A stand-in IntersectionObserver the test drives by hand. */
let observed: Element[] = [];
let callback: IntersectionObserverCallback | null = null;
let disconnected = 0;

class FakeObserver {
  constructor(cb: IntersectionObserverCallback) {
    callback = cb;
  }
  observe(el: Element) {
    observed.push(el);
  }
  disconnect() {
    disconnected += 1;
  }
  unobserve() {}
  takeRecords() {
    return [];
  }
}

function report(isIntersecting: boolean) {
  act(() => {
    callback?.(
      [{ isIntersecting } as IntersectionObserverEntry],
      {} as IntersectionObserver,
    );
  });
}

function headerSlot() {
  return document.querySelector("[data-header-search]") as HTMLElement;
}

function classes(el: Element) {
  return el.className.split(/\s+/);
}

beforeEach(() => {
  observed = [];
  callback = null;
  disconnected = 0;
  vi.stubGlobal("IntersectionObserver", FakeObserver);
});

afterEach(() => {
  vi.unstubAllGlobals();
  setPathname("/");
});

describe("one search bar at a time on the home page", () => {
  it("hides the header pill on the home page until the hero search scrolls away", () => {
    setPathname("/");
    const { unmount } = render(
      <>
        <SiteHeaderBar isLoggedIn={false} modules={modules} />
        <HeroMasterSearch modules={heroModules} />
      </>,
    );

    // Hidden from the first paint (no flash), without changing its size:
    // invisible + transparent, not display:none.
    expect(classes(headerSlot())).toEqual(
      expect.arrayContaining([
        "invisible",
        "opacity-0",
        "[html[data-hero-search=hidden]_&]:visible",
        "[html[data-hero-search=hidden]_&]:opacity-100",
        // The fade only runs when motion is allowed.
        "motion-safe:transition-[opacity,visibility]",
      ]),
    );

    // The watcher observes the hero form and mirrors it onto <html>.
    expect(observed).toEqual([document.getElementById(HERO_SEARCH_FORM_ID)]);
    report(true);
    expect(document.documentElement.getAttribute(HERO_SEARCH_ATTR)).toBe(
      "visible",
    );
    report(false);
    expect(document.documentElement.getAttribute(HERO_SEARCH_ATTR)).toBe(
      "hidden",
    );

    // Leaving the page drops the flag and the observer.
    unmount();
    expect(document.documentElement.hasAttribute(HERO_SEARCH_ATTR)).toBe(false);
    expect(disconnected).toBe(1);
  });

  it("keeps the header pill as it was on every other page", () => {
    setPathname("/doctors");
    render(<SiteHeaderBar isLoggedIn={false} modules={modules} />);

    expect(classes(headerSlot())).toEqual(["hidden", "lg:block"]);
  });

  it("shows the pill when the browser cannot observe (no IntersectionObserver)", () => {
    vi.stubGlobal("IntersectionObserver", undefined);
    render(<HeroMasterSearch modules={heroModules} />);
    expect(document.documentElement.getAttribute(HERO_SEARCH_ATTR)).toBe(
      "hidden",
    );
  });

  it("Ctrl/Cmd+K goes to the hero search while it is on screen", async () => {
    const user = userEvent.setup();
    render(
      <SearchDialogProvider>
        <button type="button">Друго копче</button>
        <input id={HEADER_SEARCH_INPUT_ID} aria-label="header q" />
        <HeroMasterSearch modules={heroModules} />
      </SearchDialogProvider>,
    );
    const header = screen.getByLabelText("header q");
    Object.defineProperty(header, "offsetParent", { value: document.body });
    const hero = document.getElementById(HERO_SEARCH_INPUT_ID)!;

    report(true);
    screen.getByRole("button", { name: "Друго копче" }).focus();
    await user.keyboard("{Control>}k{/Control}");
    expect(hero).toHaveFocus();

    report(false);
    screen.getByRole("button", { name: "Друго копче" }).focus();
    await user.keyboard("{Control>}k{/Control}");
    expect(header).toHaveFocus();
    expect(router.push).not.toHaveBeenCalled();
  });
});
