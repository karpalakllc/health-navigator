import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";
import { doctor } from "./support/fixtures";

/*
 * axe on the main public pages. Fails on any serious or critical violation.
 *
 * Violations that exist today are listed in KNOWN per page and per rule: each
 * gets its own test marked test.fail(), so the rest of the page is still held
 * to the bar, and the known test starts failing (as "expected to fail but
 * passed") the moment the issue is fixed — at which point delete its entry.
 */
const PAGES: Record<string, string> = {
  home: "/",
  doctors: "/doctors",
  "doctor profile": `/doctors/${doctor.slug}`,
  login: "/login",
  register: "/register",
  forum: "/forum",
};

const BLOCKING = new Set(["serious", "critical"]);

/** page name → rule id → why it is known. */
const CONTRAST =
  "Primary buttons and links are white on #ff5757 / #ff5757 on near-white " +
  "(≈3.1:1), and muted text #66758a sits at 4.2–4.3:1 on the warm page " +
  "backgrounds; WCAG AA needs 4.5:1 for body-size text.";

const KNOWN: Record<string, Record<string, string>> = {
  doctors: {
    "color-contrast": CONTRAST,
  },
  "doctor profile": { "color-contrast": CONTRAST },
  login: { "color-contrast": CONTRAST },
  register: { "color-contrast": CONTRAST },
  forum: { "color-contrast": CONTRAST },
};

async function blockingViolations(page: Page, path: string) {
  await page.goto(path);
  await page.waitForLoadState("networkidle");

  const results = await new AxeBuilder({ page })
    .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"])
    .analyze();

  return results.violations.filter((violation) =>
    BLOCKING.has(violation.impact ?? ""),
  );
}

function summary(
  violations: Awaited<ReturnType<typeof blockingViolations>>,
): string[] {
  return violations.map((v) => {
    const nodes = v.nodes.slice(0, 3).map((node) => {
      // Contrast failures carry the measured colours and ratio.
      const data = node.any[0]?.data as
        | { fgColor?: string; bgColor?: string; contrastRatio?: number }
        | undefined;
      const contrast = data?.contrastRatio
        ? ` [${data.fgColor} on ${data.bgColor} = ${data.contrastRatio}]`
        : "";
      return `${node.target.join(" ")}${contrast}`;
    });

    return `${v.id} (${v.impact}, ${v.nodes.length} nodes): ${v.help} — ${nodes.join(" | ")}`;
  });
}

test.describe("accessibility (axe, serious + critical)", () => {
  for (const [name, path] of Object.entries(PAGES)) {
    const known = KNOWN[name] ?? {};

    test(`${name} (${path}) has no new serious or critical violations`, async ({
      page,
    }) => {
      const violations = await blockingViolations(page, path);
      const unexpected = violations.filter((v) => !(v.id in known));
      expect(summary(unexpected)).toEqual([]);
    });

    for (const [rule, reason] of Object.entries(known)) {
      test(`${name} (${path}) known violation: ${rule}`, async ({ page }) => {
        test
          .info()
          .annotations.push({ type: "known-a11y", description: reason });
        test.fail();
        const violations = await blockingViolations(page, path);
        expect(violations.map((v) => v.id)).not.toContain(rule);
      });
    }
  }
});
