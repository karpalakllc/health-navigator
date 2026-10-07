import { readdirSync, readFileSync } from "node:fs";
import { join, relative } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { isUxTrackName } from "@/lib/ux/schema";

/*
 * A `data-track` name outside UX_TARGET_CONTEXTS is silently ignored by the
 * tracker (the click falls back to the landmark), so a component that adds
 * one without extending the closed list on both sides (schema.ts and
 * UxSchema.php, UxRoutesParityTest) would never show up in „UX анализа“.
 */
const SRC = fileURLToPath(new URL("../../", import.meta.url));

function sourceFiles(dir: string): string[] {
  return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) return sourceFiles(path);
    return /\.tsx?$/.test(entry.name) && !/\.test\.tsx?$/.test(entry.name)
      ? [path]
      : [];
  });
}

describe("data-track names in components", () => {
  const used = sourceFiles(SRC).flatMap((file) =>
    [...readFileSync(file, "utf8").matchAll(/data-track="([^"]*)"/g)].map(
      (match) => ({ file: relative(SRC, file), name: match[1] }),
    ),
  );

  it("are found at all", () => {
    expect(used.length).toBeGreaterThan(5);
  });

  it("all come from the closed context list", () => {
    expect(used.filter(({ name }) => !isUxTrackName(name))).toEqual([]);
  });
});
