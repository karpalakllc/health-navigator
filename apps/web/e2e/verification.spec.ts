import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { doctor, facilitySlug } from "./support/fixtures";

/*
 * Verified badges (W7-B): E2ESeeder::seedVerification() marks the E2E doctor
 * „Верификуван“ (official registers) and leaves the clinic
 * „Неверификувана“. The badge explains itself on tap/keyboard and
 * links to /transparency#verifikacija; „Само верификувани“ narrows the list.
 */
test.describe("verification badges", () => {
  test("the doctor profile shows „Верификуван“ next to the name and explains it", async ({
    page,
  }) => {
    await page.goto(`/doctors/${doctor.slug}`);

    const why = page.getByRole("button", {
      name: mk.verification.whyVerified,
    });
    await expect(
      page.getByText(mk.verification.verified).first(),
    ).toBeVisible();
    await why.focus();
    await page.keyboard.press("Enter");
    await expect(why).toHaveAttribute("aria-expanded", "true");
    await expect(
      page.getByRole("status").filter({
        hasText: mk.verification.verifiedInfoBasis.split("{basis}")[0],
      }),
    ).toBeVisible();

    await page.getByRole("link", { name: mk.integrity.disclosureMore }).click();
    await expect(page).toHaveURL(/\/transparency#verifikacija$/);
    await expect(
      page.getByRole("heading", {
        level: 2,
        name: mk.verification.sectionTitle,
      }),
    ).toBeVisible();
  });

  test("an unverified clinic is labelled calmly", async ({ page }) => {
    await page.goto(`/facilities/${facilitySlug}`);

    await expect(
      page.getByText(mk.verification.unverifiedFeminine).first(),
    ).toBeVisible();
    const results = await new AxeBuilder({ page }).analyze();
    expect(
      results.violations.filter(
        (v) => v.impact === "serious" || v.impact === "critical",
      ),
    ).toEqual([]);
  });

  test("„Само верификувани“ keeps the verified doctor in the list", async ({
    page,
  }) => {
    await page.goto("/doctors?verified=1");

    await expect(
      page.getByRole("heading", { level: 2, name: doctor.name }),
    ).toBeVisible();
    await expect(
      page.getByRole("switch", { name: mk.verification.filterLabel }).first(),
    ).toBeChecked();
  });
});
