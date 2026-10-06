import { expect, test, type Page } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { ADMIN, adminLogin } from "./support/admin-login";
import { login } from "./support/auth";
import { attemptUser, claimDoctor, users } from "./support/fixtures";

/*
 * „Мој профил“ end to end: an administrator assigns a member account to a
 * doctor profile; the doctor changes the phone (public at once), asks for a
 * name change (waits; the profile keeps the old name until staff approve) and
 * replies to a review (waits; public only once approved, labelled
 * „Одговор од лекарот“). Each attempt has its own seeded profile and account
 * (E2ESeeder::seedDoctorClaimProfiles), so a retry starts clean.
 *
 * The admin panel is English (Filament's own copy); the website is Macedonian
 * (the mk dictionary).
 */

async function openDoctorEdit(admin: Page, name: string): Promise<void> {
  await admin.goto(`${ADMIN}/doctors`);
  await admin.getByRole("searchbox").first().fill(name);
  const row = admin.locator("table tbody tr").filter({ hasText: name });
  await expect(row).toBeVisible();
  await row.getByRole("link", { name: /edit/i }).first().click();
  await expect(admin).toHaveURL(/\/admin\/doctors\/\d+\/edit/);
}

test.describe("doctor accounts", () => {
  // Two staff sign-ins may each wait out Filament's sign-in limit or a TOTP step.
  test.describe.configure({ mode: "serial", timeout: 240_000 });

  test("staff assign the account; the doctor edits, requests a name change and replies", async ({
    page,
    browser,
  }, testInfo) => {
    const profile = claimDoctor(testInfo.retry);
    const doctorEmail = attemptUser("doctor", testInfo.retry);
    const newPhone = `070 555 0${testInfo.retry}0`;
    const newName = `${profile.name} Нова`;
    const reply = "Ви благодарам за повратната информација.";

    // 1. An administrator links the account to the profile.
    const admin = await browser.newPage();
    await adminLogin(admin, users.admin);
    await openDoctorEdit(admin, profile.name);
    await admin.getByRole("button", { name: /assign account/i }).click();
    const assign = admin.getByRole("dialog", {
      name: /assign a member account/i,
    });
    await assign.getByRole("combobox").first().click();
    await admin.keyboard.type(doctorEmail);
    await admin.getByRole("option", { name: doctorEmail }).click();
    await assign.getByRole("button", { name: /assign account/i }).click();
    await expect(admin.getByText(/account assigned/i)).toBeVisible();

    // 2. The doctor finds „Мој профил“ in the account and changes the phone.
    await login(page, doctorEmail);
    await page.goto("/account");
    await page
      .getByRole("navigation", { name: mk.account.subNavAria })
      .getByRole("link", { name: mk.doctorDashboard.navLabel })
      .click();
    await expect(page).toHaveURL(/\/account\/doctor/);

    const phone = page.getByLabel(mk.doctorDashboard.phone, { exact: true });
    await phone.fill(newPhone);
    await page
      .getByRole("button", { name: mk.doctorDashboard.savePractice })
      .click();
    await expect(
      page.getByText(mk.doctorDashboard.practiceSaved),
    ).toBeVisible();

    const visitor = await browser.newPage();
    await visitor.goto(`/doctors/${profile.slug}`);
    await expect(visitor.getByText(newPhone).first()).toBeVisible();

    // 3. A name change waits for staff; the public profile keeps the old one.
    await page
      .getByLabel(mk.doctorDashboard.fullName, { exact: false })
      .fill(newName);
    await page
      .getByRole("button", { name: mk.doctorDashboard.submitRequest })
      .click();
    await expect(page.getByText(mk.doctorDashboard.requestSent)).toBeVisible();
    await page.reload();
    await expect(
      page.getByText(mk.doctorDashboard.pendingTitle).first(),
    ).toBeVisible();

    await visitor.reload();
    await expect(
      visitor.getByRole("heading", { level: 1, name: profile.name }),
    ).toBeVisible();

    // 4. The doctor replies to the review; it waits for staff too.
    const card = page.getByRole("article").filter({
      hasText: profile.reviewBody,
    });
    await card.getByLabel(mk.doctorDashboard.replyLabel).fill(reply);
    await card
      .getByRole("button", { name: mk.doctorDashboard.replySave })
      .click();
    await expect(card.getByText(mk.doctorDashboard.replyPending)).toBeVisible();

    await visitor.goto(`/doctors/${profile.slug}#reviews`);
    await expect(visitor.getByText(reply)).toHaveCount(0);

    // 5. Staff approve the name change and the reply.
    await admin.goto(`${ADMIN}/doctor-change-requests`);
    const requestRow = admin
      .locator("table tbody tr")
      .filter({ hasText: profile.name });
    await expect(requestRow).toBeVisible();
    await requestRow
      .getByRole("button", { name: /approve and apply/i })
      .click();
    await admin
      .getByRole("alertdialog", { name: /approve and apply/i })
      .getByRole("button", { name: /confirm|approve/i })
      .click();
    await expect(admin.getByText(/change request approved/i)).toBeVisible();

    await admin.goto(`${ADMIN}/doctor-replies`);
    const replyRow = admin.locator("table tbody tr").filter({ hasText: reply });
    await expect(replyRow).toBeVisible();
    await replyRow
      .getByRole("button", { name: /approve doctor reply/i })
      .click();
    await admin
      .getByRole("alertdialog", { name: /approve doctor reply/i })
      .getByRole("button", { name: /confirm|approve/i })
      .click();
    await expect(admin.getByText(/doctor reply published/i)).toBeVisible();

    // 6. The public profile shows the new name and the labelled reply.
    await visitor.goto(`/doctors/${profile.slug}#reviews`);
    await expect(
      visitor.getByRole("heading", { level: 1, name: newName }),
    ).toBeVisible();
    const response = visitor
      .getByRole("article")
      .filter({ hasText: profile.reviewBody });
    await expect(response.getByText(reply)).toBeVisible();
    await expect(
      response.getByText(mk.doctorDashboard.publicLabel),
    ).toBeVisible();

    await visitor.close();
    await admin.close();
  });

  test("a member who manages no profile sees no „Мој профил“", async ({
    page,
  }) => {
    await login(page, users.member);
    await page.goto("/account");

    await expect(
      page
        .getByRole("navigation", { name: mk.account.subNavAria })
        .getByRole("link", { name: mk.doctorDashboard.navLabel }),
    ).toHaveCount(0);

    await page.goto("/account/doctor");
    await expect(page).toHaveURL(/\/account$/);
  });
});
