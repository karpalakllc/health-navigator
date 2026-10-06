import { readFileSync } from "node:fs";
import { join } from "node:path";
import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { SkipLink } from "@/components/layout/skip-link";

/*
 * Owner decision (2026-10-06): no yellow focus ring and no yellow skip link.
 * jsdom does not apply stylesheets, so the CSS is checked as text.
 */
const css = readFileSync(join(__dirname, "globals.css"), "utf8").replace(
  /\/\*[\s\S]*?\*\//g,
  "",
);

/** The CSS outside every @layer block, i.e. the rules that win over layers. */
function unlayered(source: string): string {
  let out = "";
  let i = 0;
  while (i < source.length) {
    const at = source.indexOf("@layer", i);
    if (at === -1) {
      out += source.slice(i);
      break;
    }
    out += source.slice(i, at);
    let depth = 0;
    let j = source.indexOf("{", at);
    for (; j < source.length; j++) {
      if (source[j] === "{") depth++;
      if (source[j] === "}" && --depth === 0) break;
    }
    i = j + 1;
  }
  return out;
}

describe("focus style", () => {
  it("has no yellow anywhere", () => {
    expect(css.toLowerCase()).not.toContain("#ffdd00");
    expect(css).not.toContain("--color-focus:");
    expect(css).not.toContain("focus-ink");
  });

  it("draws a 2px ink ring with a 2px offset on an apricot halo, outside every layer", () => {
    expect(css).toContain("--color-focus-ring: var(--color-ink);");
    expect(css).toContain("--color-focus-halo: var(--color-apricot);");

    // Unlayered so shadow-card / chip box-shadows cannot swallow the halo.
    const rules = unlayered(css);
    expect(rules).toMatch(
      /\):focus-visible \{\s*outline: 2px solid var\(--color-focus-ring\);\s*outline-offset: 2px;\s*box-shadow: 0 0 0 6px var\(--color-focus-halo\);/,
    );
    // Text inputs on any focus (mouse too): ink border plus the halo.
    expect(rules).toMatch(
      /\.field-control:focus \{\s*border-color: var\(--color-focus-ring\);/,
    );
  });
});

describe("SkipLink look", () => {
  it("is a small ink pill with white text, only visible on focus", () => {
    render(<SkipLink />);

    const link = screen.getByRole("link", { name: "Прескокни до содржината" });
    expect(link).toHaveClass(
      "sr-only",
      "focus:not-sr-only",
      "focus:bg-ink",
      "focus:text-white",
      "focus:rounded-pill",
    );
    expect(link.className).not.toMatch(/focus:bg-focus|text-focus-ink/);
  });
});
