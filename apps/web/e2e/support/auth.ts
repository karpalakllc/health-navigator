import { expect, type Page } from "@playwright/test";
import { mk } from "../../src/i18n/mk";
import { PASSWORD } from "./fixtures";

/** The signed-in header: the account menu button replaces the "Најава" link. */
export function accountMenuButton(page: Page) {
  return page
    .getByRole("banner")
    .getByRole("button", { name: mk.nav.account, exact: true });
}

/** Fills and submits /login (the page must already be open). */
export async function submitLogin(
  page: Page,
  email: string,
  password: string = PASSWORD,
): Promise<void> {
  const form = page.locator("form").filter({
    has: page.locator('input[name="password"]'),
  });

  await form.locator('input[name="email"]').fill(email);
  await form.locator('input[name="password"]').fill(password);
  await form.getByRole("button", { name: mk.auth.signIn, exact: true }).click();
}

/** Signs in through the UI and waits until the header shows the account. */
export async function login(
  page: Page,
  email: string,
  password: string = PASSWORD,
  path = "/login",
): Promise<void> {
  await page.goto(path);
  await submitLogin(page, email, password);
  await expect(page).not.toHaveURL(/\/login/);
  await expect(accountMenuButton(page)).toBeVisible();
}

/** The form-level error a failed sign-in shows. */
export async function expectLoginRejected(page: Page): Promise<void> {
  // Scoped to the form: Next's route announcer is also role="alert".
  await expect(page.locator("form").getByRole("alert")).toBeVisible();
  await expect(page).toHaveURL(/\/login/);
  await expect(accountMenuButton(page)).toHaveCount(0);
}
