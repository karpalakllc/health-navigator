import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { login } from "./support/auth";
import { attemptUser, forum, uniqueSuffix } from "./support/fixtures";

/*
 * Seeded moderation flags (E2ESeeder::seedSiteSettings): new topics from
 * members need moderation, replies publish immediately.
 */
test.describe("forum", () => {
  test("a member's new topic waits for moderation and stays off the public list", async ({
    page,
    browser,
  }, testInfo) => {
    const title = `Прашање за сон ${uniqueSuffix()}`;

    await login(page, attemptUser("forum", testInfo.retry));
    await page.goto(`/forum/new?category=${forum.categorySlug}`);

    await page.getByLabel(mk.common.title).fill(title);
    await page
      .getByLabel(mk.common.message)
      .fill("Колку часа сон е препорачано за возрасен човек? (информативно)");
    await page.getByLabel(mk.forum.consent).check();
    await page
      .getByRole("button", { name: mk.forum.topicSubmitModeration })
      .click();

    // The author lands on "Мој форум", where the topic shows as pending.
    await expect(page).toHaveURL(/\/account\/forum$/);
    const mine = page.getByRole("listitem").filter({ hasText: title });
    await expect(mine).toBeVisible();
    await expect(mine.getByText(mk.account.statusPending)).toBeVisible();

    // Nobody else sees it until a moderator approves it.
    const visitor = await browser.newPage();
    await visitor.goto(`/forum/${forum.categorySlug}`);
    await expect(
      visitor.getByRole("heading", { name: forum.topicTitle }),
    ).toBeVisible();
    await expect(visitor.getByText(title)).toHaveCount(0);
    await visitor.close();
  });

  test("a member's reply on an approved topic appears", async ({
    page,
  }, testInfo) => {
    const body = `Мене ми помага редовен распоред. ${uniqueSuffix()}`;

    await login(page, attemptUser("forum", testInfo.retry));
    await page.goto(`/forum/${forum.categorySlug}/${forum.topicSlug}`);
    await expect(
      page.getByRole("heading", { level: 1, name: forum.topicTitle }),
    ).toBeVisible();

    const replyForm = page.locator("form").filter({
      has: page.getByRole("button", { name: mk.forum.replySubmit }),
    });
    await replyForm.getByLabel(mk.common.message).fill(body);
    const posted = page.waitForResponse(
      (response) =>
        response.url().endsWith("/api/forum/posts") &&
        response.request().method() === "POST",
    );
    await replyForm.getByRole("button", { name: mk.forum.replySubmit }).click();
    expect((await posted).status()).toBe(201);

    // In the list of replies — not the composer, which still holds the text
    // until the request settles.
    const reply = page
      .getByRole("main")
      .getByRole("listitem")
      .filter({ hasText: body });
    await expect(reply).toBeVisible();

    // Published, not just echoed back to its author.
    await page.context().clearCookies();
    await page.reload();
    await expect(reply).toBeVisible();
  });

  test("topics in an unpublished category never surface", async ({ page }) => {
    await page.goto("/forum");
    await expect(
      page.getByRole("heading", { name: forum.categoryName }),
    ).toBeVisible();
    await expect(page.getByText(forum.hiddenTopicTitle)).toHaveCount(0);
    await expect(page.getByText("Скриена категорија")).toHaveCount(0);

    // Forum topic search.
    await page.goto(`/forum?q=${encodeURIComponent(forum.hiddenTopicKeyword)}`);
    await expect(page.getByText(forum.hiddenTopicTitle)).toHaveCount(0);

    // Site-wide search.
    await page.goto(
      `/search?q=${encodeURIComponent(forum.hiddenTopicKeyword)}`,
    );
    await expect(page.getByText(forum.hiddenTopicTitle)).toHaveCount(0);

    // Direct links, too.
    for (const path of [
      `/forum/${forum.hiddenCategorySlug}`,
      `/forum/${forum.hiddenCategorySlug}/${forum.hiddenTopicSlug}`,
    ]) {
      await page.goto(path);
      await expect(
        page.getByRole("heading", { name: mk.notFound.title }),
      ).toBeVisible();
      await expect(page.getByText(forum.hiddenTopicTitle)).toHaveCount(0);
    }
  });

  test("forum search does find a published topic (control for the test above)", async ({
    page,
  }) => {
    await page.goto(`/forum?q=${encodeURIComponent("Добредојдовте")}`);
    await expect(
      page.getByRole("heading", { name: forum.topicTitle }).first(),
    ).toBeVisible();
  });
});
