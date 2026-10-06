import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { login } from "./support/auth";
import { attemptUser, doctor } from "./support/fixtures";

function starLabel(stars: number): string {
  // Same rule as starRatingLabel() in src/lib/rating.ts.
  const template =
    stars === 1
      ? mk.reviews.ratingStarLabelOne
      : mk.reviews.ratingStarLabelOther;
  return template.replace("{stars}", String(stars));
}

test.describe("reviews", () => {
  test("a member reviews the seeded doctor with the keyboard and sees it pending", async ({
    page,
  }, testInfo) => {
    await login(page, attemptUser("reviewer", testInfo.retry));
    await page.goto(`/doctors/${doctor.slug}`);
    await expect(
      page.getByRole("heading", { level: 1, name: doctor.name }),
    ).toBeVisible();

    const group = page.getByRole("radiogroup", { name: mk.reviews.rating });
    const form = page.locator("form").filter({ has: group });
    const comment = form.locator("textarea");
    const stars = group.getByRole("radio");
    await expect(stars).toHaveCount(5);
    // Nothing is pre-selected (an untouched form must not submit five stars).
    for (const star of await stars.all()) {
      await expect(star).toHaveAttribute("aria-checked", "false");
    }

    // One tab stop for the whole group (WAI-ARIA radio pattern), then arrows.
    await comment.focus();
    await page.keyboard.press("Shift+Tab");
    await expect(stars.nth(0)).toBeFocused();
    await page.keyboard.press("ArrowRight");
    await page.keyboard.press("ArrowRight");
    await page.keyboard.press("ArrowRight");
    const four = group.getByRole("radio", { name: starLabel(4) });
    await expect(four).toBeFocused();
    await expect(four).toHaveAttribute("aria-checked", "true");
    await page.keyboard.press("End");
    await page.keyboard.press("ArrowLeft");
    await expect(four).toHaveAttribute("aria-checked", "true");

    await comment.fill("Внимателен и јасен преглед, препорачувам.");
    await page.getByRole("button", { name: mk.reviews.submit }).click();

    await expect(page.getByText(mk.reviews.pendingTitle)).toBeVisible();
    await expect(page.getByText(mk.reviews.pendingBody)).toBeVisible();
    // The form is gone: one review per profile.
    await expect(
      page.getByRole("button", { name: mk.reviews.submit }),
    ).toHaveCount(0);

    // Pending reviews are not published: a visitor does not see the text.
    const visitor = await page.context().browser()!.newPage();
    await visitor.goto(`/doctors/${doctor.slug}`);
    await expect(
      visitor.getByText("Внимателен и јасен преглед, препорачувам."),
    ).toHaveCount(0);
    await visitor.close();
  });
});
