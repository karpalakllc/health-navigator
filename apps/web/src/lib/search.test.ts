import { describe, expect, it } from "vitest";
import {
  SEARCH_MIN_LENGTH,
  directorySearchHref,
  normalizeSearchQuery,
  searchQueryParams,
} from "@/lib/search";

/**
 * normalizeSearchQuery mirrors SearchQuery::normalize on the API side. If the two
 * drift, the web sends queries the API silently discards (or vice versa), and
 * nothing fails loudly — so the shared rule is pinned here.
 */
describe("normalizeSearchQuery", () => {
  it("matches the API's two-character minimum", () => {
    expect(SEARCH_MIN_LENGTH).toBe(2);
    expect(normalizeSearchQuery("a")).toBeUndefined();
    expect(normalizeSearchQuery("ab")).toBe("ab");
  });

  it("trims before measuring length", () => {
    expect(normalizeSearchQuery("  a  ")).toBeUndefined();
    expect(normalizeSearchQuery("  ab  ")).toBe("ab");
  });

  it("treats empty and whitespace-only input as absent", () => {
    expect(normalizeSearchQuery(undefined)).toBeUndefined();
    expect(normalizeSearchQuery("")).toBeUndefined();
    expect(normalizeSearchQuery("   ")).toBeUndefined();
  });

  it("counts Cyrillic characters, not bytes", () => {
    // "ср" is two characters but four UTF-8 bytes — a byte-length check would
    // wrongly accept a single-character query here.
    expect(normalizeSearchQuery("ср")).toBe("ср");
    expect(normalizeSearchQuery("с")).toBeUndefined();
  });
});

describe("searchQueryParams", () => {
  it("omits keys rather than sending empty values", () => {
    expect(searchQueryParams(undefined, undefined)).toEqual({});
    expect(searchQueryParams("a", "  ")).toEqual({});
  });

  it("includes both when present", () => {
    expect(searchQueryParams("кардио", "Скопје")).toEqual({
      q: "кардио",
      city: "Скопје",
    });
  });

  it("keeps a city even when the query is too short to send", () => {
    expect(searchQueryParams("a", "Битола")).toEqual({ city: "Битола" });
  });
});

describe("directorySearchHref", () => {
  it("returns a bare path when nothing is set", () => {
    expect(directorySearchHref("/doctors", undefined)).toBe("/doctors");
  });

  it("encodes Cyrillic query values", () => {
    expect(directorySearchHref("/doctors", "кардиолог")).toBe(
      "/doctors?q=%D0%BA%D0%B0%D1%80%D0%B4%D0%B8%D0%BE%D0%BB%D0%BE%D0%B3",
    );
  });

  it("merges extra params after the search params", () => {
    expect(
      directorySearchHref("/doctors", "ab", undefined, { page: "2" }),
    ).toBe("/doctors?q=ab&page=2");
  });
});
