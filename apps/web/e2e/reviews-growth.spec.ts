import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { submitLogin } from "./support/auth";
import { consentState } from "./support/consent";
import { attemptUser, firstReviewDoctor } from "./support/fixtures";

/*
 * „First reviews“ (docs/reviews-growth.md) on a 390 px phone: a patient
 * opens the shared link to a doctor nobody reviewed yet, starts the review
 * signed out, signs in, finds the draft restored and sends it to moderation.
 */

const PHONE = { width: 390, height: 844 };

test.describe("first reviews on a phone", () => {
  test.use({
    viewport: PHONE,
    hasTouch: true,
    isMobile: true,
    // Statistics on: the „started from the shared link“ mark may be sent.
    storageState: consentState(true),
  });

  test("shared link → sticky CTA → stars → sign-in → draft restored → moderation", async ({
    page,
  }, testInfo) => {
    const doctor = firstReviewDoctor(testInfo.retry);
    const shareUrl = `/doctors/${doctor.slug}?utm_source=share&utm_medium=referral&utm_campaign=napishi-recenzija#napishi-recenzija`;

    await page.goto(shareUrl);
    await page.waitForLoadState("networkidle");

    // Zero reviews: the bottom bar offers the review beside the call.
    const bar = page.getByRole("region", {
      name: mk.reviewGrowth.stickyLabel,
    });
    await expect(bar).toBeVisible();
    const sticky = bar.getByRole("link", { name: mk.reviews.write });
    await expect(sticky).toBeVisible();
    const box = await sticky.boundingBox();
    expect(box?.height ?? 0).toBeGreaterThanOrEqual(48);
    await page.screenshot({ path: testInfo.outputPath("01-profile-390.png") });

    await sticky.tap();
    const form = page.locator("form#review-form");
    await expect(form).toBeInViewport();
    // Signed out, the form is there; sign-in is the last step.
    await expect(
      form.getByRole("list", { name: mk.reviewFlow.stepsLabel }),
    ).toContainText(mk.reviewGrowth.stepLogin);

    await form
      .getByRole("radio", {
        name: mk.reviews.ratingStarLabelOther.replace("{stars}", "5"),
      })
      .tap();
    const body = form.getByLabel(mk.reviews.body);
    await body.fill("Кратко");
    await body.blur();
    // Inline validation in Macedonian before anything is sent.
    await expect(form.getByText(mk.reviewGrowth.bodyTooShort)).toBeVisible();
    await body.fill("Внимателна, објаснува сè и не брза.");
    await expect(form.getByText(mk.reviewGrowth.bodyTooShort)).toHaveCount(0);
    await page.screenshot({ path: testInfo.outputPath("02-form-390.png") });

    await form.getByRole("button", { name: mk.reviewGrowth.guestSubmit }).tap();
    await expect(page).toHaveURL(/\/login\?redirect=/);
    await submitLogin(page, attemptUser("first", testInfo.retry));

    // Back on the profile at the form, with the draft put back.
    await expect(page).toHaveURL(
      new RegExp(`/doctors/${doctor.slug}#napishi-recenzija$`),
    );
    await expect(page.getByText(mk.reviewGrowth.draftRestored)).toBeVisible();
    const restored = page.locator("form#review-form");
    await expect(restored.getByLabel(mk.reviews.body)).toHaveValue(
      "Внимателна, објаснува сè и не брза.",
    );

    const sent = page.waitForRequest(
      (request) =>
        request.method() === "POST" &&
        new URL(request.url()).pathname === "/api/reviews",
    );
    await restored.getByRole("button", { name: mk.reviews.submit }).tap();
    const payload = (await sent).postDataJSON() as Record<string, unknown>;
    expect(payload).toMatchObject({
      kind: "doctor",
      slug: doctor.slug,
      rating: 5,
      via_shared_link: true,
    });

    // Moderation explained; nothing published yet.
    const pending = page.getByRole("article", {
      name: mk.reviews.pendingTitle,
    });
    await expect(pending).toBeVisible();
    await expect(pending).toContainText(mk.reviewGrowth.pendingImpact);
    await page.screenshot({ path: testInfo.outputPath("03-pending-390.png") });

    // The sticky review action is gone once the member has written one.
    await page.reload();
    await expect(
      page
        .getByRole("region", { name: mk.reviewGrowth.stickyLabel })
        .getByRole("link", { name: mk.reviews.write }),
    ).toHaveCount(0);

    // No horizontal scroll, and axe-clean at phone width.
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true);
    const results = await new AxeBuilder({ page })
      .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"])
      .analyze();
    expect(
      results.violations
        .filter((v) => v.impact === "serious" || v.impact === "critical")
        .map((v) => v.id),
    ).toEqual([]);
  });

  test("back on the results after a profile, one calm prompt asks about it", async ({
    page,
  }) => {
    await page.goto("/doctors");
    await page.waitForLoadState("networkidle");
    const firstCard = page.locator('main a[href^="/doctors/"]').first();
    const href = await firstCard.getAttribute("href");
    await firstCard.tap();
    await expect(page).toHaveURL(/\/doctors\/[^/?#]+/);
    // A soft navigation: wait until the profile has rendered and noted the
    // doctor (a reader spends longer than that on it anyway).
    await expect
      .poll(() =>
        page.evaluate(() => sessionStorage.getItem("z360:search-return:v1")),
      )
      .not.toBeNull();

    await page.goBack();
    await expect(page).toHaveURL(/\/doctors$/);

    const prompt = page.locator("[data-track='search-review-prompt']");
    await expect(prompt).toBeAttached();
    await expect(page.getByRole("dialog")).toHaveCount(0);
    await expect(
      prompt.getByRole("link", { name: mk.reviewGrowth.searchPromptWrite }),
    ).toHaveAttribute("href", `${href?.split(/[?#]/)[0]}#napishi-recenzija`);

    await prompt.scrollIntoViewIfNeeded();
    await prompt
      .getByRole("button", {
        name: new RegExp(`^${mk.reviewGrowth.searchPromptLater}`),
      })
      .tap();
    await expect(prompt).toHaveCount(0);

    // Once per session: the same round trip does not ask again.
    await firstCard.tap();
    await expect
      .poll(() =>
        page.evaluate(() => sessionStorage.getItem("z360:search-return:v1")),
      )
      .not.toBeNull();
    await page.goBack();
    await page.waitForLoadState("networkidle");
    await expect(
      page.locator("[data-track='search-review-prompt']"),
    ).toHaveCount(0);
  });
});
