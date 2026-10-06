import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { login } from "./support/auth";
import { attemptUser, facilitySlug } from "./support/fixtures";

/*
 * Review integrity (W5-I): the public „Транспарентност“ page is reachable from
 * the footer and explains moderation, payment and ordering, with the
 * featuring criteria marked as a draft; the optional aspect ratings are
 * folded away in the review form and go out with the review. Removal
 * placeholders are covered end to end in report.spec.ts.
 */
test.describe("review integrity", () => {
  test("the footer leads to the transparency page", async ({ page }) => {
    await page.goto("/");
    await page
      .getByRole("contentinfo")
      .getByRole("link", { name: mk.integrity.footerLink })
      .click();

    await expect(page).toHaveURL(/\/transparency$/);
    await expect(
      page.getByRole("heading", { level: 1, name: mk.integrity.heroTitle }),
    ).toBeVisible();
    for (const title of [
      mk.integrity.statsTitle,
      mk.integrity.moderationTitle,
      mk.integrity.paymentTitle,
      mk.integrity.orderTitle,
      mk.integrity.criteriaTitle,
    ]) {
      await expect(page.getByRole("heading", { name: title })).toBeVisible();
    }
    const criteria = page.getByRole("region", {
      name: mk.integrity.criteriaTitle,
    });
    await expect(
      criteria.getByText(mk.integrity.criteriaDraft, { exact: true }),
    ).toBeVisible();

    // The monthly table opens and scrolls inside its own box.
    await page
      .getByText(mk.integrity.reviewsTable, { exact: true })
      .first()
      .click();
    await expect(
      page.getByRole("table", { name: mk.integrity.reviewsTable }),
    ).toBeVisible();

    const results = await new AxeBuilder({ page })
      .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"])
      .analyze();
    expect(
      results.violations.filter((violation) =>
        ["serious", "critical"].includes(violation.impact ?? ""),
      ),
    ).toEqual([]);
  });

  test("the transparency page has no horizontal overflow on a phone", async ({
    page,
  }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/transparency");
    await page
      .getByText(mk.integrity.reviewsTable, { exact: true })
      .first()
      .click();

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - window.innerWidth,
    );
    expect(overflow).toBeLessThanOrEqual(0);
  });

  test("a member adds optional aspect ratings to a facility review", async ({
    page,
  }, testInfo) => {
    // Its own attempt account: the doctor review in review.spec.ts uses the
    // same accounts on another profile.
    await login(page, attemptUser("reviewer", testInfo.retry));
    await page.goto(`/facilities/${facilitySlug}#reviews`);

    const overall = page.getByRole("radiogroup", { name: mk.reviews.rating });
    const form = page.locator("form").filter({ has: overall });
    await overall.getByRole("radio").nth(3).click();

    // Folded away until asked for.
    await expect(form.getByRole("radiogroup")).toHaveCount(1);
    await form.getByText(mk.integrity.aspectsToggle).click();
    const cleanliness = form.getByRole("radiogroup", {
      name: mk.integrity.aspectCleanliness,
    });
    await expect(form.getByRole("radiogroup")).toHaveCount(5);
    await cleanliness.getByRole("radio").nth(4).click();
    await expect(cleanliness.getByRole("radio").nth(4)).toHaveAttribute(
      "aria-checked",
      "true",
    );

    const request = page.waitForRequest(
      (candidate) =>
        candidate.url().endsWith("/api/reviews") &&
        candidate.method() === "POST",
    );
    await form.getByRole("button", { name: mk.reviews.submit }).click();
    expect((await request).postDataJSON()).toMatchObject({
      kind: "facility",
      rating: 4,
      aspects: { cleanliness: 5 },
    });
    await expect(page.getByText(mk.reviews.pendingTitle)).toBeVisible();
  });
});
