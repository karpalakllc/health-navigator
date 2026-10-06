import axe from "axe-core";

/**
 * Runs axe-core (a direct devDependency, pinned to the version that
 * @axe-core/playwright uses) over a rendered component and returns only
 * serious and critical violations, formatted for a readable assertion failure. jsdom has no layout or computed colours, so rules that
 * need them (contrast) are left to the Playwright suite.
 */
export async function seriousA11yViolations(
  container: Element,
): Promise<string[]> {
  const results = await axe.run(container, {
    rules: {
      "color-contrast": { enabled: false },
      // A component rendered in isolation is not a whole page.
      region: { enabled: false },
    },
  });

  return results.violations
    .filter((v) => v.impact === "serious" || v.impact === "critical")
    .map(
      (v) =>
        `${v.id} (${v.impact}): ${v.help} — ${v.nodes
          .map((n) => n.target.join(" "))
          .join(", ")}`,
    );
}
