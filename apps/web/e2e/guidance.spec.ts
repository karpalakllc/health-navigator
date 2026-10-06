import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { triage } from "./support/fixtures";

test.describe("symptom guidance", () => {
  test("a red-flag answer goes straight to the emergency screen", async ({
    page,
  }) => {
    await page.goto("/guidance");
    await expect(
      page.getByRole("heading", { level: 1, name: mk.guidance.title }),
    ).toBeVisible();

    // The disclaimer must be accepted before anything is asked.
    await page.getByRole("button", { name: mk.guidance.continue }).click();
    await expect(page.getByText(mk.guidance.confirmRequired)).toBeVisible();

    await page.getByLabel(mk.guidance.acceptLabel).check();
    await page.getByRole("button", { name: mk.guidance.continue }).click();

    await expect(
      page.getByRole("heading", { name: mk.guidance.safetyCheck }),
    ).toBeVisible();
    await page.getByLabel(triage.redFlagLabel).check();
    await page.getByRole("button", { name: mk.guidance.continue }).click();

    await expect(
      page.getByRole("heading", { name: triage.emergencyOutcomeTitle }),
    ).toBeVisible();
    const notice = page.getByText(mk.guidance.emergencyResultNote);
    await expect(notice).toBeVisible();
    await expect(notice.locator("strong", { hasText: "194" })).toBeVisible();
    await expect(notice.locator("strong", { hasText: "112" })).toBeVisible();

    // Short-circuited: none of the ordinary questions was asked.
    await expect(page.getByText(`${mk.guidance.step} 1`)).toHaveCount(0);
    await expect(
      page.getByRole("button", { name: mk.guidance.seeGuidance }),
    ).toHaveCount(0);
  });

  test("without red flags the questionnaire continues", async ({ page }) => {
    await page.goto("/guidance");
    await page.getByLabel(mk.guidance.acceptLabel).check();
    await page.getByRole("button", { name: mk.guidance.continue }).click();
    await expect(
      page.getByRole("heading", { name: mk.guidance.safetyCheck }),
    ).toBeVisible();
    await page.getByRole("button", { name: mk.guidance.continue }).click();

    await expect(
      page.getByText(new RegExp(`${mk.guidance.step} 1 `)),
    ).toBeVisible();
    await expect(
      page.getByRole("heading", { name: triage.emergencyOutcomeTitle }),
    ).toHaveCount(0);
  });
});
