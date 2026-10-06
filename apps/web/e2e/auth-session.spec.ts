import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import {
  accountMenuButton,
  expectLoginRejected,
  login,
  submitLogin,
} from "./support/auth";
import { PASSWORD, attemptUser, users } from "./support/fixtures";
import { latestLink, linksTo } from "./support/mail";

test.describe("password reset", () => {
  test("forgot → mailed link → new password; other sessions are signed out", async ({
    browser,
    page,
  }, testInfo) => {
    const email = attemptUser("reset", testInfo.retry);
    const newPassword = "NovaLozinka2026";

    // A second device, signed in before the reset.
    const otherDevice = await browser.newContext();
    const otherPage = await otherDevice.newPage();
    await login(otherPage, email);

    await page.goto("/forgot-password");
    const seen = linksTo(email, "reset").length;
    await page.locator('input[name="email"]').fill(email);
    await page
      .getByRole("button", { name: mk.auth.forgotPasswordSubmit })
      .click();
    await expect(page.getByText(mk.auth.forgotPasswordSuccess)).toBeVisible();

    await page.goto(await latestLink(email, "reset", { after: seen }));
    // The token moves into a cookie; the form lives at a clean URL.
    await expect(page).toHaveURL(/\/reset-password\/new$/);
    await page.locator('input[name="password"]').fill(newPassword);
    await page.locator('input[name="password_confirmation"]').fill(newPassword);
    await page
      .getByRole("button", { name: mk.auth.resetPasswordSubmit })
      .click();
    await expect(page).toHaveURL(/\/login\?reset=1$/);

    await submitLogin(page, email, PASSWORD);
    await expectLoginRejected(page);

    await login(page, email, newPassword);

    // The reset revoked every API token, so the other device's cookie is dead:
    // its next protected navigation lands on the login page, signed out.
    await otherPage.goto("/account");
    await expect(otherPage).toHaveURL(
      /\/login\?redirect=%2Faccount|\/login\?redirect=\/account/,
    );
    await expect(accountMenuButton(otherPage)).toHaveCount(0);
    await expect(
      otherPage
        .getByRole("banner")
        .getByRole("link", { name: mk.nav.login, exact: true }),
    ).toBeVisible();

    await otherDevice.close();
  });
});

test.describe("login redirect", () => {
  /*
   * Browsers read `\` as `/`, so `/\evil.example` is protocol-relative and
   * would leave the site. After signing in the user must stay on our origin.
   */
  for (const target of [
    "/%5Cevil.example",
    "//evil.example",
    "/%09/evil.example",
  ]) {
    test(`refuses to leave the site for redirect=${target}`, async ({
      page,
      baseURL,
    }) => {
      await page.goto(`/login?redirect=${target}`);
      await submitLogin(page, users.member);

      await expect(accountMenuButton(page)).toBeVisible();
      const landed = new URL(page.url());
      expect(landed.origin).toBe(new URL(baseURL!).origin);
      expect(landed.pathname).toBe("/");
    });
  }

  test("follows a same-origin redirect", async ({ page }) => {
    await page.goto(
      `/login?redirect=${encodeURIComponent("/account/reviews")}`,
    );
    await submitLogin(page, users.member);
    await expect(page).toHaveURL(/\/account\/reviews$/);
  });
});
