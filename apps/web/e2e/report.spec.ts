import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { ADMIN, adminLogin } from "./support/admin-login";
import { login } from "./support/auth";
import { facilitySlug, users } from "./support/fixtures";

/*
 * Notice and action end to end: a member marks a published review „Корисно“
 * and reports it, a staff Moderator hides it from the admin report queue, and
 * the review's text is gone from the public profile; in its place stays a
 * placeholder with the date and the public reason (W5-I). Each attempt has
 * its own seeded review (E2ESeeder::seedReportableReviews), so a retry starts
 * clean.
 */
function reviewBody(retry: number): string {
  return `Рецензија за пријава ${retry} (E2E).`;
}

test.describe("reports", () => {
  // One staff sign-in may wait out Filament's sign-in limit or a TOTP step.
  test.describe.configure({ timeout: 150_000 });

  test("a member reports a review, a moderator hides it, visitors no longer see it", async ({
    page,
    browser,
  }, testInfo) => {
    const body = reviewBody(testInfo.retry);
    const profile = `/facilities/${facilitySlug}`;

    await login(page, users.member);
    await page.goto(`${profile}#reviews`);

    const card = page.getByRole("article").filter({ hasText: body });
    await expect(card).toBeVisible();

    // „Корисно“: a toggle that stays quiet and shows its state.
    const helpful = card.getByRole("button", {
      name: new RegExp(`^${mk.reviews.helpful}`),
    });
    await expect(helpful).toHaveAttribute("aria-pressed", "false");
    await helpful.click();
    await expect(helpful).toHaveAttribute("aria-pressed", "true");
    await expect(helpful).toHaveText(/\(1\)/);

    // „Пријави“ opens the dialog; Escape closes it and focus comes back.
    const report = card.getByRole("button", {
      name: new RegExp(`^${mk.reports.actionReview.split(" {name}")[0]}`),
    });
    await report.click();
    let dialog = page.getByRole("dialog", { name: mk.reports.dialogTitle });
    await expect(dialog).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(dialog).toHaveCount(0);
    await expect(report).toBeFocused();

    await report.click();
    dialog = page.getByRole("dialog", { name: mk.reports.dialogTitle });
    await dialog.getByRole("radio", { name: mk.reports.reasonAbuse }).check();
    await dialog
      .getByRole("textbox", { name: mk.reports.noteLabel })
      .fill("Навредлив тон кон персоналот.");
    await dialog.getByRole("button", { name: mk.reports.submit }).click();
    await expect(dialog.getByRole("status")).toContainText(
      mk.reports.successBody,
    );
    await dialog.getByRole("button", { name: mk.reports.close }).last().click();

    // The staff Moderator resolves it from the queue.
    const admin = await browser.newPage();
    await adminLogin(admin, users.staffModerator);
    await admin.goto(`${ADMIN}/content-reports`);
    const row = admin.locator("table tbody tr").filter({ hasText: body });
    await expect(row).toBeVisible();
    await row.getByRole("button", { name: /hide content/i }).click();
    // Filament renders a confirmation modal as an alertdialog.
    const modal = admin.getByRole("alertdialog", { name: /hide content/i });
    // The public reason starts from the report's reason („навреда“).
    await expect(
      modal.getByRole("combobox", { name: /public reason/i }),
    ).toContainText(/Abuse/);
    // The reason is required and starts prefilled; replace it.
    await modal
      .getByRole("textbox", { name: /reason \(shown to the author\)/i })
      .fill("Рецензијата содржи навредлив тон.");
    await modal.getByRole("button", { name: /confirm|submit/i }).click();
    await expect(
      admin.locator("table tbody tr").filter({ hasText: body }),
    ).toHaveCount(0);
    await admin.close();

    // Gone for a visitor — text, author and rating — but not silently: a
    // placeholder says when and why, and links to how moderation works.
    const visitor = await browser.newPage();
    await visitor.goto(`${profile}#reviews`);
    await expect(
      visitor.getByRole("heading", { name: mk.reviews.title }),
    ).toBeVisible();
    await expect(visitor.getByText(body)).toHaveCount(0);
    const placeholder = visitor.getByRole("article").filter({
      hasText: /Рецензијата е отстранета на .+ — причина: навреда\./,
    });
    await expect(placeholder.first()).toBeVisible();
    await expect(
      placeholder.first().getByRole("link", { name: mk.integrity.removedHow }),
    ).toHaveAttribute("href", "/transparency#moderacija");
    await visitor.close();
  });

  test("signed-out visitors are sent to sign in from „Пријави“ and back", async ({
    page,
  }) => {
    const profile = `/facilities/${facilitySlug}`;
    await page.goto(`${profile}#reviews`);

    const report = page
      .getByRole("link", {
        name: new RegExp(`^${mk.reports.actionReview.split(" {name}")[0]}`),
      })
      .first();
    await expect(report).toHaveAttribute(
      "href",
      `/login?redirect=${encodeURIComponent(`${profile}#reviews`)}`,
    );
  });
});
