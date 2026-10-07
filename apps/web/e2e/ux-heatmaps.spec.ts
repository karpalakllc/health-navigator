import { expect, test, type Page, type Request } from "@playwright/test";
import { mk } from "../src/i18n/mk";

/**
 * Anonymous UX statistics (docs/ux-heatmaps.md): the tracker sends only
 * bounded counters, never on excluded pages or with a privacy signal, and the
 * staff overlay stays shut without a valid token.
 */

/**
 * A beacon sent while the page unloads is not reliably visible to Playwright,
 * so these tests stay on the page and let the tracker's batch timer send.
 */
const BATCH_SENT_MS = 11_500; // BATCH_DELAY_MS (10 s) in lib/ux/tracker.ts, plus margin

function uxRequests(requests: Request[]) {
  return requests.filter(
    (request) => new URL(request.url()).pathname === "/api/ux/events",
  );
}

/**
 * Playwright cannot read a beacon's Blob body, so the page keeps a copy of
 * what it hands to sendBeacon (the call itself goes through unchanged).
 */
async function recordBeaconBodies(page: Page) {
  await page.addInitScript(() => {
    const bodies: string[] = [];
    (window as unknown as { __uxBodies: string[] }).__uxBodies = bodies;
    const send = navigator.sendBeacon.bind(navigator);
    navigator.sendBeacon = (url, data) => {
      if (String(url).endsWith("/api/ux/events") && data instanceof Blob) {
        void data.text().then((text) => bodies.push(text));
      }
      return send(url, data);
    };
  });
}

/**
 * A point on a doctor card's name heading that is not on its link (the name is
 * a link inside the heading): whatever the name's length, try a few spots on
 * a few cards and keep the first that lands on the heading itself.
 */
async function deadSpotOnAHeading(page: Page) {
  const spot = await page.evaluate(() => {
    const headings = Array.from(
      document.querySelectorAll('[data-track="doctor-card"] h2'),
    ).slice(0, 6);
    for (const heading of headings) {
      heading.scrollIntoView({ block: "center" });
      const rect = heading.getBoundingClientRect();
      for (const [fx, fy] of [
        [0.98, 0.5],
        [0.98, 0.9],
        [0.6, 0.9],
        [0.995, 0.1],
      ]) {
        const x = rect.left + rect.width * fx;
        const y = rect.top + rect.height * fy;
        const hit = document.elementFromPoint(x, y);
        if (hit && heading.contains(hit) && !hit.closest("a, button")) {
          return { x, y };
        }
      }
    }
    return null;
  });
  expect(
    spot,
    "no doctor card heading with room beside its link",
  ).not.toBeNull();
  return spot!;
}

test.describe("UX tracker", () => {
  test("a dead click on a doctor card is reported as a template, without text", async ({
    page,
  }) => {
    const requests: Request[] = [];
    page.on("request", (request) => requests.push(request));
    await recordBeaconBodies(page);

    await page.goto("/doctors");
    const name =
      (await page
        .locator('[data-track="doctor-card"] h2')
        .first()
        .textContent()) ?? "";
    // The tracker loads when the browser is idle.
    await page.waitForTimeout(2_500);
    const spot = await deadSpotOnAHeading(page);
    await page.mouse.click(spot.x, spot.y);

    await expect
      .poll(() => uxRequests(requests).length, { timeout: BATCH_SENT_MS })
      .toBeGreaterThan(0);

    const payload = (
      await page.evaluate(
        () => (window as unknown as { __uxBodies: string[] }).__uxBodies,
      )
    ).join("\n");
    expect(payload).toContain('"k":"doctor-card/heading"');
    expect(payload).toContain('"d":true');
    expect(payload).toContain('"r":"/doctors"');
    if (name.trim()) expect(payload).not.toContain(name.trim());
  });

  test("sends nothing with Global Privacy Control", async ({ browser }) => {
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
    await page.waitForTimeout(BATCH_SENT_MS);

    expect(uxRequests(requests)).toEqual([]);
    await context.close();
  });

  test("sends nothing from sign-in or a doctor's claim page", async ({
    page,
  }) => {
    await page.goto("/doctors");
    const profile = await page
      .locator('[data-track="doctor-card"] h2 a')
      .first()
      .getAttribute("href");
    expect(profile).toMatch(/^\/doctors\/[^/]+$/);

    await recordBeaconBodies(page);

    for (const path of ["/login", `${profile}/claim`]) {
      await page.goto(path);
      await page.waitForTimeout(2_500);
      await page.mouse.click(10, 300);
      await page.mouse.click(10, 300);
      // Long enough for the batch timer, had the clicks been counted.
      await page.waitForTimeout(BATCH_SENT_MS);

      const bodies = await page.evaluate(
        () => (window as unknown as { __uxBodies: string[] }).__uxBodies,
      );
      expect(bodies, path).toEqual([]);
    }
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
