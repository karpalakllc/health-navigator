import { render, screen, within } from "@testing-library/react";
import type { ReactElement } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  SearchEmptyState,
  SearchErrorState,
} from "@/components/search/search-states";
import { UnifiedSearchResults } from "@/components/search/unified-search-results";
import { SearchDialogProvider } from "@/components/layout/search-dialog-context";
import type { DoctorListItem, UnifiedSearchResult } from "@/lib/api/types";
import { publicSettingsDefaults } from "@/lib/api/public-settings";
import { t, tFormat } from "@/i18n/t";

const fetchUnifiedSearch = vi.fn();
// A plain flag rather than a rejecting vi.fn: Vitest reports an Error a mock
// rejected with as a test failure even when the code under test caught it.
const api = { down: false };
vi.mock("@/lib/api/search", () => ({
  fetchUnifiedSearch: async (...args: unknown[]) => {
    if (api.down) {
      throw new Error("API request failed (503)");
    }
    return fetchUnifiedSearch(...args);
  },
}));
vi.mock("@/lib/api/settings", () => ({
  fetchPublicSettings: async () => ({
    ...publicSettingsDefaults,
    public_pharmacies: false,
    public_products: false,
  }),
}));

const empty = {
  data: [],
  meta: { current_page: 1, per_page: 5, total: 0, last_page: 1 },
};

function result(over: Partial<UnifiedSearchResult> = {}): UnifiedSearchResult {
  return {
    doctors: empty,
    facilities: empty,
    pharmacies: empty,
    products: empty,
    forum_topics: empty,
    grand_total: 0,
    ...over,
  };
}

/** Whether a (server-rendered) element tree holds an element of `type`. */
function containsElement(node: unknown, type: unknown): boolean {
  if (Array.isArray(node)) {
    return node.some((child) => containsElement(child, type));
  }
  if (node && typeof node === "object" && "props" in node) {
    const element = node as ReactElement<{ children?: unknown }>;
    return (
      element.type === type || containsElement(element.props.children, type)
    );
  }
  return false;
}

async function renderResults(q: string | undefined, city?: string) {
  const element = await UnifiedSearchResults({ q, city });
  return render(<SearchDialogProvider>{element}</SearchDialogProvider>);
}

const dominika: DoctorListItem = {
  slug: "dominika-slavkoska",
  full_name: "Доминика Славкоска",
  title: null,
  subspecialty: null,
  city: "Скопје",
  avatar_url: null,
  years_experience: null,
  accepts_new_patients: true,
  is_featured: false,
  is_sponsored: false,
  primary_specialty: null,
  specialties: [],
  primary_facility: null,
  review_summary: { count: 0, average_rating: null },
};

describe("UnifiedSearchResults (/search)", () => {
  beforeEach(() => {
    fetchUnifiedSearch.mockReset();
    api.down = false;
  });

  it("says fuzzy results are approximate and offers „Дали мислевте“", async () => {
    fetchUnifiedSearch.mockResolvedValue(
      result({
        doctors: {
          data: [dominika],
          meta: { current_page: 1, per_page: 5, total: 1, last_page: 1 },
        },
        grand_total: 1,
        fuzzy: true,
        suggestion: "Доминика",
      }),
    );

    await renderResults("domenika", "Скопје");

    const notice = screen.getByRole("status");
    expect(notice).toHaveTextContent(
      tFormat("search.fuzzyNoExact", { q: "domenika" }),
    );
    expect(notice).toHaveTextContent(`${t("search.didYouMean")} Доминика?`);
    // The suggestion re-runs the search with the corrected query, same city.
    expect(
      within(notice).getByRole("link", { name: "Доминика" }),
    ).toHaveAttribute(
      "href",
      `/search?q=${encodeURIComponent("Доминика")}&city=${encodeURIComponent("Скопје")}`,
    );
    expect(
      screen.getAllByRole("link", { name: /Доминика Славкоска/ }).length,
    ).toBeGreaterThan(0);
  });

  it("shows no fuzzy notice for exact results", async () => {
    fetchUnifiedSearch.mockResolvedValue(
      result({
        doctors: {
          data: [dominika],
          meta: { current_page: 1, per_page: 5, total: 1, last_page: 1 },
        },
        grand_total: 1,
        fuzzy: false,
        suggestion: null,
      }),
    );

    await renderResults("dominika");

    expect(
      screen.queryByText(tFormat("search.fuzzyNoExact", { q: "dominika" })),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByText(t("search.didYouMean"), { exact: false }),
    ).toBeNull();
  });

  it("groups hits by vertical with a jump chip, a count and „Види ги сите“", async () => {
    fetchUnifiedSearch.mockResolvedValue(
      result({
        forum_topics: {
          data: [
            {
              slug: "pritisok",
              title: "Колку често да мерам притисок?",
              author_name: "Ана М.",
              replies_count: 2,
              last_post_at: null,
              published_at: null,
              category: { slug: "srce", name: "Срце" },
            },
          ],
          meta: { current_page: 1, per_page: 5, total: 1, last_page: 1 },
        },
        grand_total: 1,
      }),
    );

    await renderResults("притисок", "Скопје");

    expect(fetchUnifiedSearch).toHaveBeenCalledWith({
      q: "притисок",
      city: "Скопје",
      per_page: 5,
    });
    const forum = screen.getByRole("region", {
      name: t("search.sectionForum"),
    });
    expect(
      within(forum).getByRole("link", {
        name: "Колку често да мерам притисок?",
      }),
    ).toHaveAttribute("href", "/forum/srce/pritisok");
    expect(
      within(forum).getByRole("link", {
        name: `${t("search.viewAllInSection")}: ${t("search.sectionForum")}`,
      }),
    ).toHaveAttribute("href", `/forum?q=${encodeURIComponent("притисок")}`);

    // The applied city is a removable chip that keeps the query.
    expect(
      screen.getByRole("link", {
        name: tFormat("ui.removeFilter", {
          label: tFormat("search.cityFilter", { city: "Скопје" }),
        }),
      }),
    ).toHaveAttribute("href", `/search?q=${encodeURIComponent("притисок")}`);
    // Empty verticals are not rendered.
    expect(
      screen.queryByRole("region", { name: t("search.sectionDoctors") }),
    ).not.toBeInTheDocument();

    // The chip row scrolls and so clips: padding keeps the focus ring whole.
    const chips = within(
      screen.getByRole("navigation", { name: t("search.sectionsNav") }),
    )
      .getByRole("list")
      .className.split(/\s+/);
    expect(chips).toEqual(expect.arrayContaining(["py-2.5", "-my-2.5"]));
  });

  it("lists a whole city without a query, larger pages, no forum or products", async () => {
    fetchUnifiedSearch.mockResolvedValue(
      result({
        doctors: {
          data: [
            {
              slug: "ana",
              full_name: "д-р Ана Петровска",
              title: null,
              subspecialty: null,
              city: "Скопје",
              avatar_url: null,
              years_experience: null,
              accepts_new_patients: true,
              is_featured: false,
              is_sponsored: false,
              primary_specialty: null,
              primary_facility: null,
              review_summary: { count: 0, average_rating: null },
            },
          ],
          meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
        },
        grand_total: 1,
      }),
    );

    await renderResults(undefined, "Скопје");

    expect(fetchUnifiedSearch).toHaveBeenCalledWith({
      q: undefined,
      city: "Скопје",
      per_page: 10,
    });
    expect(
      screen.getByRole("region", { name: t("search.sectionDoctors") }),
    ).toBeInTheDocument();
    // „Види ги сите“ carries only the city.
    expect(
      screen.getByRole("link", {
        name: `${t("search.viewAllInSection")}: ${t("search.sectionDoctors")}`,
      }),
    ).toHaveAttribute("href", `/doctors?city=${encodeURIComponent("Скопје")}`);
    // Removing the city chip leaves the hub, not an empty search.
    expect(
      screen.getByRole("link", {
        name: tFormat("ui.removeFilter", {
          label: tFormat("search.cityFilter", { city: "Скопје" }),
        }),
      }),
    ).toHaveAttribute("href", "/search");
  });

  it("offers matching specialties as shortcuts into the doctor list, same city", async () => {
    fetchUnifiedSearch.mockResolvedValue(
      result({
        specialties: [
          { slug: "kardiologija", name: "Кардиологија", doctors_count: 4 },
        ],
        forum_topics: {
          data: [
            {
              slug: "srce",
              title: "Кардиолог во Скопје?",
              author_name: "Ана М.",
              replies_count: 0,
              last_post_at: null,
              published_at: null,
              category: { slug: "srce", name: "Срце" },
            },
          ],
          meta: { current_page: 1, per_page: 5, total: 1, last_page: 1 },
        },
        grand_total: 1,
      }),
    );

    await renderResults("кардио", "Скопје");

    const nav = screen.getByRole("navigation", {
      name: t("homeSearch.resultsSpecialties"),
    });
    expect(
      within(nav).getByRole("link", { name: /Кардиологија/ }),
    ).toHaveAttribute(
      "href",
      `/doctors?city=${encodeURIComponent("Скопје")}&specialty=kardiologija`,
    );
  });

  it("shows the empty state when no vertical has hits", async () => {
    fetchUnifiedSearch.mockResolvedValue(result());

    const element = await UnifiedSearchResults({ q: "xyzzy" });
    // The empty state is an async server component; resolve it like RSC does.
    const emptyState = await SearchEmptyState({ q: "xyzzy" });
    expect(containsElement(element, SearchEmptyState)).toBe(true);
    render(emptyState);

    expect(
      screen.getByRole("heading", {
        name: tFormat("search.emptyTitle", { q: "xyzzy" }),
      }),
    ).toBeInTheDocument();
    // Only enabled directories are offered (pharmacies/products are off).
    expect(
      screen.getByRole("link", { name: t("home.doctorsTitle") }),
    ).toHaveAttribute("href", "/doctors?q=xyzzy");
    expect(
      screen.queryByRole("link", { name: t("home.pharmaciesTitle") }),
    ).not.toBeInTheDocument();
  });

  it("shows an ink error state with a retry link when the API fails", async () => {
    api.down = true;

    const element = await UnifiedSearchResults({ q: "кардио" });
    render(element);

    const alert = screen.getByRole("alert");
    expect(
      within(alert).getByRole("heading", { name: t("search.errorTitle") }),
    ).toBeInTheDocument();
    expect(
      within(alert).getByRole("link", { name: t("search.retry") }),
    ).toHaveAttribute("href", `/search?q=${encodeURIComponent("кардио")}`);
  });

  it("the error state keeps the city in the retry link", () => {
    render(<SearchErrorState q="кардио" city="Битола" />);

    expect(
      screen.getByRole("link", { name: t("search.retry") }),
    ).toHaveAttribute(
      "href",
      `/search?q=${encodeURIComponent("кардио")}&city=${encodeURIComponent("Битола")}`,
    );
  });
});

describe("UnifiedSearchResults: on-duty pharmacies", () => {
  beforeEach(() => {
    fetchUnifiedSearch.mockReset();
    api.down = false;
  });

  it("badges a pharmacy on duty today and states ФЗОМ's caveat once for the group", async () => {
    const pharmacy = (slug: string, onDuty: boolean) => ({
      slug,
      name: `Аптека ${slug}`,
      city: "Битола",
      avatar_url: null,
      review_summary: { count: 0, average_rating: null },
      on_duty_today: onDuty
        ? { date: "2026-10-07", mode: "all_day" as const, hours_text: null }
        : null,
    });
    fetchUnifiedSearch.mockResolvedValue(
      result({
        pharmacies: {
          data: [pharmacy("vita", true), pharmacy("bela", false)],
          meta: { current_page: 1, per_page: 5, total: 2, last_page: 1 },
        },
        grand_total: 2,
      }),
    );

    await renderResults("аптека");

    const group = screen.getByRole("region", {
      name: t("search.sectionPharmacies"),
    });
    expect(
      within(group).getByText("Дежурна денес · 24 часа"),
    ).toBeInTheDocument();
    expect(
      within(group).getAllByText(t("urgentCare.pharmaciesSource")),
    ).toHaveLength(1);
  });
});
