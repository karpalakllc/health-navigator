import { describe, expect, it } from "vitest";
import {
  isCacheableDirectoryQuery,
  MAX_CACHED_PAGE,
  MAX_LIST_PAGE,
  parseListPage,
  type DirectoryCacheRule,
} from "@/lib/api/directory-cache-policy";

const doctors: DirectoryCacheRule = {
  oneOf: { specialty: ["kardiologija"], sort: ["name", "rating"] },
  booleans: ["featured"],
  integers: { min_reviews: 10 },
};

describe("isCacheableDirectoryQuery", () => {
  it("accepts an empty or all-blank query", () => {
    expect(isCacheableDirectoryQuery({}, doctors)).toBe(true);
    expect(
      isCacheableDirectoryQuery({ q: " ", city: "", specialty: undefined }),
    ).toBe(true);
  });

  it("accepts known slugs, enums, booleans and early pages", () => {
    expect(
      isCacheableDirectoryQuery(
        {
          specialty: "kardiologija",
          sort: "rating",
          featured: true,
          page: MAX_CACHED_PAGE,
          per_page: 8,
          min_reviews: 2,
        },
        doctors,
      ),
    ).toBe(true);
    expect(
      isCacheableDirectoryQuery({ page: "3", featured: "1" }, doctors),
    ).toBe(true);
  });

  it.each([
    ["free text", { q: "ана" }],
    ["a city", { city: "Скопје" }],
    ["an unknown slug", { specialty: "kardiologija-2" }],
    ["a slug differing only in case", { specialty: "Kardiologija" }],
    ["an unknown sort", { sort: "price" }],
    ["a page past the cap", { page: MAX_CACHED_PAGE + 1 }],
    ["a fractional page", { page: 1.5 }],
    ["page zero", { page: 0 }],
    ["an exponent page", { page: "1e3" }],
    ["a padded page", { page: " 2" }],
    ["NaN", { page: NaN }],
    ["a non-boolean flag", { featured: "yes" }],
    ["an oversized per_page", { per_page: 51 }],
    ["an unknown parameter", { utm_source: "x" }],
    ["a non-string slug", { specialty: ["kardiologija"] }],
  ])("rejects %s", (_name, params) => {
    expect(isCacheableDirectoryQuery(params, doctors)).toBe(false);
  });

  it("rejects a slug when the taxonomy list is empty", () => {
    expect(
      isCacheableDirectoryQuery(
        { specialty: "kardiologija" },
        { oneOf: { specialty: [] } },
      ),
    ).toBe(false);
  });
});

describe("parseListPage", () => {
  it.each([
    [undefined, 1],
    ["", 1],
    ["abc", 1],
    ["1.5", 1],
    ["-3", 1],
    ["1e9", 1],
    ["0", 1],
    ["7", 7],
    ["1000", 1000],
    ["999999999999", MAX_LIST_PAGE],
  ])("reads %j as page %i", (raw, expected) => {
    expect(parseListPage(raw)).toBe(expected);
  });
});
