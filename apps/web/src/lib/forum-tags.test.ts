import { describe, expect, it } from "vitest";
import { parseTagInput } from "@/lib/forum-tags";

describe("parseTagInput", () => {
  it("splits on commas, trims, drops # and duplicates", () => {
    expect(
      parseTagInput(" #проширени вени, операција ,, Операција; цена"),
    ).toEqual({
      ok: true,
      tags: ["проширени вени", "операција", "цена"],
    });
    expect(parseTagInput("")).toEqual({ ok: true, tags: [] });
  });

  it("refuses more than five keywords", () => {
    expect(parseTagInput("аа, бб, вв, гг, дд, ѓѓ")).toEqual({
      ok: false,
      reason: "too-many",
    });
  });

  it("refuses too short, too long or numeric keywords", () => {
    expect(parseTagInput("а").ok).toBe(false);
    expect(parseTagInput("2026").ok).toBe(false);
    expect(parseTagInput("а".repeat(41)).ok).toBe(false);
  });
});
