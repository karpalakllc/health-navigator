import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";
import { mk } from "../src/i18n/mk";
import { login } from "./support/auth";
import { attemptUser, forum, users } from "./support/fixtures";

/*
 * Contributor levels (W8-C, docs/levels.md): the „Заедница“ page is reachable
 * from the footer and explains how titles are earned with the API's numbers;
 * a member marks a forum reply „Корисно“ and takes it back; the account page
 * shows the member's own progress card.
 */
test.describe("contributor levels", () => {
  test("the forum hub links to the community page", async ({ page }) => {
    await page.goto("/forum");
    await page
      .getByRole("complementary")
      .getByRole("link", { name: mk.levels.forumHubLink })
      .click();

    await expect(page).toHaveURL(/\/community$/);
    await expect(
      page.getByRole("heading", { level: 1, name: mk.levels.heroTitle }),
    ).toBeVisible();
  });

  test("the footer leads to the community page with its lists and rules", async ({
    page,
  }) => {
    await page.goto("/");
    await page
      .getByRole("contentinfo")
      .getByRole("link", { name: mk.levels.pageTitle })
      .click();

    await expect(page).toHaveURL(/\/community$/);
    await expect(
      page.getByRole("heading", { level: 1, name: mk.levels.heroTitle }),
    ).toBeVisible();
    await expect(
      page.getByRole("heading", { name: mk.levels.reviewersTitle }),
    ).toBeVisible();
    await expect(
      page.getByRole("heading", { name: mk.levels.howTitle }),
    ).toBeVisible();
    await expect(
      page
        .getByRole("region", { name: mk.levels.ladderReviewsTitle })
        .getByRole("row", { name: new RegExp(mk.levels.reviewLevel5) }),
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

  test("a member marks a forum reply helpful and takes it back", async ({
    page,
  }, testInfo) => {
    await login(page, attemptUser("forum", testInfo.retry));
    await page.goto(`/forum/${forum.categorySlug}/${forum.topicSlug}`);

    // The seeded reply is by member@e2e.test, so this member may vote on it.
    const reply = page
      .getByRole("article")
      .filter({ hasText: "Прв одобрен одговор во темата." });
    const button = reply.getByRole("button", { name: /^Корисно/ });

    await expect(button).toHaveAttribute("aria-pressed", "false");
    // The toggle is optimistic: wait for the vote to be stored before the
    // reload, or the reload can render ahead of it (or abort it).
    const saved = page.waitForResponse(
      (response) =>
        response.url().endsWith("/api/forum/posts/helpful") &&
        response.request().method() === "PUT",
    );
    await button.click();
    expect((await saved).ok()).toBe(true);
    await expect(button).toHaveAttribute("aria-pressed", "true");
    await expect(button).toHaveText(/1/);

    await page.reload();
    const again = page
      .getByRole("article")
      .filter({ hasText: "Прв одобрен одговор во темата." })
      .getByRole("button", { name: /^Корисно/ });
    await expect(again).toHaveAttribute("aria-pressed", "true");
    await again.click();
    await expect(again).toHaveAttribute("aria-pressed", "false");
  });

  test("the account page shows the member's own progress", async ({ page }) => {
    await login(page, users.member);
    await page.goto("/account");

    const card = page.getByRole("region", { name: mk.levels.progressTitle });
    await expect(card).toBeVisible();
    await expect(
      card.getByRole("heading", { name: mk.levels.progressReviews }),
    ).toBeVisible();
    await expect(
      card.getByRole("link", { name: mk.levels.howLink }),
    ).toHaveAttribute("href", "/community#zvanja");
  });
});
