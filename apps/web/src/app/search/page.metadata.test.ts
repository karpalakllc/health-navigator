import { describe, expect, it, vi } from "vitest";

vi.mock("@/components/search/unified-search-results", () => ({
  UnifiedSearchResults: () => null,
}));

import { metadata } from "@/app/search/page";

describe("/search metadata", () => {
  it("keeps search result pages out of the index", () => {
    expect(metadata.robots).toEqual({ index: false, follow: false });
  });
});
