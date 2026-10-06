import { beforeEach, describe, expect, it, vi } from "vitest";

const loadPublicSettings = vi.hoisted(() => vi.fn());
const fetchDoctors = vi.hoisted(() => vi.fn());
const fetchFacilities = vi.hoisted(() => vi.fn());
const fetchPharmacies = vi.hoisted(() => vi.fn());
const fetchProducts = vi.hoisted(() => vi.fn());
const fetchForumCategories = vi.hoisted(() => vi.fn());
const fetchForumTopicSearch = vi.hoisted(() => vi.fn());

vi.mock("@/lib/api/settings", () => ({ loadPublicSettings }));
vi.mock("@/lib/api/doctors", () => ({ fetchDoctors }));
vi.mock("@/lib/api/facilities", () => ({ fetchFacilities }));
vi.mock("@/lib/api/pharmacies", () => ({ fetchPharmacies }));
vi.mock("@/lib/api/products", () => ({ fetchProducts }));
vi.mock("@/lib/api/forum", () => ({
  fetchForumCategories,
  fetchForumTopicSearch,
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
});

async function urls(): Promise<string[]> {
  return (await sitemap()).map((entry) => entry.url);
}

describe("sitemap", () => {
  it("does not advertise the noindex search page", async () => {
    const list = await urls();

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

  it("keeps the rest when the pharmacy listing fails", async () => {
    fetchPharmacies.mockRejectedValue(new Error("API request failed (500)"));

    const list = await urls();

    expect(list).toContain("https://zdravje.test/products/ibuprofen-400");
    expect(list).toContain("https://zdravje.test/doctors/d-r-ana");
  });
});
