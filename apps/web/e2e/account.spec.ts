import { readFile } from "node:fs/promises";
import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import {
  accountMenuButton,
  expectLoginRejected,
  login,
  submitLogin,
} from "./support/auth";
import { PASSWORD, attemptUser } from "./support/fixtures";

/**
 * D5/D6 end to end: a member downloads their data, signs another device out,
 * then deletes the account and can no longer sign in. One account per attempt
 * (E2ESeeder "account-N"), because the last step destroys it.
 */
test("export, sign out a device, delete the account", async ({
  browser,
  page,
}, testInfo) => {
  const email = attemptUser("account", testInfo.retry);

  // A second device, signed in first so it is not the current one below.
  const otherDevice = await browser.newContext();
  const otherPage = await otherDevice.newPage();
  await login(otherPage, email);
  await login(page, email);

  await test.step("download a copy of my data", async () => {
    await page.goto("/account/data");
    const downloading = page.waitForEvent("download");
    await page
      .getByRole("link", { name: mk.account.data.exportAction })
      .click();
    const download = await downloading;

    expect(download.suggestedFilename()).toMatch(/^zdravje360-.*\.json$/);
    const file = await download.path();
    const data = JSON.parse(await readFile(file, "utf8"));
    expect(data.format).toBe("zdravje360.account-export");
    expect(data.profile.email).toBe(email);
    expect(data.devices.length).toBeGreaterThanOrEqual(2);
    // The page did not navigate away to an error.
    await expect(page).toHaveURL(/\/account\/data$/);
  });

  await test.step("sign the other device out", async () => {
    await page.goto("/account/devices");
    const devices = page.getByRole("list", {
      name: mk.account.devices.heading,
    });
    await expect(devices.getByRole("listitem")).toHaveCount(2);
    await expect(
      devices.getByText(mk.account.devices.current, { exact: true }),
    ).toHaveCount(1);

    const revokePrefix = mk.account.devices.revokeAria.split("{name}")[0];
    await devices
      .getByRole("button", { name: new RegExp(`^${revokePrefix}`) })
      .click();
    await expect(devices.getByRole("listitem")).toHaveCount(1);
    await expect(
      page.getByText(mk.account.devices.onlyThis, { exact: true }),
    ).toBeVisible();

    // The other device's next protected page lands on sign-in.
    await otherPage.goto("/account");
    await expect(otherPage).toHaveURL(/\/login\?redirect=/);
    await expect(accountMenuButton(otherPage)).toHaveCount(0);
  });

  await test.step("delete the account with the password", async () => {
    await page.goto("/account/data");
    await page
      .getByRole("button", { name: mk.account.data.deleteStart })
      .click();
    await expect(
      page.getByRole("heading", {
        name: mk.account.data.deleteConfirmHeading,
      }),
    ).toBeFocused();

    await page
      .getByLabel(mk.account.data.deletePassword, { exact: true })
      .fill(PASSWORD);
    await page
      .getByRole("button", { name: mk.account.data.deleteConfirm })
      .click();

    await expect(page).toHaveURL(/\/account\/deleted$/);
    await expect(
      page.getByRole("heading", { name: mk.account.data.deletedTitle }),
    ).toBeVisible();
    await expect(accountMenuButton(page)).toHaveCount(0);
  });

  await test.step("the old credentials no longer sign in", async () => {
    await page.goto("/login");
    await submitLogin(page, email, PASSWORD);
    await expectLoginRejected(page);
  });

  await otherDevice.close();
});
