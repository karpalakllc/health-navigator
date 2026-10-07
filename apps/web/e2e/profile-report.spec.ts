import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { pharmacySlug } from "./support/fixtures";

/*
 * „Пријави профил“ (W7-C) end to end, as a guest: the flag opens the sheet,
 * the invisible ALTCHA widget solves its proof of work in a same-origin
 * worker under the real CSP (no blob: workers, no inline scripts), and the
 * API accepts the report. A retry from the same address on the same day is
 * absorbed by the API with the same answer, so the test is safe to repeat.
 */
test.describe("profile reports", () => {
  test("a guest reports a pharmacy profile; ALTCHA runs under the CSP", async ({
    page,
  }) => {
    const violations: string[] = [];
    page.on("console", (message) => {
      if (/Content Security Policy/i.test(message.text())) {
        violations.push(message.text());
      }
    });
    const challenge = page.waitForResponse(
      (response) =>
        response.url().endsWith("/api/altcha/challenge") &&
        response.status() === 200,
    );

    await page.goto(`/pharmacies/${pharmacySlug}`);

    const flag = page.getByRole("button", { name: mk.profileReports.action });
    await flag.click();
    const dialog = page.getByRole("dialog", {
      name: mk.profileReports.dialogTitle,
    });
    await expect(dialog).toBeVisible();
    await challenge;

    // Escape closes the sheet and focus returns to the flag.
    await page.keyboard.press("Escape");
    await expect(dialog).toHaveCount(0);
    await expect(flag).toBeFocused();

    await flag.click();
    await dialog
      .getByRole("radio", {
        name: mk.profileReports.reasons.no_longer_here_place,
      })
      .check();
    await dialog
      .getByRole("textbox", { name: mk.profileReports.noteLabel })
      .fill("Аптеката е затворена од минатиот месец (E2E).");
    await dialog
      .getByRole("button", { name: mk.profileReports.submit })
      .click();

    await expect(dialog.getByRole("status")).toContainText(
      mk.profileReports.successTitle,
      { timeout: 30_000 },
    );
    expect(violations).toEqual([]);
  });
});
