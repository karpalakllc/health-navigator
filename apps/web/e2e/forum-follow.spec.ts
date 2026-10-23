import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { login } from "./support/auth";
import { attemptUser, forum, uniqueSuffix } from "./support/fixtures";

/*
 * „Следи ја темата“ (docs/forum.md): a member follows a topic, another member
 * replies (replies publish immediately in the E2E seed), and the follower
 * finds the line in „Известувања“ that leads back to the reply.
 */
test.describe("forum: following a topic", () => {
  test("a follower is told about a new reply", async ({
    page,
    browser,
  }, testInfo) => {
    const topicPath = `/forum/${forum.categorySlug}/${forum.topicSlug}`;

    await login(page, attemptUser("follower", testInfo.retry));
    await page.goto(topicPath);

    const follow = page.getByRole("button", { name: mk.forum.follow });
    await expect(follow).toBeVisible();
    if ((await follow.getAttribute("aria-pressed")) !== "true") {
      const saved = page.waitForResponse(
        (response) =>
          response.url().endsWith("/api/forum/topics/follow") &&
          response.request().method() === "PUT",
      );
      await follow.click();
      expect((await saved).status()).toBe(200);
    }
    await expect(follow).toHaveAttribute("aria-pressed", "true");

    // The signed-in thread (follow, quote buttons) has no blocking axe issues.
    const axe = await new AxeBuilder({ page })
      .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"])
      .analyze();
    expect(
      axe.violations
        .filter((v) => v.impact === "serious" || v.impact === "critical")
        .map((v) => v.id),
    ).toEqual([]);

    // Still following after a reload: the API kept it.
    await page.reload();
    await expect(
      page.getByRole("button", { name: mk.forum.follow }),
    ).toHaveAttribute("aria-pressed", "true");

    // Another member replies.
    const context = await browser.newContext();
    const replier = await context.newPage();
    await login(replier, attemptUser("forum", testInfo.retry));
    await replier.goto(topicPath);
    const body = `Одговор за следачот ${uniqueSuffix()}`;
    const replyForm = replier.locator("form").filter({
      has: replier.getByRole("button", { name: mk.forum.replySubmit }),
    });
    await replyForm.getByLabel(mk.common.message).fill(body);
    const posted = replier.waitForResponse(
      (response) =>
        response.url().endsWith("/api/forum/posts") &&
        response.request().method() === "POST",
    );
    await replyForm.getByRole("button", { name: mk.forum.replySubmit }).click();
    expect((await posted).status()).toBe(201);
    await context.close();

    // The follower's notification names the topic and leads to the reply.
    await page.goto("/account/notifications");
    const line = page.getByRole("link", {
      name: new RegExp(forum.topicTitle),
    });
    await expect(line.first()).toBeVisible();
    await line.first().click();
    await expect(page).toHaveURL(new RegExp(`${topicPath}.*#post-\\d+$`));
    await expect(page.getByText(body)).toBeVisible();
  });
});
