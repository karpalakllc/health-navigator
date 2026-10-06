import { expect, test } from "@playwright/test";
import { forum } from "./support/fixtures";

/*
 * What a crawler that does not run JavaScript receives (docs/seo.md): the
 * raw HTML of a forum topic must already carry the structured data, the
 * opening post and the head metadata. Plain HTTP requests, no browser.
 * (The sitemap is covered by unit tests: it is built at `next build` time,
 * possibly before the E2E data exists, and then cached for an hour.)
 */
const GPTBOT =
  "Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)";

function jsonLdBlocks(html: string): Record<string, unknown>[] {
  return [
    ...html.matchAll(
      /<script type="application\/ld\+json">([\s\S]*?)<\/script>/g,
    ),
  ].map((match) => JSON.parse(match[1]) as Record<string, unknown>);
}

test.describe("SEO without JavaScript", () => {
  test("a forum topic's HTML holds its JSON-LD, the first post and head metadata", async ({
    request,
  }) => {
    const response = await request.get(
      `/forum/${forum.categorySlug}/${forum.topicSlug}`,
      { headers: { "User-Agent": GPTBOT } },
    );
    expect(response.status()).toBe(200);
    const html = await response.text();

    const posting = jsonLdBlocks(html).find(
      (block) => block["@type"] === "DiscussionForumPosting",
    );
    expect(posting).toMatchObject({
      headline: forum.topicTitle,
      text: "Одобрена тема за E2E тестови. Темите и одговорите се модерираат.",
      author: { "@type": "Person" },
      // Other specs may have added approved replies to this topic by now.
      comment: expect.arrayContaining([
        expect.objectContaining({
          "@type": "Comment",
          text: "Прв одобрен одговор во темата.",
        }),
      ]),
    });
    expect(
      jsonLdBlocks(html).some((block) => block["@type"] === "BreadcrumbList"),
    ).toBe(true);

    // The opening post is in the document itself, not only in the data.
    expect(html).toContain(
      "Одобрена тема за E2E тестови. Темите и одговорите се модерираат.",
    );

    // Blocking metadata for AI crawlers: title and canonical in <head>.
    const head = html.split("</head>")[0];
    expect(head).toContain(`<title>${forum.topicTitle}`);
    expect(head).toMatch(
      new RegExp(
        `<link rel="canonical" href="[^"]*/forum/${forum.categorySlug}/${forum.topicSlug}"`,
      ),
    );
    expect(head).toMatch(
      /<meta name="description" content="[^"]*\(dobredojdovte/,
    );
  });

  test("robots.txt invites AI crawlers and keeps accounts out; llms.txt lists the forum", async ({
    request,
  }) => {
    const robots = await (await request.get("/robots.txt")).text();
    expect(robots).toContain("User-Agent: GPTBot");
    expect(robots).toContain("User-Agent: ClaudeBot");
    expect(robots).toContain("Disallow: /account");
    expect(robots).toContain("Sitemap:");

    const llms = await request.get("/llms.txt");
    expect(llms.headers()["content-type"]).toContain("text/plain");
    expect(await llms.text()).toContain(`/forum/${forum.categorySlug})`);
  });
});
