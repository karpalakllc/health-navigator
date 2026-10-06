import { describe, expect, it } from "vitest";
// Next's own list, which htmlLimitedBots replaces when set.
import { HTML_LIMITED_BOT_UA_RE } from "next/dist/shared/lib/router/utils/html-bots";
import robots, { DISALLOWED_PATHS } from "@/app/robots";
import {
  AI_CRAWLERS,
  htmlLimitedBotsPattern,
  NEXT_DEFAULT_HTML_LIMITED_BOTS,
} from "@/lib/crawlers";

describe("robots.txt", () => {
  process.env.NEXT_PUBLIC_SITE_URL = "https://zdravje.test";

  it("names search engines and every AI crawler with the same rules as *", () => {
    const { rules, sitemap } = robots();
    const groups = Array.isArray(rules) ? rules : [rules];
    const named = groups.find((group) => Array.isArray(group.userAgent));
    const star = groups.find((group) => group.userAgent === "*");

    expect(named?.userAgent).toEqual(
      expect.arrayContaining([
        "Googlebot",
        "Bingbot",
        "GPTBot",
        "OAI-SearchBot",
        "ChatGPT-User",
        "ClaudeBot",
        "Claude-SearchBot",
        "Claude-User",
        "PerplexityBot",
        "Perplexity-User",
        "Google-Extended",
        "Applebot-Extended",
        "CCBot",
      ]),
    );
    // A crawler follows only its most specific group, so it must repeat the
    // private paths rather than inherit them from *.
    expect(named?.disallow).toEqual(star?.disallow);
    expect(named?.allow).toBe("/");
    expect(sitemap).toBe("https://zdravje.test/sitemap.xml");
  });

  it("keeps accounts, the API, admin and search results out", () => {
    expect(DISALLOWED_PATHS).toEqual(
      expect.arrayContaining([
        "/api/",
        "/account",
        "/admin",
        "/search",
        "/login",
      ]),
    );
    // Public content stays crawlable.
    for (const path of ["/forum", "/doctors", "/facilities", "/forum/tags"]) {
      expect(DISALLOWED_PATHS.some((blocked) => path.startsWith(blocked))).toBe(
        false,
      );
    }
  });
});

describe("htmlLimitedBots", () => {
  it("still contains Next's default list verbatim", () => {
    expect(NEXT_DEFAULT_HTML_LIMITED_BOTS).toBe(HTML_LIMITED_BOT_UA_RE.source);
  });

  it("adds the AI crawlers and Googlebot, not ordinary browsers", () => {
    const pattern = htmlLimitedBotsPattern();

    for (const ua of [
      "Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)",
      "Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)",
      "Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)",
      "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)",
      "CCBot/2.0 (https://commoncrawl.org/faq/)",
      "Mozilla/5.0 (compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot)",
      "Twitterbot/1.0",
    ]) {
      expect(pattern.test(ua), ua).toBe(true);
    }

    expect(
      pattern.test(
        "Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile Safari/604.1",
      ),
    ).toBe(false);
    expect(AI_CRAWLERS.length).toBeGreaterThan(5);
  });
});
