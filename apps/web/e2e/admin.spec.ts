import { expect, test, type Page } from "@playwright/test";
import { API_URL } from "./support/env";
import { PASSWORD, STAFF_TOTP_SECRET, users } from "./support/fixtures";
import { totp, totpSecondsRemaining } from "./support/totp";

/*
 * The Filament panel lives on the API host, not the web app. Its UI is in
 * English (APP_LOCALE=en), so these selectors use Filament's own copy and
 * structure rather than the mk dictionary.
 */
const ADMIN = `${API_URL}/admin`;

async function adminLogin(page: Page, email: string): Promise<void> {
  await page.goto(`${ADMIN}/login`);
  await page.locator('input[type="email"]').fill(email);
  await page.locator('input[type="password"]').fill(PASSWORD);
  await page.getByRole("button", { name: /sign in/i }).click();

  // Staff 2FA: once the panel requires app authentication it asks for a code
  // after the password. Tolerate both, so the spec holds on either side of
  // that change.
  const challenge = page.locator(
    'input[autocomplete="one-time-code"], input[id$=".code"], input[name="code"]',
  );
  // The losing wait is left pending; swallow its eventual rejection.
  const outcome = await Promise.race([
    challenge
      .first()
      .waitFor({ state: "visible", timeout: 15_000 })
      .then(() => "challenge" as const)
      .catch(() => null),
    page
      .waitForURL((url) => !url.pathname.endsWith("/login"), {
        timeout: 15_000,
      })
      .then(() => "in" as const)
      .catch(() => null),
  ]);

  if (outcome === "challenge") {
    // Do not submit a code in its last seconds; it could expire in flight.
    if (totpSecondsRemaining() < 3) {
      await page.waitForTimeout(totpSecondsRemaining() * 1000 + 200);
    }
    await challenge.first().fill(totp(STAFF_TOTP_SECRET));
    await page.getByRole("button", { name: /sign in|verify|confirm/i }).click();
  }

  await expect(page).not.toHaveURL(/\/admin\/login/);
}

test.describe("admin panel", () => {
  test("an Administrator signs in and sees the dashboard", async ({ page }) => {
    await adminLogin(page, users.admin);
    await expect(page).toHaveURL(new RegExp(`${ADMIN}/?$`));
    await expect(
      page.getByRole("heading", { level: 1, name: /dashboard/i }),
    ).toBeVisible();
  });

  test("delete bulk actions on doctors: Administrator yes, staff Moderator no", async ({
    browser,
  }) => {
    const adminPage = await browser.newPage();
    await adminLogin(adminPage, users.admin);
    await adminPage.goto(`${ADMIN}/doctors`);
    await expect(adminPage.locator("table tbody tr").first()).toBeVisible();

    // Bulk actions exist for the Administrator: rows are selectable and the
    // group offers "Delete selected".
    await adminPage
      .locator("table tbody tr")
      .first()
      .locator('input[type="checkbox"]')
      .check();
    await adminPage
      .getByRole("button", { name: /bulk actions|open actions/i })
      .first()
      .click();
    await expect(
      adminPage.getByRole("button", { name: /^delete selected$/i }),
    ).toBeVisible();
    await adminPage.close();

    const moderatorPage = await browser.newPage();
    await adminLogin(moderatorPage, users.staffModerator);
    await moderatorPage.goto(`${ADMIN}/doctors`);
    await expect(moderatorPage.locator("table tbody tr").first()).toBeVisible();

    // A staff Moderator only views doctors: no selection, no bulk delete.
    await expect(
      moderatorPage.locator('table tbody input[type="checkbox"]'),
    ).toHaveCount(0);
    await expect(
      moderatorPage.getByRole("button", { name: /delete selected/i }),
    ).toHaveCount(0);
    await moderatorPage.close();
  });
});
