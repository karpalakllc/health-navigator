import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";

/*
 * next/font options must be literals in the module (they are read at build
 * time), so the layout cannot import them from somewhere testable. Read the
 * source instead: every next/font/google call has to load Cyrillic, or the
 * Macedonian text renders in a fallback face.
 */
const source = readFileSync(
  fileURLToPath(new URL("./layout.tsx", import.meta.url)),
  "utf8",
);

const fontCalls = [...source.matchAll(/=\s*(\w+)\(\{([^}]*)\}\)/g)].filter(
  ([, name]) => /^(Geologica|Source_Sans_3|Geist|Geist_Mono|Inter)$/.test(name),
);

describe("root layout fonts", () => {
  it("loads exactly the D2a faces: Geologica (UI) and Source Sans 3 (reading)", () => {
    expect(fontCalls.map(([, name]) => name).sort()).toEqual([
      "Geologica",
      "Source_Sans_3",
    ]);
  });

  it("no longer ships Geist, Geist Mono or Inter", () => {
    expect(source).not.toMatch(/\b(Geist|Geist_Mono|Inter)\b/);
  });

  it.each(fontCalls.map(([, name, options]) => [name, options]))(
    "%s loads the cyrillic subset",
    (_name, options) => {
      expect(options).toMatch(/subsets:\s*\[[^\]]*"cyrillic"/);
    },
  );

  it.each([
    ["Geologica", "--font-geologica"],
    ["Source_Sans_3", "--font-source-sans"],
  ])("%s is exposed as the %s CSS variable", (name, variable) => {
    const options = fontCalls.find(([, n]) => n === name)?.[2] ?? "";
    expect(options).toContain(`variable: "${variable}"`);
  });

  it("keeps the document in Macedonian", () => {
    expect(source).toMatch(/lang="mk"/);
  });
});
