# Contributor levels and the monthly lists (W8-C)

Members who write reviews and help in the forum earn a **title** shown as a small
chip next to their username, and the most helpful of them appear for a month on
the public „Заедница“ page (`/community`). The goal is recognition: people like
titles, and a visible „thank you“ motivates the next review or answer.

What it must never become: an incentive that buys reviews. There are **no
prizes, discounts, money or perks** of any kind, now or later, tied to a level, a
list or a rating (consumer law чл. 71(1) т. 27, and our promise
„рецензиите не се купуваат“, docs/legal/research-memo.md). Points never depend on
*what* a review says or how many stars it gives — only on it being published and
on other members finding it useful.

## Where it shows

| Place | What |
|---|---|
| Review cards (doctor, facility, pharmacy profiles) | Reviewer title chip after the username |
| Forum topic and replies | Forum title chip after the username (next to „Автор“) |
| `/account` | „Вашите звања“ card: both titles, points, counts, the next step („Уште 2 рецензии и 15 поени до „Активен рецензент“.“) and a link to the rules |
| `/community` (footer → „Заедница“) | Last calendar month's „Најкорисни рецензенти“ and „Најактивни во форумот“, top 10 each, then the rules and both ladders |

**Why a page of its own, not sections on /forum or /transparency.** The reviewer
list does not belong in the forum, and /transparency is about moderation
figures, not people; mixing them would blur both. One calm page holds both lists
and the rules that produce them (the rules come from the API, so the page always
states the numbers actually applied). It is linked from the footer („Ресурси“)
and from the account card. A teaser on the home page or a link on the forum hub
can be added later (the forum hub belongs to W8-A this wave).

No public member pages exist: a level is only ever seen as a chip on content the
member already published, on the monthly list, or by the member themselves.

## Points

Only **approved, public** content counts. Nothing is ever earned from content
waiting for moderation or refused.

| Earns | Reviews | Forum |
|---|---|---|
| Published item | review **+10** | topic **+3**; reply in **someone else's** topic **+5** (replies in one's own topic: 0) |
| „Корисно“ from another member | **+2** per vote on a review | **+3** per vote on a reply |
| Taken down after publication | **−20** | **−15** (topic or reply) |

Anti-gaming rules (all in `App\Support\Levels\LevelRules`):

- **Per-day caps** on what earns points, by the day the item was *submitted*
  (Macedonian time): 5 reviews, 3 topics, 8 replies. A moderator approving a
  backlog at once does not cut anyone's points. Items over the cap still count
  as published (for the level's minimum count), they just earn nothing.
- **Vote caps:** at most **10** „Корисно“ votes count per review or reply, and at
  most **3** from the same member for the same author (all items together), in
  the order they were cast. This blunts vote rings and a friend voting on
  everything.
- **Self-votes** never count (the API refuses them; the scoring also ignores any
  that slipped past). Votes from **suspended** accounts do not count.
- **Removed content subtracts** more than it earned, so posting something that
  gets taken down never pays.
- **Suspended accounts, staff and temporary („clen-…“) usernames** show no title
  and are never on a list. Staff carry „Тим“ / their role instead.
- **Deleted accounts** lose their levels row at once; their posts show
  „Избришан корисник“ with no title (posts must not be linkable to each other).
- Totals never go below zero.

## Ladders

A level needs **both** the count and the points. Points rise with „Корисно“, so
higher levels need content others found useful, not just volume.

**Reviewers**

| Level | Title | Published reviews | Points |
|---|---|---|---|
| 1 | Рецензент | 1 | 10 |
| 2 | Активен рецензент | 3 | 35 |
| 3 | Посветен рецензент | 6 | 80 |
| 4 | Искусен рецензент | 12 | 170 |
| 5 | Столб на заедницата | 25 | 380 |

**Forum** (posts = published topics + replies in other members' topics)

| Level | Title | Posts | Points |
|---|---|---|---|
| 1 | Соговорник | 1 | 3 |
| 2 | Помошник | 5 | 35 |
| 3 | Посветен помошник | 20 | 140 |
| 4 | Стожер на форумот | 50 | 380 |

Wording choices: no title implies medical expertise or verification („Експерт“,
„Советник“, „Доверлив“, „Верификуван“ are deliberately avoided — the forum is
not medical advice, and „верификуван“ is reserved for profiles). „Истакнат“ is
avoided because it already labels featured profiles. Titles are in
`apps/web/src/i18n/mk.ts` (`levels.reviewLevelN`, `levels.forumLevelN`); the API
sends only the level number. Please have a native speaker confirm the titles.

## Monthly lists

- Window: the **previous calendar month**, Europe/Skopje. On 7 October the page
  shows September.
- Same scoring as above, restricted to the month: items published in it, votes
  cast in it, removals made in it (caps apply within the month).
- Ranked by points, then „Корисно“ received, then username. Members with 0 points
  are left out; at most 10 per list.
- Shown: rank, username, current title, and the month's counts (reviews or posts,
  „Корисно“ votes). Never ids, real names, e-mail or avatars.
- The forum list is withheld while the forum module is off.
- Cached for 6 hours in the API (`levels:leaderboards:{YYYY-MM}`), 5 minutes as a
  public HTTP cache, 10 minutes in the web tier; the nightly run clears it.

## Computation

- `contributor_levels` (one row per member with published content) holds the
  derived points, counts and levels. It is never edited by hand and can be
  dropped and rebuilt at any time.
- Recomputed for the author **after commit** whenever a review, topic or reply is
  approved, refused, taken down or deleted (`ContributorLevelObserver`), and
  whenever one of their items gains or loses a „Корисно“ vote. A topic's status
  change also recomputes everyone who replied in it (replies only count inside a
  published topic).
- **Nightly** at 03:30: `php artisan levels:recompute` rebuilds every row and
  clears the cached lists, healing anything an event missed (bulk updates, rule
  changes).
- Changing a number: edit `LevelRules`, update the tables above, run
  `levels:recompute`. The web reads the rules from the API.

## API

| Route | Auth | Returns |
|---|---|---|
| `GET /me/levels` | member | `shown_publicly`, and per ladder `level`, `points`, counts, `helpful`, `next` (`level`, `missing_reviews`/`missing_posts`, `missing_points`, or null at the top) |
| `GET /community/leaderboards` | public | `month`, `reviewers[]`, `forum[]` (or null), `rules` |
| `PUT` / `DELETE /forum/posts/{post}/helpful` | member who may post | `helpful_count`, `has_voted_helpful` |

Public payloads: `author_level` on review list items; `author.level` on forum
topic and reply authors; `helpful_count` (and `viewer.has_voted_helpful`) on
replies.

## Privacy

Levels are derived from content that is already public; nothing about browsing
is stored. `contributor_levels` and `forum_post_helpful_votes` are listed in
docs/data-inventory.md, included in the member's export, and the levels row is
deleted on account deletion.
