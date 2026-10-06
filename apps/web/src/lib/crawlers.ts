/*
 * Crawlers we invite explicitly (robots.ts) and that get a fully blocking
 * render (htmlLimitedBots in next.config.ts). Sources and purposes are in
 * docs/seo.md. Relative imports only: next.config.ts loads this file before
 * the "@/…" alias exists.
 */

/** Search engines (robots.txt tokens). */
export const SEARCH_CRAWLERS = [
  "Googlebot",
  "Bingbot",
  "Applebot",
  "DuckDuckBot",
  "YandexBot",
] as const;

/**
 * AI assistants and AI search. Fetchers acting for a user (ChatGPT-User,
 * Claude-User, Perplexity-User) and training crawlers (GPTBot, ClaudeBot,
 * CCBot) alike: the owner wants the forum and the directory to be found from
 * ChatGPT, Claude, Perplexity and Gemini. Google-Extended and
 * Applebot-Extended are robots.txt tokens only (they control use for
 * training/grounding; the crawling is Googlebot's/Applebot's).
 */
export const AI_CRAWLERS = [
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
] as const;

/**
 * Next 16's default HTML-limited bot pattern
 * (next/dist/shared/lib/router/utils/html-bots.js). Setting htmlLimitedBots
 * replaces it, so it is repeated here; robots.test.ts fails if Next's copy
 * changes.
 */
export const NEXT_DEFAULT_HTML_LIMITED_BOTS =
  "[\\w-]+-Google|Google-[\\w-]+|Chrome-Lighthouse|Slurp|DuckDuckBot|baiduspider|yandex|sogou|bitlybot|tumblr|vkShare|quora link preview|redditbot|ia_archiver|Bingbot|BingPreview|applebot|facebookexternalhit|facebookcatalog|Twitterbot|LinkedInBot|Slackbot|Discordbot|WhatsApp|SkypeUriPreview|Yeti|googleweblight";

/**
 * Bots that get blocking metadata: <title>, description, canonical and
 * robots in <head> before any body HTML, instead of streamed to the end of
 * <body>.
 *
 * Next's defaults plus the AI crawlers, which read raw HTML and do not run
 * JavaScript, so metadata at the end of <body> is easy for them to miss; and
 * Googlebot, because Google only honours rel=canonical (and reads robots
 * meta most reliably) in <head>. The page body still streams (loading.tsx
 * boundaries), so a missing page is a 200 with a noindex tag, not a 404 —
 * see docs/seo.md.
 */
export function htmlLimitedBotsPattern(): RegExp {
  const ai = AI_CRAWLERS.filter(
    (name) => name !== "Google-Extended" && name !== "Applebot-Extended",
  );

  return new RegExp(
    [NEXT_DEFAULT_HTML_LIMITED_BOTS, "Googlebot", ...ai].join("|"),
    "i",
  );
}
