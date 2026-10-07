import { expect, test, type Request } from "@playwright/test";
import { mk } from "../src/i18n/mk";

/**
 * Anonymous UX statistics (docs/ux-heatmaps.md): the tracker sends only
 * bounded counters, never on excluded pages or with a privacy signal, and the
 * staff overlay stays shut without a valid token.
 */

function uxBatches(requests: Request[]) {
  return requests
    .filter((request) => new URL(request.url()).pathname === "/api/ux/events")
    .map((request) => request.postDataJSON() as Record<string, unknown>);
}

test.describe("UX tracker", () => {
  test("a dead click on a doctor card is reported as a template, without text", async ({
    page,
  }) => {
    const requests: Request[] = [];
    page.on("request", (request) => requests.push(request));

    await page.goto("/doctors");
    const heading = page.getByRole("article").first().getByRole("heading");
    const name = (await heading.first().textContent()) ?? "";
    // The tracker loads when the browser is idle.
    await page.waitForTimeout(2_500);
    await heading.first().click();

    // Leaving the page sends the batch.
    await page.goto("/about");
    await expect
      .poll(() => uxBatches(requests).length, { timeout: 10_000 })
      .toBeGreaterThan(0);

    const payload = JSON.stringify(uxBatches(requests));
    expect(payload).toContain('"k":"doctor-card/heading"');
    expect(payload).toContain('"d":true');
    expect(payload).toContain('"r":"/doctors"');
    if (name.trim()) expect(payload).not.toContain(name.trim());
  });

  test("sends nothing on account pages or with Global Privacy Control", async ({
    browser,
  }) => {
    const context = await browser.newContext({
      extraHTTPHeaders: { "Sec-GPC": "1" },
    });
    await context.addInitScript(() => {
      Object.defineProperty(navigator, "globalPrivacyControl", { value: true });
    });
    const page = await context.newPage();
    const requests: Request[] = [];
    page.on("request", (request) => requests.push(request));

    await page.goto("/doctors");
    await page.waitForTimeout(2_500);
    await page.mouse.click(10, 300);
    await page.goto("/login");
    await page.waitForTimeout(1_000);

    expect(uxBatches(requests)).toEqual([]);
    await context.close();
  });

  test("the heatmap overlay does not open with a forged token", async ({
    page,
  }) => {
    await page.goto(`/doctors#ux-heatmap=${"a".repeat(60)}.${"b".repeat(43)}`);

    await expect(page.getByText(mk.uxOverlay.expired)).toBeVisible({
      timeout: 10_000,
    });
    expect(page.url()).not.toContain("ux-heatmap");
  });
});
