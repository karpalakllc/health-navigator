import { expect, test, type Page } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { login } from "./support/auth";
import { API_URL } from "./support/env";
import {
  PASSWORD,
  attemptUser,
  forum,
  uniqueSuffix,
  users,
} from "./support/fixtures";

/*
 * Approve → public: a member's topic waits for moderation (seeded
 * forum_topics_require_moderation), the category's community moderator
 * approves it in the Filament panel, and only then do visitors see it.
 *
 * The community moderator (E2ESeeder: Member + Forum Moderator, scoped to the
 * published E2E category) may enter the panel without a second factor, so this
 * costs one of the five sign-in submissions a minute Filament allows per
 * address; admin.spec.ts uses four. The panel is in English (APP_LOCALE=en).
 */
const ADMIN = `${API_URL}/admin`;

async function moderatorLogin(page: Page): Promise<void> {
  await page.goto(`${ADMIN}/login`);
  await page.locator('input[type="email"]').fill(users.communityModerator);
  await page.locator('input[type="password"]').fill(PASSWORD);
  await page.getByRole("button", { name: /sign in/i }).click();

  // A retry inside the same minute can meet Filament's sign-in limit: wait it
  // out once rather than fail (the limit itself is behaviour we keep).
  const notice = page.getByText(/too many login attempts/i).first();
  const throttled = await notice
    .waitFor({ state: "visible", timeout: 2_000 })
    .then(() => true)
    .catch(() => false);

  if (throttled) {
    const text = (await notice.textContent()) ?? "";
    const seconds = Number(/(\d+)\s*second/i.exec(text)?.[1] ?? 60);
    await page.waitForTimeout((seconds + 1) * 1000);
    await page.getByRole("button", { name: /sign in/i }).click();
  }

  await expect(page).not.toHaveURL(/\/admin\/login/);
}

test.describe("forum moderation", () => {
  // Room for one sign-in throttle wait (up to a minute).
  test.describe.configure({ timeout: 150_000 });

  test("a topic approved by the category's moderator becomes public", async ({
    page,
    browser,
  }, testInfo) => {
    // Short: the panel's title column truncates at 48 characters.
    const title = `Модерација ${uniqueSuffix()}`;

    // 1. The member submits; the topic is pending.
    await login(page, attemptUser("forum", testInfo.retry));
    await page.goto(`/forum/new?category=${forum.categorySlug}`);
    await page.getByLabel(mk.common.title).fill(title);
    await page
      .getByLabel(mk.common.message)
      .fill("Дали некој има искуство со вежби за грб? (информативно)");
    await page.getByLabel(mk.forum.consent).check();
    await page
      .getByRole("button", { name: mk.forum.topicSubmitModeration })
      .click();
    await expect(page).toHaveURL(/\/account\/forum$/);

    // 2. Not public yet.
    const visitor = await browser.newPage();
    await visitor.goto(`/forum/${forum.categorySlug}`);
    await expect(
      visitor.getByRole("heading", { name: forum.topicTitle }),
    ).toBeVisible();
    await expect(visitor.getByText(title)).toHaveCount(0);

    // 3. The community moderator approves it in the panel. The topics table
    //    opens on the Pending filter.
    const moderator = await browser.newPage();
    await moderatorLogin(moderator);
    await moderator.goto(`${ADMIN}/forum-topics`);
    const row = moderator.locator("table tbody tr").filter({ hasText: title });
    await expect(row).toBeVisible();
    await row.getByRole("button", { name: /^approve$/i }).click();
    // Filament renders a confirmation modal as an alertdialog.
    await moderator
      .getByRole("alertdialog", { name: /^approve$/i })
      .getByRole("button", { name: /^confirm$/i })
      .click();
    await expect(row).toHaveCount(0);
    await moderator.close();

    // 4. Visitors now see it in the category and can open it.
    await visitor.reload();
    const listed = visitor.getByRole("link", { name: title, exact: true });
    await expect(listed).toBeVisible();
    await listed.click();
    await expect(
      visitor.getByRole("heading", { level: 1, name: title }),
    ).toBeVisible();
    await visitor.close();

    // 5. The author's own list shows it as published.
    await page.reload();
    const mine = page.getByRole("listitem").filter({ hasText: title });
    await expect(mine).toBeVisible();
    await expect(mine.getByText(mk.account.statusPending)).toHaveCount(0);
  });
});
