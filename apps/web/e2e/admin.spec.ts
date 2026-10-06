import { expect, test, type Locator, type Page } from "@playwright/test";
import { API_URL } from "./support/env";
import { PASSWORD, staffTotpSecrets, users } from "./support/fixtures";
import { totp, totpSecondsRemaining } from "./support/totp";

/*
 * The Filament panel lives on the API host, not the web app. Its UI is in
 * English (APP_LOCALE=en), so these selectors use Filament's own copy and
 * structure rather than the mk dictionary.
 *
 * Sign-ins are budgeted: Filament allows five sign-in submissions a minute per
 * address — the password step and the 2FA code step each count — and every E2E
 * request comes from 127.0.0.1. One run signs in twice (the Administrator once
 * for the whole file, the staff Moderator once), four submissions.
 */
const ADMIN = `${API_URL}/admin`;

type StaffEmail = keyof typeof staffTotpSecrets;

async function adminLogin(page: Page, email: StaffEmail): Promise<void> {
  await page.goto(`${ADMIN}/login`);
  await page.locator('input[type="email"]').fill(email);
  await page.locator('input[type="password"]').fill(PASSWORD);
  await submit(page, /sign in/i);

  // Staff 2FA: once the panel requires app authentication it asks for a code
  // after the password. Tolerate both, so the spec holds on either side of
  // that change.
  const challenge = page
    .locator(
      'input[autocomplete="one-time-code"], input[id$=".code"], input[name="code"]',
    )
    .first();
  // The losing wait is left pending; swallow its eventual rejection.
  const outcome = await Promise.race([
    challenge
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
    await submitCode(page, challenge, staffTotpSecrets[email]);

    // Filament refuses a code from a step it already accepted for this secret
    // (replay protection), so the same account signing in twice inside one
    // 30-second step — a retry in a fresh worker — is rightly rejected. Wait
    // for the next step and try once more.
    const rejected = await page
      .getByText(/code you entered is invalid/i)
      .waitFor({ state: "visible", timeout: 5_000 })
      .then(() => true)
      .catch(() => false);

    if (rejected) {
      await page.waitForTimeout(totpSecondsRemaining() * 1000 + 500);
      await submitCode(page, challenge, staffTotpSecrets[email]);
    }
  }

  await expect(page).not.toHaveURL(/\/admin\/login/);
}

async function submitCode(
  page: Page,
  challenge: Locator,
  secret: string,
): Promise<void> {
  await submit(page, /sign in|verify|confirm/i, async () => {
    // Do not submit a code in its last seconds; it could expire in flight.
    if (totpSecondsRemaining() < 3) {
      await page.waitForTimeout(totpSecondsRemaining() * 1000 + 200);
    }
    await challenge.fill(totp(secret));
  });
}

/**
 * Fills (via `prepare`) and submits the current step. If Filament's sign-in
 * limit has been hit (a retry, or a rerun inside the same minute), waits the
 * window out and submits once more instead of failing — the limit itself is
 * behaviour we keep. `prepare` runs again before the second click, so a code
 * that aged out of its step during the wait is replaced.
 */
async function submit(
  page: Page,
  name: RegExp,
  prepare: () => Promise<void> = async () => {},
): Promise<void> {
  const button = page.getByRole("button", { name });
  await prepare();
  await button.click();

  const notice = page.getByText(/too many login attempts/i).first();
  const throttled = await notice
    .waitFor({ state: "visible", timeout: 2_000 })
    .then(() => true)
    .catch(() => false);
  if (!throttled) return;

  const text = (await notice.textContent()) ?? "";
  const seconds = Number(/(\d+)\s*second/i.exec(text)?.[1] ?? 60);
  await page.waitForTimeout((seconds + 1) * 1000);
  await prepare();
  await button.click();
}

test.describe("admin panel", () => {
  // One Administrator session for the whole file: each sign-in costs two of the
  // five submissions a minute Filament allows per address. The timeout leaves
  // room for one throttle wait (a minute) or a replay wait (up to 30 seconds),
  // which beforeAll is subject to as well.
  test.describe.configure({ mode: "serial", timeout: 150_000 });

  let adminPage: Page;

  test.beforeAll(async ({ browser }) => {
    adminPage = await browser.newPage();
    await adminLogin(adminPage, users.admin);
  });

  test.afterAll(async () => {
    await adminPage?.close();
  });

  test("an Administrator signs in and sees the dashboard", async () => {
    await adminPage.goto(`${ADMIN}`);
    await expect(adminPage).toHaveURL(new RegExp(`${ADMIN}/?$`));
    await expect(
      adminPage.getByRole("heading", { level: 1, name: /dashboard/i }),
    ).toBeVisible();
  });

  test("delete bulk actions on doctors: Administrator yes, staff Moderator no", async ({
    browser,
  }) => {
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
