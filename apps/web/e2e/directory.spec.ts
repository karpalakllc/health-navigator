import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { doctor } from "./support/fixtures";

test.describe("directory list", () => {
  test("a doctor card with a phone offers „Јави се“ straight from the list", async ({
    page,
  }) => {
    // The list payload carries phone and office_hours (E2ESeeder sets both).
    await page.goto(`/doctors?q=${encodeURIComponent("Тестовска")}`);

    const card = page
      .getByRole("article")
      .filter({ has: page.getByRole("heading", { name: doctor.name }) });
    await expect(card).toHaveCount(1);

    const call = card.getByRole("link", {
      name: mk.directory.callName.replace("{name}", doctor.name),
    });
    await expect(call).toBeVisible();
    await expect(call).toHaveText(mk.directory.call);
    await expect(call).toHaveAttribute("href", "tel:+38970000001");
  });
});
