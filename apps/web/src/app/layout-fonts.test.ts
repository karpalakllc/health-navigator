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
  ([, name]) => /^(Geist|Geist_Mono|Inter)$/.test(name),
);

describe("root layout fonts", () => {
  it("configures every Google font it imports", () => {
    expect(fontCalls.map(([, name]) => name).sort()).toEqual([
      "Geist",
      "Geist_Mono",
      "Inter",
    ]);
  });

  it.each(fontCalls.map(([, name, options]) => [name, options]))(
    "%s loads the cyrillic subset",
    (_name, options) => {
      expect(options).toMatch(/subsets:\s*\[[^\]]*"cyrillic"/);
    },
  );
});
