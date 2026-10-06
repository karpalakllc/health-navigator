# SEO and AI discoverability

Goal: when someone searches Google, or asks ChatGPT, Claude, Perplexity or Gemini, something like „операција за проширени вени“ or "operacija za prosireni veni", our forum topics and doctor profiles should come up.

This document records what the code does, why it does it, and how it was measured. Sources were checked on 2026-10-06. Statements marked *(inference)* are our reading, not something the source says.

## 1. Rendering strategy

### Decision: keep per-request rendering with a CSP nonce

The root layout is `force-dynamic`, and `src/proxy.ts` mints a script nonce on every request (`script-src 'self' 'nonce-…' 'strict-dynamic'`, no `unsafe-inline`). We considered and rejected the faster alternatives below.

| Option | Why not |
|---|---|
| ISR or static pages with a static CSP | App Router pages inline their RSC payload as `self.__next_f.push(…)` scripts, which differ for every page. A static header cannot allow them without `'unsafe-inline'`, so security would get weaker. |
| Partial prerendering / Cache Components | Next's CSP guide: "Partial Prerendering (PPR) is incompatible with nonce-based CSP since static shell scripts won't have access to the nonce" (`node_modules/next/dist/docs/01-app/02-guides/content-security-policy.md`). |
| Experimental SRI (`experimental.sri`) | Hashes only external files, not the inline RSC scripts, and it is experimental. |
| Caching anonymous detail-page API reads in the Data Cache | A cached fetch is sent without the visitor's address, so it draws on the web tier's shared rate-limit bucket. Anyone can invent slugs, which turns this into the problem `directory-cache-policy.ts` exists to prevent. Rejected for doctor, facility and topic pages; the decorative profile ↔ forum list *is* cached (5 min), because its slugs come from pages that already rendered. |

The gaps that hurt crawlers were in the HTML they receive, not in server speed. Server-rendered TTFB is already about 5–20 ms locally. These are fixed:

- **`htmlLimitedBots`** (`next.config.ts`, list in `src/lib/crawlers.ts`). Next normally streams `<title>`, description, canonical and robots meta to the **end of `<body>`** for any client it does not recognise. That includes GPTBot, ClaudeBot, PerplexityBot, CCBot and Googlebot, because Next's default list only covers HTML-limited bots such as Bingbot and Twitterbot.
  - AI crawlers read raw HTML and are widely reported not to run JS *(unverified; only Apple documents rendering)*.
  - Google honours `rel=canonical` only in `<head>`.
  - These bots now receive blocking metadata in `<head>`.
  - `src/app/robots.test.ts` fails if Next's default list changes, because our setting replaces it.
- **Forum pages need no JavaScript.** Everything is server components, and the topic's JSON-LD, opening post and replies are in the HTML.
  - Because of the `loading.tsx` Suspense boundaries, the page body arrives as a streamed `<div hidden id="S:…">` segment that a small inline script swaps in.
  - Text extractors get the full text. A browser with JS disabled would see the skeleton.
  - Removing that would mean deleting every ancestor `loading.tsx` (including `app/loading.tsx`), which costs instant navigation feedback for people. We kept it.
- **Missing pages.** For the same streaming reason, a missing topic or profile returns **200 with `<meta name="robots" content="noindex">`**, not 404, even for blocking-metadata bots (measured).
  - Next documents this; Google treats it as excluded and Search Console may list it as a soft 404.
  - `generateMetadata` now calls `notFound()` on an API 404, so the head carries the not-found metadata rather than an indexable generic title.
  - A real 404 would need a slug check in `proxy.ts` (an extra API call per request). Left as an option.

### Measurements (local, `next start` against the API on :8024)

The script is `scratchpad/w5s/measure.sh`: curl, median of 5 runs, `Accept-Encoding: identity`.

- *HTML title in head*: y = title and canonical, t = title only, n = neither.
- `hidden S:` is the number of streamed segments.

**Before (main @ c8e6124)**

| Path | UA | Status | TTFB | Total | LD+JSON | Title in head |
|---|---|---|---|---|---|---|
| /doctors/elena-dimitrova | GPTBot | 200 | 5 ms | 83 ms | Physician only | **n** |
| /facilities/… | GPTBot | 200 | 5 ms | 73 ms | yes | **n** |
| /forum/…/topic | GPTBot | 200 | 5 ms | 47 ms | **none** | **n** |
| /forum/…/topic | Googlebot | 200 | 5 ms | 46 ms | **none** | **n** |
| /forum/…/missing | any | 200 | 5 ms | 35 ms | – | n |

**After**

| Path | UA | Status | TTFB | Total | LD+JSON | Title in head |
|---|---|---|---|---|---|---|
| /doctors/elena-dimitrova | browser | 200 | 7 ms | 111 ms | Physician, BreadcrumbList | n (streamed) |
| /doctors/elena-dimitrova | GPTBot | 200 | 66 ms | 112 ms | Physician, BreadcrumbList | **y** |
| /forum/…/topic | GPTBot | 200 | 62 ms | 63 ms | DiscussionForumPosting, BreadcrumbList | **y** |
| /forum/…/topic | Googlebot | 200 | 63 ms | 63 ms | DiscussionForumPosting, BreadcrumbList | **y** |
| /forum/…/missing | GPTBot | 200 + noindex | 46 ms | 46 ms | – | t (not-found title) |

- For bots, TTFB rises to the metadata fetch time (about 50–60 ms). This is the intended cost of blocking metadata.
- Browsers keep streaming: TTFB stays under 20 ms.

### Core Web Vitals (lab, 390×844 mobile, DPR 2, CPU throttled 4×)

The script is `scratchpad/w5s/vitals.mjs`: Playwright Chromium 153, median run.

- Lighthouse is not installed, and adding it would be a new package, so lab LCP, CLS and TBT come from `PerformanceObserver`.
- **Chromium 153 headless ignored CDP network throttling** (both `emulateNetworkConditions` and `…ByRule`; verified). These numbers are therefore CPU-bound, and transfer size is reported separately.
- INP needs real interactions; TBT stands in for it.

| Page | LCP before → after | CLS | TBT before → after | JS (decoded) before → after |
|---|---|---|---|---|
| / | 1148 → 976 ms | 0 | 74 → 92 ms | 823 → 589 KB |
| /doctors | 1016 → 928 ms | 0.012 | 67 → 46 ms | 848 → 614 KB |
| doctor profile | 1036 → 968 ms | 0 | 65 → 124 ms | 855 → 621 KB |
| forum topic | 1004 → 1000 ms | 0 | 62 → (noisy, 60–315) ms | 835 → 600 KB |

Topic page first-load JS, measured over the referenced chunks: **947 → 710 KB raw, 294 → 219 KB gzip** (the 110 KB `noModule` polyfill is included in both but modern browsers never load it).

The fixes:

1. **Sentry loads lazily.** The browser SDK was statically imported by `instrumentation-client.ts`, `error.tsx` and `global-error.tsx`, so about 150 KB raw sat in the shared first-load chunk of every page, even without a DSN.
   - `src/lib/sentry-client.ts` loads it after `load`, only when `NEXT_PUBLIC_SENTRY_DSN` is set, and buffers earlier errors.
2. **No italic Source Sans faces.** They were two more preloaded fonts (about 47 KB), and nothing on the site uses italic.

LCP elements are text (H1 or post body) and CLS is about 0. Profile images were already `loading="eager"` with `fetchPriority="high"`, so we found no image fix to make. Thresholds (web.dev/articles/vitals): LCP ≤ 2.5 s, INP ≤ 200 ms, CLS ≤ 0.1 at the 75th percentile.

## 2. Structured data

All builders are in `src/lib/structured-data.ts` and rendered by `components/seo/json-ld.tsx`. JSON-LD `<script>` blocks are data, not executed script, so CSP does not apply to them. Tests: `structured-data*.test.ts` plus a structural sanity checker (`test/schema-sanity.ts`) for required properties, ISO dates and null values.

### Forum topics: DiscussionForumPosting

Source: https://developers.google.com/search/docs/appearance/structured-data/discussion-forum

- Required on both the posting and each `Comment`: `author.name`, `datePublished`, and one of `text`/`image`/`video`. All are present.
- Replies are nested as `comment` items of type `Comment`, with `url` `#post-N`; the page renders matching `id`s.
- `author` is a `Person` with `name` only. `author.url` is recommended, not required, and members have no public profile pages, so we do not invent one.
- We also emit:
  - `headline`, `url` / `mainEntityOfPage`, and `dateModified` (latest reply)
  - `isPartOf` (the category page)
  - `keywords` (the topic's tags)
  - `interactionStatistic` (`CommentAction`, the reply count)
  - `inLanguage: mk`
- Multi-page threads: Google asks that later pages include the original post with the main URL. Page N renders the opening post, its `url` stays the first page, and `comment` lists that page's replies.
  - Each page has its own canonical (`?page=N`), because replies on page 2 are not on page 1.
- Why not QAPage: Google limits QAPage to pages whose focus is "a single question and its answers". Our topics are experiences and discussions.
- Breadcrumbs follow https://developers.google.com/search/docs/appearance/structured-data/breadcrumb: at least two items, and the last item may omit `item`.

### Doctors: Physician

Sources: https://schema.org/Physician, https://schema.org/IndividualPhysician

- Physician is a LocalBusiness / MedicalBusiness and MedicalOrganization subtype.
  - `IndividualPhysician` (schema.org v24) is more exact, but we keep `Physician`, the type Google's review-snippet tooling is sure to treat as a LocalBusiness *(inference)*.
- Specialties go in **`knowsAbout`** (text). `medicalSpecialty` expects schema.org's English `MedicalSpecialty` enumeration, and a Macedonian specialty name is not one of its values.
- Also emitted:
  - `knowsLanguage`
  - `availableService` (procedures as `MedicalProcedure`)
  - `hospitalAffiliation` (hospitals only)
  - `isAcceptingNewPatients`
  - `image`, `address`, `telephone`

**aggregateRating is allowed.** Google's review-snippet rule (https://developers.google.com/search/docs/appearance/structured-data/review-snippet) says: "If the entity that's being reviewed controls the reviews about itself, their pages that use LocalBusiness or any other type of Organization structured data are ineligible for star review feature."

- Doctors cannot add, edit or remove reviews here.
- The rating is shown on the page and comes from members.
- Featured or sponsored status does not give the doctor control over reviews.

We emit the rating only when at least one published review exists *(inference: eligible; stars are never guaranteed)*. If doctors ever get the power to hide reviews (see W5-C doctor claim), revisit this.

### Other types

- **Facilities:** `Hospital` / `MedicalClinic` / `DiagnosticLab`, the same rating rule, and `BreadcrumbList`.
- **ProfilePage:** not used. Google's ProfilePage is for creators' profiles (forum members, authors), not for organisations or people listed by an unaffiliated directory (https://developers.google.com/search/docs/appearance/structured-data/profile-page). Members have no public profile pages yet; when they do, mark those up as ProfilePage.
- **Home:** `WebSite` (site name).

## 3. Forum keywords, tag pages, Latin search

- **Data.** `forum_tags` holds the name, a slug, `match_key` and `latin`; `forum_tag_topic` has a `confirmed` flag. `App\Support\Forum\ForumTagNormalizer` normalises names: lower-case, no punctuation, 2–40 characters, not a bare number, at most 5 per topic.
- **Matching.** `match_key` folds Cyrillic, shaved Latin (`prosireni`), digraph Latin (`proshireni`) and diacritics (`prošireni`) onto one key. One row therefore serves every spelling.
- **Who edits.**
  - Members may suggest keywords when creating a topic (`tags` on `POST /forum/categories/{c}/topics`; a comma-separated field in the composer).
  - Staff and moderators edit them in Filament (Forum topics → View → *Keywords*). Saving there marks them `confirmed`.
- **In the page.**
  - **`<title>`** is the topic title, plus `– {main keyword}` if the title does not already contain it. The main keyword is the first one; keywords keep their given order. It is never a keyword list.
  - **Description** is the opening post, then the Latin spelling of a Cyrillic title, once, in brackets: `… (operacija za prosireni veni)`.
    - Google matches diacritic-free Latin queries better to text that contains them *(inference)*.
    - It appears once, as a natural transliteration, not a keyword list.
  - Visible keyword chips link to `/forum/tags/{slug}`.
- **Tag pages** `/forum/tags/[tag]` list visible topics.
  - Indexable from 3 topics (`TAG_INDEXABLE_MIN_TOPICS`, mirrored by `ForumTag::INDEXABLE_MIN_TOPICS`).
  - Below that: `noindex, follow`, so the page is not indexed (thin) but its links are still followed.
  - Only indexable tags appear in the sitemap. A tag with only hidden or pending topics returns 404 from the API.
  - A forum category with the slug `tags` would be shadowed by this route. Don't create one.
- **„Слични теми“** ranks topics with the most shared keywords first (across categories), then fills from the same category: `RelatedForumTopics::forTopic`.
- **Search.** Forum search (SQL path) matches keywords spelling-independently. The Meilisearch document includes both spellings (`tags` is a searchable attribute).

## 4. Profile ↔ forum links

`GET /forum/topics/related?doctor={slug}|facility={slug}` feeds an „Од форумот“ box on profiles. The box is hidden when empty.

- **Doctors: only moderator-confirmed keywords spelling the doctor's name.**
  - Leading titles (`д-р`, `dr`, `проф`, …) are ignored; a surname alone never matches.
  - A name in free text is not used: two doctors can share a name, and linking a profile to a discussion is a statement about a person. This keeps it exact and moderator-confirmable, as briefed.
- **Facilities:** a keyword spelling the facility name (any keyword), or the name in a topic title.

## 5. robots.txt, llms.txt, sitemap, canonicals

### robots.txt (`src/app/robots.ts`)

One named group of crawlers, with the same rules as `*` (a crawler obeys only its most specific group):

- **Search:** Googlebot, Bingbot, Applebot, DuckDuckBot, YandexBot
- **AI:** GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-SearchBot, Claude-User, PerplexityBot, Perplexity-User, Google-Extended, Applebot-Extended, CCBot

All are allowed on `/`. Disallowed: `/api/`, `/account`, `/admin`, `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, `/search`, `/forum/new`, `/design-system`.

- Search results also stay `noindex`. Google notes a robots-blocked page's `noindex` cannot be seen, but disallowing is what keeps crawl budget off endless filter URLs (https://developers.google.com/crawling/docs/faceted-navigation).

| Token | Purpose | Doc |
|---|---|---|
| GPTBot / OAI-SearchBot / ChatGPT-User | training / ChatGPT search ("opted out … will not be shown in ChatGPT search answers") / user fetches | https://developers.openai.com/api/docs/bots |
| ClaudeBot / Claude-SearchBot / Claude-User | training / search quality / user fetches | https://support.claude.com/en/articles/8896518 |
| PerplexityBot / Perplexity-User | search index (not training) / user fetches | https://docs.perplexity.ai/guides/bots |
| Google-Extended | Gemini training and grounding; "does not impact a site's inclusion in Google Search"; does **not** control AI Overviews (Googlebot does) | https://developers.google.com/crawling/docs/crawlers-fetchers/google-common-crawlers, https://developers.google.com/search/docs/appearance/ai-features |
| Applebot / Applebot-Extended | Siri, Spotlight, Safari / training opt-out token only | https://support.apple.com/en-us/119829 |
| CCBot | Common Crawl dataset (widely used for training) | https://commoncrawl.org/ccbot |

**Owner decision to confirm:** allowing the *training* crawlers (GPTBot, ClaudeBot, CCBot, Google-Extended, Applebot-Extended) means members' forum posts may end up in model training data.

- This helps answers mention us, but it is a privacy and terms question. The legal memo should say so (see `docs/legal/`).
- Opting out is a one-line change in `AI_CRAWLERS`, with a separate `disallow: "/"` group for those tokens.
- The search and user-fetch bots are what ChatGPT, Claude and Perplexity answers cite.

### llms.txt (`src/app/llms.txt/route.ts`, built by `src/lib/llms-txt.ts`)

Follows https://llmstxt.org: an H1, a blockquote summary, H2 link lists (sections, forum categories, 20 recent topics, indexable keywords), and an "Optional" section.

- Rendered per request from hour-cached reads.
- No vendor has said it reads the file. Google says no special AI files are needed, and John Mueller compared it to the keywords meta tag (reported by Search Engine Journal). It is cheap, so it is offered.
- `llms-full.txt` is skipped: not part of the spec, and not cheap with user content.

### Sitemap (`src/app/sitemap.ts`)

- **Bug fixed:** forum topics were never listed. The sitemap queried the search endpoint, which returns nothing without a query. It now walks each category's topic list.
- `lastmod` comes from real activity: topic = latest reply or publication; category = its latest topic; tag = its latest topic.
- Google uses `<lastmod>` "if it's consistently and verifiably … accurate" and ignores `priority` and `changefreq` (https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).

### Canonicals and hreflang

- Canonicals:
  - topic pages are self-canonical per page
  - forum category pages use `listCanonicalPath` (filters → bare list, page N kept)
  - tag pages are self-canonical per page
- `hreflang` is not needed for a single-language site *(inference)*. Google says it uses neither `hreflang` nor `lang` to detect language. `<html lang="mk">` stays for accessibility.

## 6. Search Console readiness checklist

1. **Verify ownership.**
   - Google: set `GOOGLE_SITE_VERIFICATION=<token>` (the `content` value only) in the web app's runtime environment. The root layout emits `<meta name="google-site-verification">`, read per request, so no rebuild is needed. Keep it permanently; Google rechecks (https://support.google.com/webmasters/answer/9008080).
   - A Domain property needs DNS verification instead.
   - Bing: `BING_SITE_VERIFICATION` emits `msvalidate.01`. Bing can also import the Google property.
2. **Submit `https://<domain>/sitemap.xml`** in the Sitemaps report. robots.txt also advertises it. The old ping endpoint is gone (2023).
3. **URL Inspection** → *Test live URL* for one doctor, one facility and one forum topic. Confirm the rendered HTML has the post text, and that *Enhancements* show "Discussion forum" and "Breadcrumbs" valid.
   - Also run https://search.google.com/test/rich-results on a topic and a doctor profile with reviews.
4. **Coverage after 2–4 weeks.** Check *Pages*:
   - Expect "Excluded by noindex" for thin tag pages and missing slugs, and for `/search` URLs found via links.
   - Investigate any "Duplicate without user-selected canonical".
5. **Core Web Vitals report** (field data, needs traffic). The lab numbers are above.
6. **Optional: IndexNow** for Bing, Yandex and others (Google does not participate): https://www.indexnow.org. Not built.
7. **Set `NEXT_PUBLIC_SITE_URL` to the real https origin at build time.** Canonicals, the sitemap and robots.txt depend on it.

## 7. Not done, or uncertain

- **True 404 status** for missing slugs: needs a proxy-level existence check (see §1).
- **Network-throttled lab metrics:** Chromium 153 headless ignored CDP throttling. Run Lighthouse or PageSpeed Insights once the site has a public URL.
- **Training-crawler opt-in:** an owner and legal decision (§5).
- **IndividualPhysician vs Physician** (§2): revisit if Google documents support.
- **Member profile pages with ProfilePage markup** would let `author.url` be filled.
