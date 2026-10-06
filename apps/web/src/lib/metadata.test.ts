import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";
import {
  DEFAULT_OG_IMAGE,
  listCanonicalPath,
  pageMetadata,
} from "@/lib/metadata";

describe("pageMetadata", () => {
  it("leaves `robots` out entirely for indexable pages", () => {
    // Next merges metadata key by key: a present-but-undefined `robots` would
    // erase the root layout's maintenance-mode noindex.
    expect(Object.keys(pageMetadata("Лекари"))).not.toContain("robots");
  });

  it("marks noIndex pages", () => {
    expect(pageMetadata("Сметка", undefined, { noIndex: true }).robots).toEqual(
      { index: false, follow: false },
    );
  });
});

describe("listCanonicalPath", () => {
  it("is the bare list without parameters or on page 1", () => {
    expect(listCanonicalPath("/doctors")).toBe("/doctors");
    expect(listCanonicalPath("/doctors", { page: "1" })).toBe("/doctors");
  });

  it("ignores campaign and click-tracking parameters, which are not filters", () => {
    expect(
      listCanonicalPath("/doctors", {
        page: "3",
        utm_source: "viber",
        utm_campaign: "launch",
        fbclid: "abc",
        gclid: "xyz",
      }),
    ).toBe("/doctors?page=3");
    expect(listCanonicalPath("/doctors", { utm_medium: "email" })).toBe(
      "/doctors",
    );
  });

  it("keeps an unfiltered deeper page", () => {
    expect(listCanonicalPath("/doctors", { page: "4" })).toBe(
      "/doctors?page=4",
    );
  });

  it("folds any filter, sort or query back into the bare list", () => {
    for (const params of [
      { specialty: "kardiologija" },
      { language: "angliski", page: "2" },
      { sort: "rating" },
      { q: "ана", city: "Скопје" },
      { category: ["a", "b"] },
    ]) {
      expect(listCanonicalPath("/doctors", params)).toBe("/doctors");
    }
  });

  it("ignores blank filters and clamps pages like the list does", () => {
    expect(listCanonicalPath("/facilities", { q: "  ", page: "2" })).toBe(
      "/facilities?page=2",
    );
    expect(listCanonicalPath("/facilities", { page: "x" })).toBe("/facilities");
    expect(listCanonicalPath("/facilities", { page: "999999" })).toBe(
      "/facilities?page=1000",
    );
  });
});

describe("pageMetadata share image", () => {
  it("sets the default image on OpenGraph, which the Twitter card inherits", () => {
    const metadata = pageMetadata("Лекари", undefined, { path: "/doctors" });

    expect(metadata.openGraph?.images).toEqual([
      expect.objectContaining({
        url: "/og-default.png",
        width: 1200,
        height: 630,
      }),
    ]);
    expect(metadata.twitter).toMatchObject({ card: "summary_large_image" });
  });

  it("points at a 1200×630 PNG that exists in public/", () => {
    const file = join(process.cwd(), "public", DEFAULT_OG_IMAGE.url);
    expect(existsSync(file)).toBe(true);

    const png = readFileSync(file);
    // PNG signature, then the IHDR chunk's big-endian width and height.
    expect(png.subarray(1, 4).toString("ascii")).toBe("PNG");
    expect(png.readUInt32BE(16)).toBe(1200);
    expect(png.readUInt32BE(20)).toBe(630);
  });
});
