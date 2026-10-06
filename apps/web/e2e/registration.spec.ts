import { expect, test, type Page } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import {
  accountMenuButton,
  expectLoginRejected,
  login,
  submitLogin,
} from "./support/auth";
import { freshEmail } from "./support/fixtures";
import { latestLink } from "./support/mail";

async function register(
  page: Page,
  { name, email, password }: { name: string; email: string; password: string },
): Promise<void> {
  await page.goto("/register");
  await page.locator('input[name="name"]').fill(name);
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('input[name="password_confirmation"]').fill(password);
  await page
    .getByRole("button", { name: mk.auth.register, exact: true })
    .click();

  // Same answer whether or not the address was already registered.
  await expect(
    page.getByRole("heading", { name: mk.auth.verifyCheckInbox }),
  ).toBeVisible();
}

test.describe("registration", () => {
  test("register, verify from the mailed link, sign in", async ({ page }) => {
    const email = freshEmail("signup");
    const password = "Registracija2026";

    await register(page, { name: "Нов Член", email, password });

    const link = await latestLink(email, "verify");
    await page.goto(link);

    await expect(page).toHaveURL(/\/verify-email\?status=verified$/);
    await expect(
      page.getByRole("heading", { name: mk.auth.verifiedTitle }),
    ).toBeVisible();
    await expect(page.getByText(mk.auth.verifiedBody)).toBeVisible();

    await page
      .getByRole("main")
      .getByRole("link", { name: mk.auth.signIn })
      .click();
    await expect(page).toHaveURL(/\/login$/);
    await submitLogin(page, email, password);

    await expect(accountMenuButton(page)).toBeVisible();
    await accountMenuButton(page).click();
    await expect(
      page.getByRole("menuitem", { name: mk.nav.account, exact: true }),
    ).toBeVisible();
  });

  /*
   * Account pre-hijacking. An attacker registers the victim's address first
   * with a password they know (A); the victim then registers too (B). Neither
   * password may survive: clicking the link proves control of the mailbox, not
   * authorship of the stored password, so the API voids it and mails the
   * mailbox a reset link instead. Only the password chosen through that link
   * (C) may work.
   */
  test("contested registration: only the mailbox owner chooses the password", async ({
    browser,
  }) => {
    const email = freshEmail("contested");
    const attackerPassword = "Napagjac2026A";
    const victimPassword = "Zrtva2026B";
    const chosenPassword = "Sopstvenik2026C";

    const attacker = await browser.newPage();
    await register(attacker, {
      name: "Напаѓач",
      email,
      password: attackerPassword,
    });
    await attacker.close();

    const victim = await browser.newPage();
    await register(victim, { name: "Жртва", email, password: victimPassword });

    // The victim's mailbox holds the link the first sign-up triggered (the
    // second one fell inside the per-address cooldown).
    await victim.goto(await latestLink(email, "verify"));
    await expect(victim).toHaveURL(
      /\/verify-email\?status=verified_set_password$/,
    );
    await expect(
      victim.getByText(mk.auth.verifiedSetPasswordBody),
    ).toBeVisible();

    // Settling the contest mails a reset link without anyone asking for one.
    const resetLink = await latestLink(email, "reset");
    await victim.goto(resetLink);
    await expect(victim).toHaveURL(/\/reset-password\/new$/);
    await expect(victim.locator('input[name="email"]')).toHaveValue(email);
    await victim.locator('input[name="password"]').fill(chosenPassword);
    await victim
      .locator('input[name="password_confirmation"]')
      .fill(chosenPassword);
    await victim
      .getByRole("button", { name: mk.auth.resetPasswordSubmit })
      .click();
    await expect(victim).toHaveURL(/\/login\?reset=1$/);

    // Neither registration password works.
    for (const stale of [attackerPassword, victimPassword]) {
      await victim.goto("/login");
      await submitLogin(victim, email, stale);
      await expectLoginRejected(victim);
    }

    await login(victim, email, chosenPassword);

    await victim.close();
  });
});
