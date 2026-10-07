import { beforeEach, describe, expect, it, vi } from "vitest";

const loadPublicSettings = vi.hoisted(() => vi.fn());
const published = vi.hoisted(() => ({ slugs: [] as string[] }));

vi.mock("@/lib/api/settings", () => ({ loadPublicSettings }));
vi.mock("@/lib/api/doctors", () => ({
  fetchDoctors: vi.fn(async () => ({ data: [], meta: { last_page: 1 } })),
}));
vi.mock("@/lib/api/facilities", () => ({
  fetchFacilities: vi.fn(async () => ({ data: [], meta: { last_page: 1 } })),
}));
vi.mock("@/lib/api/pharmacies", () => ({ fetchPharmacies: vi.fn() }));
vi.mock("@/lib/api/products", () => ({ fetchProducts: vi.fn() }));
vi.mock("@/lib/api/forum", () => ({
  fetchForumCategories: vi.fn(),
  fetchForumTopics: vi.fn(),
  fetchForumTags: vi.fn(),
}));
// The real registry, with `published.slugs` signed off and published.
vi.mock("@/content/first-aid", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/content/first-aid")>();
  return {
    ...actual,
    publishedFirstAidGuides: () =>
      actual.FIRST_AID_GUIDES.filter((guide) =>
        published.slugs.includes(guide.slug),
      ),
  };
});

import sitemap from "@/app/sitemap";

async function urls(): Promise<string[]> {
  return (await sitemap()).map((entry) => entry.url);
}

beforeEach(() => {
  process.env.NEXT_PUBLIC_SITE_URL = "https://zdravje.test";
  published.slugs = [];
  loadPublicSettings.mockResolvedValue({
    degraded: false,
    public_forum: false,
    public_guidance: false,
    public_pharmacies: false,
    public_products: false,
  });
});

describe("sitemap first-aid entries", () => {
  it("lists no first-aid page while every guide is a draft", async () => {
    expect((await urls()).some((url) => url.includes("/prva-pomos"))).toBe(
      false,
    );
  });

  it("lists the index and only the published guides", async () => {
    published.slugs = ["kpr-vozrasni", "izgorenici"];
    const list = (await urls()).filter((url) => url.includes("/prva-pomos"));

    expect(list).toEqual([
      "https://zdravje.test/prva-pomos",
      "https://zdravje.test/prva-pomos/kpr-vozrasni",
      "https://zdravje.test/prva-pomos/izgorenici",
    ]);
  });
});
