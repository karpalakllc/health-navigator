import { expect, test, type Page, type Request } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { CONSENT_KEY, noConsentState } from "./support/consent";

/**
 * The real cookie banner (every other spec starts with the choice stored):
 * layer 1, „Прилагоди“ into layer 2, saving, the footer link, and statistics
 * requests only after an explicit accept.
 */
test.use({ storageState: noConsentState });

const c = mk.consent;

function statsRequests(requests: Request[]) {
  return requests.filter((request) =>
    new URL(request.url()).pathname.startsWith("/api/ux/events"),
  );
}

async function stored(page: Page) {
  return page.evaluate((key) => window.localStorage.getItem(key), CONSENT_KEY);
}

test.describe("cookie consent", () => {
  test("layer 1 asks, does not block the page, and stores nothing until answered", async ({
    page,
  }) => {
    await page.goto("/doctors");

    const banner = page.getByRole("dialog", { name: c.title });
    await expect(banner).toBeVisible();
    await expect(banner).toHaveAttribute("aria-modal", "false");
    // The page behind stays usable.
    await expect(
      page.getByRole("link", { name: mk.nav.doctors }).first(),
    ).toBeVisible();
    expect(await stored(page)).toBeNull();
  });

  test("Прилагоди opens layer 2; saving with statistics off stores a decline", async ({
    page,
  }) => {
    await page.goto("/doctors");
    await page.getByRole("button", { name: c.customize }).click();

    const modal = page.getByRole("dialog", { name: c.modalTitle });
    await expect(modal).toHaveAttribute("aria-modal", "true");
    await expect(
      modal.getByRole("switch", { name: new RegExp(c.necessaryTitle) }),
    ).toBeDisabled();
    const statistics = modal.getByRole("switch", {
      name: new RegExp(c.statisticsTitle),
    });
    await expect(statistics).not.toBeChecked();

    await page.keyboard.press("Escape");
    await expect(modal).toBeHidden();
    await expect(page.getByRole("dialog", { name: c.title })).toBeVisible();

    await page.getByRole("button", { name: c.customize }).click();
    await modal.getByRole("button", { name: c.save }).click();
    await expect(page.getByRole("dialog")).toHaveCount(0);
    expect(JSON.parse((await stored(page)) ?? "{}")).toMatchObject({
      statistics: false,
      version: 1,
    });

    // Remembered: no banner on the next page.
    await page.goto("/facilities");
    await expect(page.getByRole("dialog")).toHaveCount(0);
  });

  test("the footer link reopens layer 2 with the stored choice, and withdrawing works", async ({
    page,
  }) => {
    await page.goto("/doctors");
    await page.getByRole("button", { name: c.acceptAll }).click();
    await expect(page.getByRole("dialog")).toHaveCount(0);

    await page.getByRole("button", { name: mk.footer.cookieSettings }).click();
    const modal = page.getByRole("dialog", { name: c.modalTitle });
    await expect(
      modal.getByRole("switch", { name: new RegExp(c.statisticsTitle) }),
    ).toBeChecked();

    await modal.getByRole("button", { name: c.declineAll }).click();
    expect(JSON.parse((await stored(page)) ?? "{}").statistics).toBe(false);
  });

  test("statistics requests go out only after an accept, with the consent header", async ({
    page,
  }) => {
    const requests: Request[] = [];
    page.on("request", (request) => requests.push(request));

    await page.goto("/doctors");
    // Banner showing, nothing answered: the tracker must stay silent.
    await page.waitForTimeout(2_500);
    await page.mouse.click(10, 300);
    await page.waitForTimeout(1_000);
    expect(statsRequests(requests)).toEqual([]);

    await page.getByRole("button", { name: c.acceptAll }).click();
    await page.waitForTimeout(2_500);
    await page.mouse.click(10, 300);
    // BATCH_DELAY_MS (10 s) in lib/ux/tracker.ts, plus margin.
    await expect
      .poll(() => statsRequests(requests).length, { timeout: 12_500 })
      .toBeGreaterThan(0);
    expect(statsRequests(requests)[0].headers()["x-z360-consent"]).toBe(
      "statistics",
    );
  });

  test("a decline keeps statistics off", async ({ page }) => {
    const requests: Request[] = [];
    page.on("request", (request) => requests.push(request));

    await page.goto("/doctors");
    await page.getByRole("button", { name: c.decline }).click();
    await page.waitForTimeout(2_500);
    await page.mouse.click(10, 300);
    await page.waitForTimeout(11_500);

    expect(statsRequests(requests)).toEqual([]);
  });

  test("the end of the page stays reachable above the banner", async ({
    page,
  }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/guidance");
    const banner = page.getByRole("dialog", { name: c.title });
    await expect(banner).toBeVisible();

    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    // Playwright's click fails if another element (the banner) intercepts it.
    await page
      .getByRole("button", { name: mk.footer.cookieSettings })
      .click({ timeout: 5_000 });
    await expect(
      page.getByRole("dialog", { name: c.modalTitle }),
    ).toBeVisible();
  });
});
