import { act, fireEvent, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { HomeCarousel } from "@/components/home/home-carousel";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

/*
 * jsdom has no layout: give the rail a 600px viewport over six 280px cards
 * with a 20px step (card i at i * 300), as on a phone-ish width that shows
 * two cards.
 */
const CARD = 280;
const STEP = 300;
const VIEW = 600;

let scrollLeft = 0;
const scrollTo = vi.fn((options: ScrollToOptions) => {
  scrollLeft = options.left ?? 0;
});

beforeEach(() => {
  scrollLeft = 0;
  scrollTo.mockClear();
  vi.spyOn(HTMLElement.prototype, "offsetLeft", "get").mockImplementation(
    function (this: HTMLElement) {
      const index = Array.from(this.parentElement?.children ?? []).indexOf(
        this,
      );
      return this.tagName === "LI" ? index * STEP : 0;
    },
  );
  vi.spyOn(HTMLElement.prototype, "offsetWidth", "get").mockImplementation(
    function (this: HTMLElement) {
      return this.tagName === "LI" ? CARD : VIEW;
    },
  );
  vi.spyOn(HTMLElement.prototype, "clientWidth", "get").mockImplementation(
    () => VIEW,
  );
  vi.spyOn(HTMLElement.prototype, "scrollLeft", "get").mockImplementation(
    () => scrollLeft,
  );
  HTMLElement.prototype.scrollTo =
    scrollTo as unknown as HTMLElement["scrollTo"];
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

function renderRail(count = 6) {
  return render(
    <section aria-label="Истакнати лекари">
      <HomeCarousel>
        {Array.from({ length: count }, (_, i) => (
          <li key={i} className="snap-start">
            <a href={`/doctors/${i}`}>Лекар {i + 1}</a>
          </li>
        ))}
      </HomeCarousel>
    </section>,
  );
}

function position() {
  return document.querySelector("[data-rail-position]")?.textContent;
}

function dots() {
  return Array.from(document.querySelectorAll("[data-rail-dot]")).map((d) =>
    d.getAttribute("data-rail-dot"),
  );
}

async function settle() {
  // rAF-throttled update after a scroll.
  await act(async () => {
    await new Promise((resolve) => requestAnimationFrame(() => resolve(null)));
  });
}

describe("HomeCarousel", () => {
  it("shows which cards are in view as „1–2 од 6“ with matching dots", async () => {
    const { container } = renderRail();
    expect(position()).toBe("1–2 од 6");
    expect(dots()).toEqual(["on", "on", "off", "off", "off", "off"]);
    // The dots are decoration; the line is the information.
    expect(
      document.querySelector("[data-rail-dot]")?.parentElement,
    ).toHaveAttribute("aria-hidden", "true");

    scrollLeft = 900;
    fireEvent.scroll(screen.getByRole("list"));
    await settle();
    expect(position()).toBe("4–5 од 6");
    expect(dots()).toEqual(["off", "off", "off", "on", "on", "off"]);
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("pages with labelled previous/next buttons, disabled at either end", async () => {
    const user = userEvent.setup();
    renderRail();
    const prev = screen.getByRole("button", { name: t("homeSearch.railPrev") });
    const next = screen.getByRole("button", { name: t("homeSearch.railNext") });
    const list = screen.getByRole("list");
    expect(prev).toBeDisabled();
    expect(next).toBeEnabled();
    expect(next).toHaveAttribute("aria-controls", list.id);

    await user.click(next);
    // Two cards in view, so the next page starts at the third card.
    expect(scrollTo).toHaveBeenLastCalledWith({
      left: 600,
      behavior: "smooth",
    });
    fireEvent.scroll(list);
    await settle();
    expect(position()).toBe("3–4 од 6");
    expect(prev).toBeEnabled();

    scrollLeft = 1200;
    fireEvent.scroll(list);
    await settle();
    expect(position()).toBe("5–6 од 6");
    expect(next).toBeDisabled();

    await user.click(prev);
    expect(scrollTo).toHaveBeenLastCalledWith({
      left: 600,
      behavior: "smooth",
    });
  });

  it("jumps instead of gliding when the visitor prefers reduced motion", async () => {
    vi.stubGlobal(
      "matchMedia",
      (query: string) =>
        ({
          matches: query.includes("reduce"),
          media: query,
          addEventListener() {},
          removeEventListener() {},
        }) as unknown as MediaQueryList,
    );
    const user = userEvent.setup();
    renderRail();
    await user.click(
      screen.getByRole("button", { name: t("homeSearch.railNext") }),
    );
    expect(scrollTo).toHaveBeenLastCalledWith({ left: 600, behavior: "auto" });
  });

  it("leaves the controls out when every card fits", () => {
    renderRail(2);
    expect(document.querySelector("[data-rail-controls]")).toBeNull();
    expect(screen.queryByRole("button")).toBeNull();
  });
});
