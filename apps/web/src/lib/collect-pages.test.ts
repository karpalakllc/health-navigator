import { describe, expect, it } from "vitest";
import { collectPages } from "@/lib/collect-pages";

function pages(lastPage: number, failOn?: number) {
  const calls: number[] = [];
  const fetchPage = async (page: number) => {
    calls.push(page);
    if (page === failOn) {
      throw new Error("API request failed (500)");
    }
    return { data: [`p${page}`], meta: { last_page: lastPage } };
  };
  return { calls, fetchPage };
}

describe("collectPages", () => {
  it("walks to the last page", async () => {
    const { fetchPage } = pages(3);
    expect(await collectPages(fetchPage, 40)).toEqual(["p1", "p2", "p3"]);
  });

  it("keeps what it has when a page fails, without throwing", async () => {
    const { fetchPage } = pages(5, 3);
    expect(await collectPages(fetchPage, 40)).toEqual(["p1", "p2"]);
  });

  it("stops at maxPages", async () => {
    const { calls, fetchPage } = pages(100);
    await collectPages(fetchPage, 2);
    expect(calls).toEqual([1, 2]);
  });

  it("lets independent listings survive one another's failure", async () => {
    // The sitemap walks forum categories one by one; a failure in the first
    // used to abort every category after it.
    const broken = pages(2, 1);
    const healthy = pages(1);

    const results = [
      await collectPages(broken.fetchPage, 40),
      await collectPages(healthy.fetchPage, 40),
    ];

    expect(results).toEqual([[], ["p1"]]);
  });
});
