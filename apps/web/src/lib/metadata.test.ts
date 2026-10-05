import { describe, expect, it } from "vitest";
import { pageMetadata } from "@/lib/metadata";

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
