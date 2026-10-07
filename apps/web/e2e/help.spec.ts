import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { login } from "./support/auth";
import { attemptUser, forum, help } from "./support/fixtures";

/*
 * W8-A „Прашања без одговор“. E2ESeeder::seedHelpTopic seeds one approved
 * question nobody answers; nothing here replies to it.
 */
test.describe("unanswered questions", () => {
  const answerName = `${mk.help.answer} на темата „${help.topicTitle}“`;

  test("a guest finds the question under „Без одговор“ and is asked to sign in at its reply box", async ({
    page,
  }) => {
    await page.goto("/forum");
    await page
      .getByRole("navigation", { name: mk.help.viewLabel })
      .getByRole("link", { name: new RegExp(`^${mk.help.viewUnanswered}`) })
      .click();

    await expect(page).toHaveURL(/\/forum\?view=unanswered$/);
    await expect(
      page.getByRole("heading", { level: 2, name: mk.help.listTitle }),
    ).toBeVisible();

    await page.getByRole("link", { name: answerName }).click();

    await expect(page).toHaveURL(
      new RegExp(`/forum/${forum.categorySlug}/${help.topicSlug}#forum-reply$`),
    );
    const replyBox = page.locator("#forum-reply");
    await expect(
      replyBox.getByRole("heading", { name: mk.forum.loginCtaTitle }),
    ).toBeVisible();

    // Signing in comes back to the same reply box.
    await replyBox.getByRole("link", { name: mk.forum.guestReplyCta }).click();
    await expect(page).toHaveURL(
      new RegExp(
        `/login\\?redirect=${encodeURIComponent(`/forum/${forum.categorySlug}/${help.topicSlug}#forum-reply`)}$`,
      ),
    );
  });

  test("a member answers from the category's „Без одговор“ chip straight into the reply form", async ({
    page,
  }, testInfo) => {
    await login(page, attemptUser("forum", testInfo.retry));
    await page.goto(`/forum/${forum.categorySlug}`);
    await page
      .getByRole("navigation", { name: mk.forum.sortLabel })
      .getByRole("link", { name: mk.help.viewUnanswered })
      .click();

    await expect(page).toHaveURL(/sort=unanswered/);
    await page.getByRole("link", { name: answerName }).click();

    await expect(page).toHaveURL(new RegExp(`${help.topicSlug}#forum-reply$`));
    await expect(
      page.locator("#forum-reply").getByLabel(mk.common.message),
    ).toBeVisible();
  });
});
