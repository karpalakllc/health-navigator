import { render, screen } from "@testing-library/react";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import { StickyActionBar } from "@/components/ui/sticky-action-bar";

/*
 * WCAG 2.4.11 (focus not obscured): jsdom has no layout and no :has(), so
 * guard the contract instead: globals.css reserves room for the bottom tab
 * bar and for any mounted sticky bar, and the sticky bars carry the
 * attribute those rules look for.
 */
const css = readFileSync(
  resolve(process.cwd(), "src/app/globals.css"),
  "utf8",
).replace(/\s+/g, " ");

describe("scroll padding for sticky UI", () => {
  it("always keeps scrolled-to elements above the bottom tab bar", () => {
    expect(css).toMatch(
      /html \{[^}]*scroll-padding-bottom: calc\(var\(--tabbar-space\) \+ 0\.75rem\)/,
    );
  });

  it("adds a visible sticky action bar's height while one is mounted", () => {
    expect(css).toMatch(
      /html:has\(\[data-sticky-action-bar\]:not\(\[data-hidden\]\)\) \{ scroll-padding-bottom: calc\(var\(--tabbar-space\) \+ 5rem \+ 0\.75rem\);/,
    );
  });

  it("adds the list pages' sticky search strip to the top padding", () => {
    expect(css).toMatch(
      /html:has\(\[data-sticky-search-strip\]\) \{ scroll-padding-top: calc\(var\(--header-h\) \+ 4\.75rem \+ 0\.75rem\);/,
    );
  });

  it("marks StickyActionBar for those rules", () => {
    render(
      <StickyActionBar label="Брз контакт">
        <button type="button">Јави се</button>
      </StickyActionBar>,
    );

    expect(screen.getByRole("region", { name: "Брз контакт" })).toHaveAttribute(
      "data-sticky-action-bar",
    );
  });
});
