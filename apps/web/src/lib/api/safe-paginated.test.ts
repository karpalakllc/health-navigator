import { describe, expect, it } from "vitest";
import { safePaginated } from "@/lib/api/safe-paginated";
import type { PaginatedEnvelope } from "@/lib/api/types";

describe("safePaginated", () => {
  it("passes a successful page through untouched", async () => {
    const page: PaginatedEnvelope<{ id: number }> = {
      data: [{ id: 1 }, { id: 2 }],
      meta: { current_page: 2, per_page: 2, total: 7, last_page: 4 },
    };

    await expect(safePaginated(async () => page)).resolves.toBe(page);
  });

  it("turns a failed fetch into an empty first page", async () => {
    await expect(
      safePaginated(async () => {
        throw new Error("API request failed (503)");
      }),
    ).resolves.toEqual({
      data: [],
      meta: { current_page: 1, per_page: 5, total: 0, last_page: 1 },
    });
  });

  it("keeps the caller's page size on the empty page", async () => {
    const result = await safePaginated(() => Promise.reject(new Error()), 12);

    expect(result.meta.per_page).toBe(12);
    expect(result.data).toEqual([]);
  });
});
