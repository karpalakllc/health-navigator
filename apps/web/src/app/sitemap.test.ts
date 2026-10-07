import { beforeEach, describe, expect, it, vi } from "vitest";

const loadPublicSettings = vi.hoisted(() => vi.fn());
const fetchDoctors = vi.hoisted(() => vi.fn());
const fetchFacilities = vi.hoisted(() => vi.fn());
const fetchPharmacies = vi.hoisted(() => vi.fn());
const fetchProducts = vi.hoisted(() => vi.fn());
const fetchForumCategories = vi.hoisted(() => vi.fn());
const fetchForumTopics = vi.hoisted(() => vi.fn());
const fetchForumTags = vi.hoisted(() => vi.fn());
const fetchUrgentCareCities = vi.hoisted(() => vi.fn());

vi.mock("@/lib/api/settings", () => ({ loadPublicSettings }));
vi.mock("@/lib/api/doctors", () => ({ fetchDoctors }));
vi.mock("@/lib/api/facilities", () => ({ fetchFacilities }));
vi.mock("@/lib/api/pharmacies", () => ({ fetchPharmacies }));
vi.mock("@/lib/api/products", () => ({ fetchProducts }));
vi.mock("@/lib/api/urgent-care", () => ({ fetchUrgentCareCities }));
vi.mock("@/lib/api/forum", () => ({
  fetchForumCategories,
  fetchForumTopics,
  fetchForumTags,
}));

import sitemap, { revalidate } from "@/app/sitemap";

function page(slugs: string[]) {
  return {
    data: slugs.map((slug) => ({ slug })),
    meta: { last_page: 1 },
  };
}

const modulesOn = {
  degraded: false,
  public_forum: false,
  public_guidance: true,
  public_pharmacies: true,
  public_products: true,
};

beforeEach(() => {
  process.env.NEXT_PUBLIC_SITE_URL = "https://zdravje.test";
  vi.clearAllMocks();
  loadPublicSettings.mockResolvedValue(modulesOn);
  fetchDoctors.mockResolvedValue(page(["d-r-ana"]));
  fetchFacilities.mockResolvedValue(page(["klinika"]));
  fetchPharmacies.mockResolvedValue(page(["zegin-centar", "jakafarm"]));
  fetchProducts.mockResolvedValue(page(["ibuprofen-400"]));
  fetchUrgentCareCities.mockResolvedValue([]);
});

async function urls(): Promise<string[]> {
  return (await sitemap()).map((entry) => entry.url);
}

describe("sitemap", () => {
  it("does not advertise the noindex search page", async () => {
    const list = await urls();

    expect(list).toContain("https://zdravje.test/transparency");
    expect(list).toContain("https://zdravje.test/community");
    expect(list).toContain("https://zdravje.test/doctors");
    expect(list.some((url) => url.includes("/search"))).toBe(false);
  });

  it("lists pharmacy and product profiles while their modules are on", async () => {
    const list = await urls();

    expect(list).toEqual(
      expect.arrayContaining([
        "https://zdravje.test/doctors/d-r-ana",
        "https://zdravje.test/facilities/klinika",
        "https://zdravje.test/pharmacies/zegin-centar",
        "https://zdravje.test/pharmacies/jakafarm",
        "https://zdravje.test/products/ibuprofen-400",
      ]),
    );
  });

  it("walks pharmacies and products in the sitemap's own cache window", async () => {
    await sitemap();

    expect(fetchPharmacies).toHaveBeenCalledWith(
      { page: 1, per_page: 50 },
      { revalidate },
    );
    expect(fetchProducts).toHaveBeenCalledWith(
      { page: 1, per_page: 50 },
      { revalidate },
    );
  });

  it("leaves both out (and does not call the API) while switched off", async () => {
    loadPublicSettings.mockResolvedValue({
      ...modulesOn,
      public_pharmacies: false,
      public_products: false,
    });

    const list = await urls();

    expect(list.some((url) => url.includes("/pharmacies"))).toBe(false);
    expect(list.some((url) => url.includes("/products"))).toBe(false);
    expect(fetchPharmacies).not.toHaveBeenCalled();
    expect(fetchProducts).not.toHaveBeenCalled();
  });

  it("lists „Каде веднаш“, its city pages with places, and the guides", async () => {
    fetchUrgentCareCities.mockResolvedValue([
      { name: "Скопје", total: 3, ed: 2, ems: 1, clinic: 0, dental: 0 },
      { name: "Битола", total: 1, ed: 1, ems: 0, clinic: 0, dental: 0 },
      // Not on the territorial list: no city page to advertise.
      { name: "Непознато", total: 1, ed: 1, ems: 0, clinic: 0, dental: 0 },
    ]);

    const list = await urls();

    expect(list).toEqual(
      expect.arrayContaining([
        "https://zdravje.test/urgent-care",
        "https://zdravje.test/urgent-care/skopje",
        "https://zdravje.test/urgent-care/bitola",
        "https://zdravje.test/guides",
        "https://zdravje.test/guides/kako-do-uput",
        "https://zdravje.test/guides/participacija",
      ]),
    );
    expect(list.filter((url) => url.includes("/urgent-care/"))).toHaveLength(2);
  });

  it("keeps the rest when the urgent-care cities fail", async () => {
    fetchUrgentCareCities.mockRejectedValue(
      new Error("API request failed (500)"),
    );

    const list = await urls();

    expect(list).toContain("https://zdravje.test/urgent-care");
    expect(list).toContain("https://zdravje.test/doctors/d-r-ana");
  });

  it("keeps the rest when the pharmacy listing fails", async () => {
    fetchPharmacies.mockRejectedValue(new Error("API request failed (500)"));

    const list = await urls();

    expect(list).toContain("https://zdravje.test/products/ibuprofen-400");
    expect(list).toContain("https://zdravje.test/doctors/d-r-ana");
  });
});

describe("sitemap forum entries", () => {
  beforeEach(() => {
    loadPublicSettings.mockResolvedValue({ ...modulesOn, public_forum: true });
    fetchForumCategories.mockResolvedValue([
      { slug: "hirurgija", name: "Хирургија", description: null },
    ]);
    fetchForumTopics.mockResolvedValue({
      data: [
        {
          slug: "operacija-za-prosireni-veni",
          last_post_at: "2026-10-05T10:00:00+02:00",
          published_at: "2026-10-01T10:00:00+02:00",
        },
        {
          slug: "bez-odgovor",
          last_post_at: null,
          published_at: "2026-09-01T10:00:00+02:00",
        },
      ],
      meta: { last_page: 1 },
    });
    fetchForumTags.mockResolvedValue({
      data: [
        {
          slug: "prosireni-veni",
          topics_count: 4,
          last_activity_at: "2026-10-05T10:00:00+02:00",
        },
        { slug: "retko", topics_count: 2, last_activity_at: null },
      ],
      meta: { last_page: 1 },
    });
  });

  it("lists every topic per category from the topic listing, with lastmod from activity", async () => {
    const entries = await sitemap();
    const byUrl = new Map(entries.map((entry) => [entry.url, entry]));

    expect(fetchForumTopics).toHaveBeenCalledWith(
      "hirurgija",
      { page: 1, per_page: 50 },
      { revalidate },
    );
    expect(
      byUrl.get(
        "https://zdravje.test/forum/hirurgija/operacija-za-prosireni-veni",
      )?.lastModified,
    ).toBe("2026-10-05T10:00:00+02:00");
    expect(
      byUrl.get("https://zdravje.test/forum/hirurgija/bez-odgovor")
        ?.lastModified,
    ).toBe("2026-09-01T10:00:00+02:00");
    expect(
      byUrl.get("https://zdravje.test/forum/hirurgija")?.lastModified,
    ).toBe("2026-10-05T10:00:00+02:00");
  });

  it("lists only indexable tag pages (three or more topics)", async () => {
    const list = await urls();

    expect(fetchForumTags).toHaveBeenCalledWith(
      { min_topics: 3, page: 1, per_page: 50 },
      { revalidate },
    );
    expect(list).toContain("https://zdravje.test/forum/tags/prosireni-veni");
    expect(list).not.toContain("https://zdravje.test/forum/tags/retko");
  });

  it("keeps the directory when the forum categories fail", async () => {
    fetchForumCategories.mockRejectedValue(
      new Error("API request failed (500)"),
    );

    const list = await urls();

    expect(list).toContain("https://zdravje.test/doctors/d-r-ana");
    expect(list.some((url) => url.includes("/forum/"))).toBe(false);
  });
});
