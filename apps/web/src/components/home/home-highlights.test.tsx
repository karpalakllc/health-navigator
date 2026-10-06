import { act, render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { RecordRecentlyViewed } from "@/components/directory/record-recently-viewed";
import { HomeCities } from "@/components/home/home-cities";
import { HomeHero } from "@/components/home/home-hero";
import { HomeRecentReviews } from "@/components/home/home-recent-reviews";
import { HomeRecentlyViewed } from "@/components/home/home-recently-viewed";
import { HomeSpecialties } from "@/components/home/home-specialties";
import type { HomeReview } from "@/lib/api/home";
import {
  RECENTLY_VIEWED_KEY,
  recordRecentlyViewed,
} from "@/lib/recently-viewed";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

describe("HomeSpecialties („Популарни специјалности“)", () => {
  const specialties = [
    { slug: "kardiologija", name: "Кардиологија", doctors_count: 12 },
    { slug: "pedijatrija", name: "Педијатрија", doctors_count: 1 },
    ...Array.from({ length: 6 }, (_, i) => ({
      slug: `s-${i}`,
      name: `Специјалност ${i}`,
      doctors_count: 2,
    })),
  ];

  it("links each specialty to the filtered doctor directory with its count", async () => {
    const { container } = render(<HomeSpecialties specialties={specialties} />);

    const section = screen.getByRole("region", {
      name: t("homeSections.specialtiesTitle"),
    });
    expect(
      within(section).getByRole("link", { name: "Кардиологија 12 лекари" }),
    ).toHaveAttribute("href", "/doctors?specialty=kardiologija");
    // Macedonian singular.
    expect(
      within(section).getByRole("link", { name: "Педијатрија 1 лекар" }),
    ).toBeInTheDocument();
    expect(
      within(section).getByRole("link", {
        name: t("home.topRatedViewAll"),
      }),
    ).toHaveAttribute("href", "/doctors");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  /** How many items each layout shows, read from the responsive classes. */
  function visibleCounts(items: HTMLElement[]) {
    const shown = (hiddenClass: string) =>
      items.filter((li) => !li.classList.contains(hiddenClass)).length;
    return {
      phone: shown("max-sm:hidden"),
      tablet: shown("sm:max-lg:hidden"),
      desktop: shown("lg:hidden"),
    };
  }

  it("shows six on phones and eight on wider layouts when there are eight", () => {
    render(<HomeSpecialties specialties={specialties} />);

    const items = screen.getAllByRole("listitem");
    expect(items).toHaveLength(8);
    expect(visibleCounts(items)).toEqual({ phone: 6, tablet: 6, desktop: 8 });
  });

  it("never leaves a lone card on the last row", () => {
    render(<HomeSpecialties specialties={specialties.slice(0, 5)} />);

    // 2 columns → 4, 3 columns → 3, 4 columns → 4.
    expect(visibleCounts(screen.getAllByRole("listitem"))).toEqual({
      phone: 4,
      tablet: 3,
      desktop: 4,
    });
  });

  it("has no drill-in chevrons on the cards", () => {
    const { container } = render(<HomeSpecialties specialties={specialties} />);

    for (const link of screen.getAllByRole("link")) {
      if (link.getAttribute("href") === "/doctors") continue;
      // Only the specialty's own icon remains.
      expect(link.querySelectorAll("svg")).toHaveLength(1);
    }
    expect(container.innerHTML).not.toContain("m9 18 6-6-6-6");
  });

  it("renders nothing without data", () => {
    const { container } = render(<HomeSpecialties specialties={[]} />);
    expect(container).toBeEmptyDOMElement();
  });
});

describe("HomeCities („Пребарај по град“)", () => {
  it("renders city chips that open the doctor directory filtered by city", async () => {
    const { container } = render(
      <HomeCities
        cities={[
          { name: "Скопје", doctors_count: 21 },
          { name: "Битола", doctors_count: 3 },
        ]}
      />,
    );

    const section = screen.getByRole("region", {
      name: t("homeSections.citiesTitle"),
    });
    expect(
      within(section).getByRole("link", { name: "Скопје 21 лекар" }),
    ).toHaveAttribute("href", `/doctors?city=${encodeURIComponent("Скопје")}`);
    expect(
      within(section).getByRole("link", { name: "Битола 3 лекари" }),
    ).toBeInTheDocument();
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("renders nothing without data", () => {
    const { container } = render(<HomeCities cities={[]} />);
    expect(container).toBeEmptyDOMElement();
  });
});

describe("HomeRecentReviews („Најнови рецензии“)", () => {
  const now = new Date("2026-10-06T08:00:00Z");
  const review = (overrides: Partial<HomeReview>): HomeReview => ({
    id: 1,
    rating: 5,
    excerpt: "Многу љубезен пристап.",
    author_name: "Марија од Битола",
    published_at: "2026-10-04T08:00:00Z",
    target: {
      kind: "doctor",
      slug: "ana-petrovska",
      name: "д-р Ана Петровска",
    },
    ...overrides,
  });

  it("shows author display name, stars, excerpt, relative date and the profile link", async () => {
    const { container } = render(
      <HomeRecentReviews
        now={now}
        reviews={[
          review({}),
          review({
            id: 2,
            rating: 4,
            author_name: "Петар Г.",
            published_at: "2026-10-06T07:00:00Z",
            target: {
              kind: "pharmacy",
              slug: "apteka-ohrid",
              name: "Аптека Охрид",
            },
          }),
        ]}
      />,
    );

    const section = screen.getByRole("region", {
      name: t("homeSections.reviewsTitle"),
    });
    const cards = within(section).getAllByRole("article");
    expect(cards).toHaveLength(2);

    const first = within(cards[0]!);
    expect(first.getByText("Марија од Битола")).toBeInTheDocument();
    expect(first.getByText("Многу љубезен пристап.")).toBeInTheDocument();
    expect(first.getByRole("img", { name: "5,0 / 5" })).toBeInTheDocument();
    expect(first.getByText("пред 2 дена")).toHaveAttribute(
      "datetime",
      "2026-10-04T08:00:00Z",
    );
    expect(first.getByRole("link")).toHaveAttribute(
      "href",
      "/doctors/ana-petrovska#reviews",
    );
    expect(first.getByRole("link")).toHaveAccessibleName("д-р Ана Петровска");

    const second = within(cards[1]!);
    expect(second.getByText("денес")).toBeInTheDocument();
    expect(second.getByRole("link")).toHaveAttribute(
      "href",
      "/pharmacies/apteka-ohrid#reviews",
    );
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("skips reviews without a target and hides when nothing is left", () => {
    const { container } = render(
      <HomeRecentReviews now={now} reviews={[review({ target: null })]} />,
    );
    expect(container).toBeEmptyDOMElement();
  });

  /** Cards shown per layout: 1 column on phones, 2 from md, all on lg. */
  function reviewCounts(items: HTMLElement[]) {
    const shown = (hiddenClass: string) =>
      items.filter((li) => !li.classList.contains(hiddenClass)).length;
    return {
      phone: shown("max-md:hidden"),
      tablet: shown("md:max-lg:hidden"),
      desktop: shown("lg:hidden"),
    };
  }

  it("shows three on phones and all four in the two-column and desktop grids", () => {
    render(
      <HomeRecentReviews
        now={now}
        reviews={[1, 2, 3, 4].map((id) => review({ id }))}
      />,
    );
    expect(reviewCounts(screen.getAllByRole("listitem"))).toEqual({
      phone: 3,
      tablet: 4,
      desktop: 4,
    });
  });

  it("never leaves a lone card in the two-column grid", () => {
    render(
      <HomeRecentReviews
        now={now}
        reviews={[1, 2, 3].map((id) => review({ id }))}
      />,
    );
    expect(reviewCounts(screen.getAllByRole("listitem"))).toEqual({
      phone: 3,
      tablet: 2,
      desktop: 3,
    });
  });
});

describe("HomeRecentlyViewed („Последно прегледани“)", () => {
  beforeEach(() => window.localStorage.clear());
  afterEach(() => window.localStorage.clear());

  const entry = {
    kind: "doctor" as const,
    slug: "ana-petrovska",
    name: "д-р Ана Петровска",
    subtitle: "Кардиологија · Скопје",
    avatarUrl: null,
  };

  it("is hidden when this device has viewed nothing", () => {
    render(<HomeRecentlyViewed />);
    expect(screen.queryByRole("region")).toBeNull();
    expect(screen.queryByRole("button")).toBeNull();
  });

  it("leaves out pharmacies while that module is off", () => {
    recordRecentlyViewed(entry);
    recordRecentlyViewed({
      kind: "pharmacy",
      slug: "apteka-ohrid",
      name: "Аптека Охрид",
      subtitle: null,
      avatarUrl: null,
    });

    render(<HomeRecentlyViewed pharmaciesOn={false} />);

    expect(
      screen.getAllByRole("link").map((l) => l.getAttribute("href")),
    ).toEqual(["/doctors/ana-petrovska"]);
  });

  it("is hidden when the only stored profiles are pharmacies and the module is off", () => {
    recordRecentlyViewed({
      kind: "pharmacy",
      slug: "apteka-ohrid",
      name: "Аптека Охрид",
      subtitle: null,
      avatarUrl: null,
    });

    render(<HomeRecentlyViewed pharmaciesOn={false} />);

    expect(screen.queryByRole("region")).toBeNull();
  });

  it("fills whole rows in the four-column desktop grid; the phone rail keeps all", () => {
    for (let i = 0; i < 5; i++) {
      recordRecentlyViewed({ ...entry, slug: `d-${i}` });
    }

    render(<HomeRecentlyViewed />);

    const items = screen.getAllByRole("listitem");
    expect(items).toHaveLength(5);
    expect(
      items.filter((li) => li.classList.contains("lg:hidden")),
    ).toHaveLength(1);
    expect(items.some((li) => li.className.includes("max-"))).toBe(false);
  });

  it("lists stored profiles newest first, linking to each profile", async () => {
    recordRecentlyViewed(entry);
    recordRecentlyViewed({
      kind: "facility",
      slug: "klinika-ana",
      name: "Клиника Ана",
      subtitle: null,
      avatarUrl: null,
    });

    const { container } = render(<HomeRecentlyViewed />);

    const section = screen.getByRole("region", {
      name: t("homeSections.recentTitle"),
    });
    const links = within(section).getAllByRole("link");
    expect(links.map((l) => l.getAttribute("href"))).toEqual([
      "/facilities/klinika-ana",
      "/doctors/ana-petrovska",
    ]);
    // No subtitle stored: the kind stands in.
    expect(links[0]).toHaveTextContent(t("homeSections.kindFacility"));
    expect(links[1]).toHaveTextContent("Кардиологија · Скопје");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("updates when a profile is recorded while the page is open", () => {
    render(<HomeRecentlyViewed />);
    expect(screen.queryByRole("region")).toBeNull();

    act(() => {
      recordRecentlyViewed(entry);
    });

    expect(
      screen.getByRole("link", { name: /д-р Ана Петровска/ }),
    ).toBeInTheDocument();
  });

  it("„Исчисти“ empties the list, announces it and keeps focus in place", async () => {
    const user = userEvent.setup();
    recordRecentlyViewed(entry);
    render(<HomeRecentlyViewed />);

    await user.click(
      screen.getByRole("button", { name: t("homeSections.recentClearLabel") }),
    );

    expect(screen.queryByRole("region")).toBeNull();
    expect(window.localStorage.getItem(RECENTLY_VIEWED_KEY)).toBeNull();
    expect(screen.getByRole("status")).toHaveTextContent(
      t("homeSections.recentCleared"),
    );
    expect(document.activeElement).not.toBe(document.body);
  });

  it("renders nothing, without throwing, when storage is blocked", () => {
    recordRecentlyViewed(entry);
    vi.spyOn(Storage.prototype, "getItem").mockImplementation(() => {
      throw new DOMException("blocked", "SecurityError");
    });

    render(<HomeRecentlyViewed />);

    expect(screen.queryByRole("region")).toBeNull();
  });
});

describe("RecordRecentlyViewed", () => {
  beforeEach(() => window.localStorage.clear());

  it("stores the visited profile on mount and renders nothing", () => {
    const { container } = render(
      <RecordRecentlyViewed
        kind="pharmacy"
        slug="apteka-ohrid"
        name="Аптека Охрид"
        subtitle="Аптека · Охрид"
        avatarUrl="https://media.example/logo.webp"
      />,
    );

    expect(container).toBeEmptyDOMElement();
    const stored = JSON.parse(
      window.localStorage.getItem(RECENTLY_VIEWED_KEY) ?? "[]",
    );
    expect(stored).toHaveLength(1);
    expect(stored[0]).toMatchObject({
      kind: "pharmacy",
      slug: "apteka-ohrid",
      name: "Аптека Охрид",
      subtitle: "Аптека · Охрид",
      avatarUrl: "https://media.example/logo.webp",
    });
  });

  it("does not throw when storage refuses the write", () => {
    vi.spyOn(Storage.prototype, "setItem").mockImplementation(() => {
      throw new DOMException("full", "QuotaExceededError");
    });

    expect(() =>
      render(<RecordRecentlyViewed kind="doctor" slug="x" name="д-р Икс" />),
    ).not.toThrow();
  });
});

describe("HomeHero phone search placeholder", () => {
  it("uses the short prompt so it is not clipped at 360–390px", () => {
    render(<HomeHero quickLinks={[]} />);

    expect(
      screen.getByRole("searchbox", { name: t("nav.searchWhat") }),
    ).toHaveAttribute("placeholder", t("nav.searchWhatPlaceholderShort"));
    expect(t("nav.searchWhatPlaceholderShort").length).toBeLessThanOrEqual(20);
  });
});
