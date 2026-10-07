import { expect, test, type Page } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { triage } from "./support/fixtures";

async function toScreen(page: Page) {
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
    page.getByRole("heading", { name: mk.guidance.forWhomTitle }),
  ).toBeVisible();
  await page.getByRole("textbox", { name: /Возраст/ }).fill("40");
  await page.getByText(mk.guidance.sexMale, { exact: true }).click();
  await page.getByRole("button", { name: mk.guidance.continue }).click();

  await expect(
    page.getByRole("heading", { name: mk.guidance.symptomsTitle }),
  ).toBeVisible();
  await page
    .getByRole("searchbox", { name: mk.guidance.searchLabel })
    .fill("opsto");
  await page.getByRole("checkbox", { name: triage.flowTitle }).check();
  await page.getByRole("button", { name: mk.guidance.continue }).click();

  await expect(
    page.getByRole("heading", { name: mk.guidance.safetyCheck }),
  ).toBeVisible();
}

test.describe("symptom guidance", () => {
  test("a red-flag answer goes straight to the emergency screen", async ({
    page,
  }) => {
    await toScreen(page);
    await page.getByLabel(triage.redFlagLabel).check();
    await page.getByRole("button", { name: mk.guidance.continue }).click();

    await expect(
      page.getByRole("heading", { name: triage.emergencyOutcomeTitle }),
    ).toBeVisible();
    await expect(
      page.getByRole("link", { name: mk.guidance.call194 }),
    ).toHaveAttribute("href", "tel:194");
    await expect(
      page.getByRole("link", { name: mk.guidance.call112 }),
    ).toHaveAttribute("href", "tel:112");

    // Short-circuited: none of the ordinary questions was asked, no way back.
    await expect(page.getByText(triage.firstQuestion)).toHaveCount(0);
    await expect(
      page.getByRole("button", { name: mk.common.back }),
    ).toHaveCount(0);
  });

  test("without red flags the questions follow, then a result with a summary", async ({
    page,
  }) => {
    await toScreen(page);
    await page.getByRole("button", { name: mk.guidance.continue }).click();

    await expect(
      page.getByRole("heading", { name: triage.firstQuestion }),
    ).toBeVisible();
    await page.getByText("Општи симптоми (болка, температура, замор)").click();
    await page.getByRole("button", { name: mk.guidance.continue }).click();
    await page.getByText("Благи — се забележуваат, но се поднесливи").click();
    await page.getByRole("button", { name: mk.guidance.continue }).click();
    await page.getByText("Помалку од 24 часа").click();
    await page.getByRole("button", { name: mk.guidance.continue }).click();

    await expect(
      page.getByRole("heading", { name: "Општи информации" }),
    ).toBeVisible();
    await expect(
      page.getByRole("heading", { name: mk.guidance.watchTitle }),
    ).toBeVisible();
    await expect(
      page.getByRole("region", { name: mk.guidance.summaryTitle }),
    ).toContainText("40 години, Машки");
    await expect(
      page.getByRole("heading", { name: triage.emergencyOutcomeTitle }),
    ).toHaveCount(0);
  });

  test("the urgent-help button works on every step", async ({ page }) => {
    await toScreen(page);
    await page.getByRole("button", { name: mk.guidance.emergencyNow }).click();

    await expect(
      page.getByRole("link", { name: mk.guidance.call194 }),
    ).toBeVisible();
    await expect(
      page.getByText(mk.guidance.emergencyShortcutBody),
    ).toBeVisible();
  });
});
