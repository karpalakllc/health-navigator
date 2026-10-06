import type { Metadata } from "next";
import { mk } from "@/i18n/mk";
import { tFormat } from "@/i18n/t";
import { parseListPage } from "@/lib/api/directory-cache-policy";
import { siteUrl } from "@/lib/site-url";
import { latinSearchVariant, toLatin } from "@/lib/transliterate";

/**
 * The site-wide share image (public/og-default.png, 1200×630, D2a palette).
 *
 * Set on every page here rather than through an app/opengraph-image file: a
 * page's own `openGraph` object replaces its parent's wholesale, so a root
 * file-based image would be dropped by every page that calls pageMetadata.
 * Twitter's card falls back to the OpenGraph image.
 */
export const DEFAULT_OG_IMAGE = {
  url: "/og-default.png",
  width: 1200,
  height: 630,
  alt: `${mk.nav.wordmark}: ${mk.meta.description}`,
} as const;

/**
 * Shared metadata for a public page.
 *
 * Previously this emitted only a title and description, so every link shared to
 * Facebook, Viber or X rendered as a blank card, and no page declared a
 * canonical URL. `metadataBase` is what lets Next resolve the relative canonical
 * and OpenGraph paths below into absolute ones.
 */
export function pageMetadata(
  title: string,
  description?: string,
  options: {
    path?: string;
    noIndex?: boolean;
    /** With noIndex: still let crawlers follow the links (thin tag pages). */
    follow?: boolean;
    ogType?: "website" | "article";
  } = {},
): Metadata {
  const fullTitle = `${title} · ${mk.meta.title}`;
  const summary = description ?? mk.meta.description;

  return {
    metadataBase: new URL(siteUrl()),
    title: fullTitle,
    description: summary,
    alternates: options.path ? { canonical: options.path } : undefined,
    openGraph: {
      title: fullTitle,
      description: summary,
      siteName: mk.meta.title,
      locale: "mk_MK",
      type: options.ogType ?? "website",
      url: options.path,
      images: [DEFAULT_OG_IMAGE],
    },
    twitter: {
      card: "summary_large_image",
      title: fullTitle,
      description: summary,
    },
    // Account pages hold personal data and must never be indexed, nor may a
    // stand-in for a switched-off module. The key is omitted otherwise: Next
    // merges metadata key by key, so `robots: undefined` here would wipe the
    // root layout's maintenance-mode noindex.
    ...(options.noIndex
      ? { robots: { index: false, follow: options.follow ?? false } }
      : {}),
  };
}

/**
 * Search Console / Bing Webmaster Tools ownership tags, from server env vars
 * (GOOGLE_SITE_VERIFICATION, BING_SITE_VERIFICATION: the token only, not the
 * whole tag). Read at request time — the layout renders per request — so a
 * token can be added without a rebuild. Undefined when neither is set.
 */
export function siteVerification(
  env: Record<string, string | undefined> = process.env,
): Metadata["verification"] {
  const google = env.GOOGLE_SITE_VERIFICATION?.trim();
  const bing = env.BING_SITE_VERIFICATION?.trim();

  if (!google && !bing) {
    return undefined;
  }

  return {
    ...(google ? { google } : {}),
    ...(bing ? { other: { "msvalidate.01": bing } } : {}),
  };
}

/** Plain text of a post body, whitespace collapsed, cut at a word. */
export function plainExcerpt(text: string, max: number): string {
  const plain = text.replace(/\s+/g, " ").trim();

  if (plain.length <= max) {
    return plain;
  }

  const cut = plain.slice(0, max - 1);
  const atWord = cut.slice(0, cut.lastIndexOf(" "));

  return `${(atWord.length > max * 0.6 ? atWord : cut).replace(/[\s,.;:–-]+$/, "")}…`;
}

/** Google shows ~155–160 characters of a description. */
const DESCRIPTION_LENGTH = 160;

/**
 * <title> and description of a forum topic.
 *
 * Title: the topic's own, plus its first keyword when the title does not
 * already say it (compared spelling-independently) — one keyword, never a
 * list. Description: the opening post, then — for a Cyrillic title — its
 * Latin spelling once in brackets, so "operacija za prosireni veni" matches
 * „Операција за проширени вени“ (docs/seo.md). The excerpt is shortened to
 * leave room for it.
 */
export function forumTopicMeta(topic: {
  title: string;
  body: string;
  tags?: { name: string; latin: string }[];
}): { title: string; description: string } {
  const firstTag = topic.tags?.[0];
  const titleKey = toLatin(topic.title);
  const title =
    firstTag && !titleKey.includes(firstTag.latin)
      ? `${topic.title} – ${firstTag.name}`
      : topic.title;

  const latin = latinSearchVariant(topic.title);
  const suffix = latin ? ` (${latin})` : "";
  const excerpt = plainExcerpt(
    topic.body,
    Math.max(60, DESCRIPTION_LENGTH - suffix.length),
  );

  return { title, description: `${excerpt}${suffix}` };
}

/**
 * <title> and description of a doctor or facility profile: the name with
 * what it is and where („Елена Димитрова – Кардиологија, Скопје“), then the
 * profile text, then the Latin spelling of a Cyrillic name once, for searches
 * like "elena dimitrova kardiolog".
 */
export function profileMeta(input: {
  name: string;
  kind?: string | null;
  city?: string | null;
  text?: string | null;
  fallback: string;
}): { title: string; description: string } {
  const where = [input.kind, input.city].filter(Boolean).join(", ");
  const title = where ? `${input.name} – ${where}` : input.name;
  const latin = latinSearchVariant(input.name);
  const suffix = latin ? ` (${latin})` : "";
  const lead = [where, input.text ? plainExcerpt(input.text, 400) : null]
    .filter(Boolean)
    .join(". ");
  const description = plainExcerpt(
    lead || input.fallback,
    Math.max(60, DESCRIPTION_LENGTH - suffix.length),
  );

  return { title, description: `${description}${suffix}` };
}

/**
 * A tag page is indexable (and listed in the sitemap) from this many visible
 * topics; below it is thin, so noindex — but followed, so its topics are
 * still discovered. Same value as ForumTag::INDEXABLE_MIN_TOPICS in the API.
 */
export const TAG_INDEXABLE_MIN_TOPICS = 3;

export function isIndexableTag(topicsCount: number | undefined): boolean {
  return (topicsCount ?? 0) >= TAG_INDEXABLE_MIN_TOPICS;
}

/** Title and description of /forum/tags/[tag]. */
export function forumTagMeta(tag: { name: string; latin: string }): {
  title: string;
  description: string;
} {
  const latin = latinSearchVariant(tag.name);

  return {
    title: tFormat("seo.tagPageTitle", { tag: tag.name }),
    description: `${tFormat("seo.tagPageDescription", { tag: tag.name })}${
      latin ? ` (${latin})` : ""
    }`,
  };
}

export type ListSearchParams = Record<string, string | string[] | undefined>;

const TRACKING_PARAMS = new Set([
  "fbclid",
  "gclid",
  "dclid",
  "gbraid",
  "wbraid",
  "msclkid",
  "yclid",
  "igshid",
  "mc_cid",
  "mc_eid",
  "ref",
]);

function isTrackingParam(key: string): boolean {
  const name = key.toLowerCase();

  return name.startsWith("utm_") || TRACKING_PARAMS.has(name);
}

/**
 * Canonical path for a paginated, filterable list page.
 *
 * Any filter (text, slug, sort) points at the bare list: filter combinations
 * are views of it, not documents to index one by one. An unfiltered deeper
 * page keeps its `?page=N`, since page 2 is not a duplicate of page 1.
 * Campaign and click-tracking parameters (utm_*, fbclid…) are not filters:
 * a shared link to page 3 is still page 3.
 */
export function listCanonicalPath(
  path: string,
  params: ListSearchParams = {},
): string {
  const filtered = Object.entries(params).some(
    ([key, value]) =>
      key !== "page" &&
      !isTrackingParam(key) &&
      (Array.isArray(value) ? value.length > 0 : (value ?? "").trim() !== ""),
  );

  if (filtered) {
    return path;
  }

  const page = parseListPage(
    typeof params.page === "string" ? params.page : undefined,
  );

  return page > 1 ? `${path}?page=${page}` : path;
}
