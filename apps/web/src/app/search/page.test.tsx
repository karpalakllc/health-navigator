import type { ReactElement } from "react";
import { describe, expect, it, vi } from "vitest";

vi.mock("@/components/search/unified-search-results", () => ({
  UnifiedSearchResults: () => null,
}));
vi.mock("@/components/search/search-states", () => ({
  enabledDirectorySections: async () => [],
}));

import SearchPage from "@/app/search/page";
import { UnifiedSearchResults } from "@/components/search/unified-search-results";
import { t } from "@/i18n/t";

function find(
  node: unknown,
  type: unknown,
): ReactElement<Record<string, unknown>> | null {
  if (Array.isArray(node)) {
    for (const child of node) {
      const hit = find(child, type);
      if (hit) return hit;
    }
    return null;
  }
  if (node && typeof node === "object" && "props" in node) {
    const element = node as ReactElement<Record<string, unknown>>;
    if (element.type === type) return element;
    return find(element.props.children, type) ?? find(element.props.form, type);
  }
  return null;
}

async function page(params: { q?: string; city?: string }) {
  return SearchPage({ searchParams: Promise.resolve(params) });
}

describe("/search page", () => {
  it("lists a whole city when only a city is chosen", async () => {
    const tree = await page({ q: "", city: "Скопје" });
    const results = find(tree, UnifiedSearchResults);

    expect(results).not.toBeNull();
    expect(results?.props.q).toBeUndefined();
    expect(results?.props.city).toBe("Скопје");
  });

  it("keeps requiring two characters when there is no city", async () => {
    const tree = await page({ q: "а" });

    expect(find(tree, UnifiedSearchResults)).toBeNull();
  });

  it("still searches by query alone", async () => {
    const tree = await page({ q: "кардио" });

    expect(find(tree, UnifiedSearchResults)?.props.q).toBe("кардио");
  });

  it("explains the city shortcut in the hint", () => {
    expect(t("search.queryHint")).toMatch(/град/i);
  });
});
