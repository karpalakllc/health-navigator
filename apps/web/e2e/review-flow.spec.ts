import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { login } from "./support/auth";
import { attemptUser, doctor, facilitySlug, users } from "./support/fixtures";

/*
 * W8-B: stars-first reviews, the profile prompt, „Известувања“ and the
 * signed unsubscribe page. Written for the coordinator's E2E run.
 */

test.describe("review flow and notifications", () => {
  test("a member sends a stars-only review in a few taps; it waits for moderation", async ({
    page,
  }, testInfo) => {
    await login(page, attemptUser("impact", testInfo.retry));
    await page.goto(`/facilities/${facilitySlug}#review-form`);

    const form = page.locator("form#review-form");
    await expect(form.getByText(mk.reviewFlow.ratingQuestion)).toBeVisible();
    await expect(form.locator("textarea")).toHaveCount(0);

    await form
      .getByRole("radio", {
        name: mk.reviews.ratingStarLabelOther.replace("{stars}", "5"),
      })
      .click();
    // The optional steps appear; the send button does not wait for them.
    await expect(form.locator("textarea")).toBeVisible();
    await form.getByRole("button", { name: mk.reviews.submit }).click();

    await expect(page.getByText(mk.reviews.pendingTitle)).toBeVisible();
  });

  test("a guest is invited once, below the fold, never in a dialog", async ({
    browser,
  }) => {
    const context = await browser.newContext({
      viewport: { width: 390, height: 844 },
    });
    const page = await context.newPage();
    await page.clock.install();
    await page.goto(`/doctors/${doctor.slug}`);
    // The dwell timer starts once the page has hydrated; fast-forwarding the
    // fake clock before that would skip nothing.
    await page.waitForLoadState("networkidle");

    const question = mk.reviewFlow.promptDoctor.replace("{name}", doctor.name);
    await page.clock.fastForward(16_000);
    await expect(page.getByText(question)).toBeAttached();
    await expect(page.getByRole("dialog")).toHaveCount(0);

    await page.getByText(question).scrollIntoViewIfNeeded();
    await page.getByRole("button", { name: mk.reviewFlow.promptLater }).click();
    await expect(page.getByText(question)).toHaveCount(0);

    // Once per profile per device.
    await page.reload();
    await page.waitForLoadState("networkidle");
    await page.clock.fastForward(16_000);
    await expect(page.getByText(question)).toHaveCount(0);
    await context.close();
  });

  test("the impact summary is readable on a 390 px phone", async ({
    browser,
  }, testInfo) => {
    const context = await browser.newContext({
      viewport: { width: 390, height: 844 },
    });
    const page = await context.newPage();
    await login(page, users.member);
    await page.goto("/account/reviews");

    const summary = page.locator("section[aria-labelledby='impact-title']");
    await expect(summary).toBeVisible();
    const figures = summary.getByTestId("impact-figure");
    await expect(figures).toHaveCount(3);

    for (const label of [
      mk.reviewFlow.impactViews,
      mk.reviewFlow.impactHelpful,
      mk.reviewFlow.impactReplies,
    ]) {
      const text = summary.getByText(label, { exact: true });
      await expect(text).toBeVisible();
      // Not cut: the whole label fits its box, and no ellipsis.
      expect(
        await text.evaluate(
          (element) =>
            element.scrollWidth <= element.clientWidth &&
            getComputedStyle(element).textOverflow !== "ellipsis",
        ),
      ).toBe(true);
    }

    // No horizontal page scroll at phone width.
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true);

    await summary.screenshot({ path: testInfo.outputPath("impact-390.png") });
    await context.close();
  });

  test("a member turns the monthly digest on and the setting sticks", async ({
    page,
  }, testInfo) => {
    await login(page, attemptUser("impact", testInfo.retry));
    await page.goto("/account/notifications");

    await expect(
      page.getByRole("heading", { level: 1, name: mk.notifications.title }),
    ).toBeVisible();
    const digest = page.getByLabel(mk.notifications.types.impact_digest);
    await expect(digest).not.toBeChecked();

    await digest.check();
    await expect(page.getByText(mk.notifications.saved)).toBeVisible();

    await page.reload();
    await expect(
      page.getByLabel(mk.notifications.types.impact_digest),
    ).toBeChecked();
  });

  test("a forged unsubscribe link changes nothing and says so", async ({
    page,
  }) => {
    await page.goto(`/unsubscribe?token=1.moderation.${"A".repeat(43)}`);

    await expect(
      page.getByText(mk.notifications.unsubscribe.invalid),
    ).toBeVisible();
    await expect(
      page.getByRole("button", { name: mk.notifications.unsubscribe.confirm }),
    ).toHaveCount(0);
  });
});
