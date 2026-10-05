import { describe, expect, it } from "vitest";
import { isPathEnabled, type ModuleFlags } from "@/lib/site-modules";

const allOff: ModuleFlags = {
  public_forum: false,
  public_guidance: false,
  public_pharmacies: false,
  public_products: false,
};

describe("isPathEnabled", () => {
  it("hides a switched-off module and everything under it", () => {
    expect(isPathEnabled("/forum", allOff)).toBe(false);
    expect(isPathEnabled("/forum/new", allOff)).toBe(false);
    expect(isPathEnabled("/pharmacies/x", allOff)).toBe(false);
    expect(isPathEnabled("/products", allOff)).toBe(false);
    expect(isPathEnabled("/guidance", allOff)).toBe(false);
  });

  it("shows an enabled module", () => {
    expect(isPathEnabled("/forum", { ...allOff, public_forum: true })).toBe(
      true,
    );
  });

  it("never hides core pages or lookalike prefixes", () => {
    expect(isPathEnabled("/doctors", allOff)).toBe(true);
    expect(isPathEnabled("/search", allOff)).toBe(true);
    expect(isPathEnabled("/forums-archive", allOff)).toBe(true);
  });
});
