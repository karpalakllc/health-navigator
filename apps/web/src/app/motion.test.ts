import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";

/*
 * The motion system lives in globals.css. jsdom computes no styles, so read
 * the source: every hover must be limited to hover-capable pointers, every
 * movement must sit behind prefers-reduced-motion, and the reveal must never
 * hide server-rendered content on its own.
 */
const css = readFileSync(
  fileURLToPath(new URL("./globals.css", import.meta.url)),
  "utf8",
);

/** The bodies of every `@media <query> { … }` block whose query matches. */
function mediaBlocks(query: RegExp): string[] {
  const blocks: string[] = [];
  const pattern = /@media\s*([^{]+)\{/g;
  let match: RegExpExecArray | null;
  while ((match = pattern.exec(css))) {
    if (!query.test(match[1])) continue;
    let depth = 1;
    let i = pattern.lastIndex;
    while (depth > 0 && i < css.length) {
      if (css[i] === "{") depth++;
      if (css[i] === "}") depth--;
      i++;
    }
    blocks.push(css.slice(pattern.lastIndex, i - 1));
  }
  return blocks;
}

/** CSS outside every reduced-motion `no-preference` block. */
const outsideNoPreference = mediaBlocks(
  /prefers-reduced-motion:\s*no-preference/,
).reduce((rest, block) => rest.replace(block, ""), css);

describe("motion system (globals.css)", () => {
  it("zeroes every animation and transition under reduced motion", () => {
    const reduce = mediaBlocks(/prefers-reduced-motion:\s*reduce/).join("\n");
    expect(reduce).toMatch(/animation-duration:\s*0\.01ms\s*!important/);
    expect(reduce).toMatch(/transition-duration:\s*0\.01ms\s*!important/);
    expect(reduce).toMatch(/scroll-behavior:\s*auto\s*!important/);
  });

  it("defines the duration and easing tokens", () => {
    expect(css).toMatch(/--duration-fast:\s*150ms/);
    expect(css).toMatch(/--duration-base:\s*220ms/);
    expect(css).toMatch(/--ease-out-soft:\s*cubic-bezier/);
  });

  it("moves things only inside prefers-reduced-motion: no-preference", () => {
    // Static transforms that are not motion (the hidden scroll bar etc.)
    // never use translate/scale, so any translate or scale outside the
    // guard would be movement that reduced-motion users still get.
    const moving = outsideNoPreference
      .split("\n")
      .filter((line) => /transform:\s*(translate|scale)/.test(line))
      // keyframe bodies run only when an animation is applied, and every
      // animation is applied inside the guard
      .filter(
        (line) =>
          !/^\s*transform:\s*(translateX\(100%\)|scaleX\(0\))/.test(line),
      )
      .filter((line) => !/translateX\(-100%\)/.test(line));
    expect(moving).toEqual([]);

    const guarded = mediaBlocks(/prefers-reduced-motion:\s*no-preference/).join(
      "\n",
    );
    expect(guarded).toMatch(
      /\.hover-lift:hover\s*\{\s*transform:\s*translateY\(-2px\)/,
    );
    expect(guarded).toMatch(/animation:\s*shimmer/);
    expect(guarded).toMatch(/animation:\s*slide-in-right/);
    expect(guarded).toMatch(/\[data-reveal="pending"\]/);
  });

  it("applies card hover only on pointers that can hover", () => {
    const hover = mediaBlocks(/\(hover:\s*hover\)/).join("\n");
    expect(hover).toMatch(/\.hover-lift:hover::after/);
    expect(hover).toMatch(/\.btn-primary:hover/);
    expect(hover).toMatch(/\.chip:hover/);
    // …and nowhere else.
    const outsideHover = mediaBlocks(/\(hover:\s*hover\)/).reduce(
      (rest, block) => rest.replace(block, ""),
      css,
    );
    expect(outsideHover).not.toMatch(/\.(hover-lift|btn-\w+|chip):hover/);
  });

  it("only hides a reveal section once the observer has armed the page", () => {
    // Every rule that hides a section is scoped to html.js-reveal, which only
    // the client adds: the server HTML (and a crawler) always sees content.
    const hiding = [...css.matchAll(/([^{}]*)\{[^}]*opacity:\s*0;[^}]*\}/g)]
      .map(([, selector]) => selector.trim())
      .filter((selector) => selector.includes("data-reveal"));
    expect(hiding.length).toBeGreaterThan(0);
    for (const selector of hiding) {
      expect(selector.startsWith(".js-reveal ")).toBe(true);
    }
  });
});
