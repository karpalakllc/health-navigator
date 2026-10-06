import { expect, type Locator, type Page } from "@playwright/test";
import { API_URL } from "./env";
import { PASSWORD, staffTotpSecrets } from "./fixtures";
import { totp, totpSecondsRemaining } from "./totp";

/*
 * Filament sign-in with the staff 2FA step, tolerant of the panel's sign-in
 * limit (five submissions a minute per address) and of TOTP replay protection
 * (the same secret accepted once per 30-second step) — other specs sign the
 * same staff accounts in. Same behaviour as the helper inside admin.spec.ts;
 * that spec can switch to this one when it is next touched.
 */
export const ADMIN = `${API_URL}/admin`;

type StaffEmail = keyof typeof staffTotpSecrets;

export async function adminLogin(page: Page, email: StaffEmail): Promise<void> {
  await page.goto(`${ADMIN}/login`);
  await page.locator('input[type="email"]').fill(email);
  await page.locator('input[type="password"]').fill(PASSWORD);
  await submit(page, /sign in/i);

  const challenge = page
    .locator(
      'input[autocomplete="one-time-code"], input[id$=".code"], input[name="code"]',
    )
    .first();
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
    if (totpSecondsRemaining() < 3) {
      await page.waitForTimeout(totpSecondsRemaining() * 1000 + 200);
    }
    await challenge.fill(totp(secret));
  });
}

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
