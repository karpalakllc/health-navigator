import { describe, expect, it, vi } from "vitest";
import type { Metadata } from "next";

vi.mock("@/lib/api/settings", () => ({
  fetchPublicSettings: vi.fn(async () => ({
    public_forum: true,
    public_guidance: true,
    public_pharmacies: true,
    public_products: true,
  })),
}));

import * as about from "@/app/about/page";
import * as disclaimer from "@/app/disclaimer/page";
import * as doctors from "@/app/doctors/page";
import * as facilities from "@/app/facilities/page";
import * as forum from "@/app/forum/page";
import * as guidance from "@/app/guidance/page";
import * as pharmacies from "@/app/pharmacies/page";
import * as products from "@/app/products/page";
import * as search from "@/app/search/page";
import { DEFAULT_OG_IMAGE } from "@/lib/metadata";

type ListPage = {
  generateMetadata: (props: {
    searchParams: Promise<Record<string, string>>;
  }) => Promise<Metadata>;
};

function canonical(metadata: Metadata): unknown {
  return metadata.alternates?.canonical;
}

/**
 * G3: every indexable list route declares a canonical, so filter and sort
 * variants (?city=, ?sort=, ?q=) consolidate on the list itself.
 */
describe("list route canonicals", () => {
  it.each([
    ["/doctors", doctors as unknown as ListPage],
    ["/facilities", facilities as unknown as ListPage],
    ["/pharmacies", pharmacies as unknown as ListPage],
    ["/products", products as unknown as ListPage],
    ["/forum", forum as unknown as ListPage],
  ])(
    "%s points filtered views at itself and keeps ?page=N",
    async (path, page) => {
      const meta = (params: Record<string, string>) =>
        page.generateMetadata({ searchParams: Promise.resolve(params) });

      expect(canonical(await meta({}))).toBe(path);
      expect(canonical(await meta({ q: "ана", page: "3" }))).toBe(path);
      expect(canonical(await meta({ page: "2" }))).toBe(`${path}?page=2`);
      expect((await meta({})).openGraph?.url).toBe(path);
    },
  );

  it("points /guidance at itself", async () => {
    expect(canonical(await guidance.generateMetadata())).toBe("/guidance");
  });

  it.each([
    ["/about", about.metadata],
    ["/disclaimer", disclaimer.metadata],
    ["/search", search.metadata],
  ])("points %s at itself", (path, metadata) => {
    expect(canonical(metadata)).toBe(path);
  });

  it("gives every one the default share image", async () => {
    const metadata = await doctors.generateMetadata({
      searchParams: Promise.resolve({}),
    });

    expect(metadata.openGraph?.images).toEqual([DEFAULT_OG_IMAGE]);
  });
});
