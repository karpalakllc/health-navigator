# Personal data inventory

Factual inventory of where the platform holds or sends personal data, written
for legal counsel reviewing the privacy notice
(`apps/web/src/content/legal/privacy.tsx`, which this document does **not**
change). It describes the code as of 2026-10-06, including the privacy-by-default
changes of that date and the account data-rights work (export, deletion,
devices, suspension — §5). Where something is *not* done, that is stated
rather than implied.

Defaults quoted below are the shipped defaults; an operator can change the ones
marked *(configurable)*.

## 1. Database tables

"Access" names the admin-panel (Filament, `/admin`) permissions that expose the
data; staff panel access requires a second factor (authenticator app). The
public API exposes only what is listed under "Public".

| Table | Personal data held | Purpose | Retention | Access |
|---|---|---|---|---|
| `users` | `name` (real name, **private**), `username` (public, unique, chosen by the member; `username_normalized` / `username_skeleton` are its folded forms for uniqueness), `username_changed_at`, `must_choose_username` (still a temporary `clen-…` name), `terms_accepted_at` + `terms_version` (sign-up consent: „14+ and I accept the Terms of Use and the Privacy Policy“; null for accounts from before 2026-10-14), `display_name` (**retired**: the old „Име П.“ public name, no longer shown anywhere or editable; the column is dropped in a later release), `email`, `password` (bcrypt hash), `email_verified_at`, `registration_contested_at`, `avatar_path` (uploaded photo), `user_kind`, `app_authentication_secret` and `app_authentication_recovery_codes` (staff second factor; encrypted with `APP_KEY`, codes also hashed), `remember_token`, `suspended_at` / `suspension_reason` / `suspended_by_id` (staff suspension; the reason is staff-only), `anonymised_at` (set when the member deleted the account), timestamps | Accounts for reviews and the forum; staff accounts for moderation | Until the member deletes the account (`DELETE /me`, §5). Reviews and forum content reference the user with `restrict` foreign keys and stay public, so deletion **anonymises the row in place** rather than removing it: every personal field above is cleared or replaced (see §5) and only `id`, `user_kind`, timestamps and `anonymised_at` remain. | Own account: `GET /me` (name, username and its change dates, email, role, avatar). Panel: `clients.view` / `clients.update` (members), `staff.*` (staff); renaming a username needs `usernames.manage`. **Public: `username` and avatar image only** — never the real name or initials (owner decision 2026-10-14). |
| `username_history` | `user_id` (nullable), a previous `username` with its folded forms, `reason` (`changed` by the member, `forced` by staff, `anonymised` on deletion), staff `note` and `changed_by_id` for a staff rename, `reserved_until`, `created_at` | Keeps a released username from being taken by someone else (impersonation) | Six months after release, then pruned daily (`model:prune`). On account deletion the rows are unlinked (`user_id` null, `note` cleared) and the current username is added, unlinked | Never public. The member's own rows (name, reason, note, dates; not who at staff) are in their export. Panel: the rename action writes them. |
| `username_terms` | Blocked and reserved username words (`term`, folded forms, `kind`, `language`, `match_type`, `category`, staff `note`, `active`) | Refusing offensive or impersonating usernames | Indefinite (configuration) | No personal data. Panel „Username rules“, `usernames.manage`. Seeded from `database/seeders/data/username_terms*.php` (LDNOOBW, CC BY 4.0 — docs/third-party-assets.md). |
| `reviews` | `user_id`, `rating`, `body` (free text about a doctor, facility or pharmacy — may describe treatment), `status`, `rejection_note`, `moderated_by_id`, timestamps | Public ratings of providers; moderation | Indefinite (no purge) | Public once approved: rating, body, author `username`, date. Pending/rejected: the author (`/me/reviews`) and panel `reviews.view`. |
| `forum_topics`, `forum_posts` | `user_id`, `title`/`body` (free text; health questions are the forum's purpose), `status`, `rejection_note`, `moderated_by_id`, timestamps. `forum_topics.community_rules_accepted_at`: when the author ticked the single consent (community rules, no diagnosis, call 194/112 in an emergency) while creating the topic; null for topics from before 2026-10-06 and for staff- or seeder-created topics | Community forum; moderation; record of the author's consent | Indefinite (no purge); the consent time lives and is deleted with its topic | Public once approved: title, body, author `username`, author's join date and post counts, staff/moderator badge. Pending: the author and `forum_topics.view` / `forum_posts.view` / `forum.moderate`. Consent time: panel `forum_topics.view` only (topic view page); never in the public or `/me` API. |
| `reviews` (right of reply, 2026-10-13) | `response_body` (the doctor's or facility's official reply, entered by staff), `response_by_id` (the staff member who posted it), `response_at`; `helpful_count` (a total, no personal data) | Right of reply (docs/notice-and-action.md) | As the review | Public on an approved review: body, the profile's name as responder, date — never `response_by_id`. Panel: `reviews.respond`. |
| `content_reports` | `user_id` (the reporter), reported item (review, forum topic or reply), `reason`, optional `note` (≤ 500, free text), `status`, `resolved_by_id`, `resolved_at`, `staff_alerted_at` (when staff were emailed about it), timestamps | Notice and action: member reports of published content (docs/notice-and-action.md); the reporter is emailed the outcome (kept/removed, no moderator name) | Indefinite (no purge). Kept when the reporter deletes the account (the anonymised row stays) but its `note` is cleared; deleted with the user row only if that is ever hard-deleted (cascade) | Never public; the author of the reported item is not told who reported it. Panel: the report queue (`content_reports.view` / `content_reports.resolve`). In the reporter's export. |
| `review_helpful_votes` | `review_id`, `user_id`, `created_at` — one „Корисно“ vote per member per review | The helpful count and sort | Until the member removes the vote; kept (unlinked from any personal data) when the account is anonymised; cascade-deleted with the review | Public only as `reviews.helpful_count`; a signed-in member sees their own `has_voted_helpful`. In the voter's export. |
| `forum_category_moderator` | `user_id` ↔ category | Scoped community-moderator rights | Until changed | Panel `clients.assign_roles` |
| `model_has_roles` / `model_has_permissions` (Spatie) | user ↔ role | Authorisation | Until changed | Panel `roles.*`, `clients.assign_roles` |
| `triage_sessions` | **No user link** (removed 2026-10-06). Session UUID, SHA-256 hash of a per-session secret, flow id, `terms_accepted_at`, `emergency_stopped`, `outcome_code`, timestamps | Symptom-guidance questionnaire state | **90 days**, then deleted by `triage:purge-old-sessions` (daily 03:15) *(configurable: `--days`)* | Not exposed in the panel. Via the API only to the holder of the session secret. |
| `triage_session_answers` | Structured answers (option codes only, no free text) per session | Rule-based guidance outcome | Deleted with their session (cascade) | As above |
| `search_term_daily` | **No user, session or time of day.** `date`, normalised search term (lower-cased, max 64 chars), `count` | "Top searches" on the admin dashboard | **365 days** *(configurable: `--search-days`)*, purged by `analytics:purge-old-events` (daily 03:45) | Panel `analytics.view` |
| `analytics_events` | `event` name, `user_id`, optional `properties`, `occurred_at`. Events still recorded **with** `user_id`: `user.login`, `user.registration_started`, `user.registered`, `review.submitted` (properties: reviewable type/id), `forum.topic_created`, `forum.post_created` (properties: topic/category ids). Searches are no longer events. | Dashboard counts (registrations, logins, contributions) | **180 days** *(configurable: `--days`)*, same purge command | Panel `analytics.view` sees aggregated counts only; rows are not listed in the UI |
| `personal_access_tokens` | Hashed API bearer tokens per user, `name` (device label: the web tier sends a coarse "browser · OS" such as „Chrome · macOS“, derived from the user agent — the user agent itself is not stored), `created_at`, `last_used_at`, `expires_at` | Web/mobile sign-in; the member's device list | Tokens expire after 30 days *(configurable: `SANCTUM_TOKEN_EXPIRATION_MINUTES`)*; expired rows are deleted daily at 04:15 by `sanctum:prune-expired --hours=24` (so at most a day after expiry). The member can revoke any of them sooner (§5); logout, password reset and account deletion delete them. | Own tokens: `GET /me/tokens` (name, created, last used, expiry, current flag). Not in the panel. |
| `password_reset_tokens` | Email, hashed reset token, `created_at` | Password reset | Token valid 60 minutes; rows are **not pruned** on a schedule (replaced on the next request for the same address) | Not exposed |
| `sessions` | `user_id`, IP address, user agent, session payload | Admin-panel (Filament) web sessions only; the public site uses API tokens | Lifetime 60 minutes *(configurable: `SESSION_LIFETIME`)*; expired rows removed by Laravel's session garbage collection (probabilistic) | Not exposed |
| `cache` / Redis | Rate-limiter keys derived from IP address and, for login lockout, email + IP; per-account limiter keys; mail cooldown keys | Abuse prevention | The limiter window: IP-keyed limits at most one hour (symptom guidance), most only a minute; per-account limits (reviews, forum, reports) up to a day | Not exposed |
| `jobs` / Redis queue | Queued mail jobs carry recipient address and name and the mail's content (e.g. review/topic title, rejection note) | Sending mail out of request | Until processed | Not exposed |
| `failed_jobs` | Payload of any job that failed — for mail jobs, as above | Retry/debugging; each failure also alerts the operators (`NotifyOnFailedJob`) | **30 days**: `queue:prune-failed --hours=720` runs daily at 04:30 | Server operators only |
| `site_settings`, directory tables (`doctors`, `facilities` — pharmacies included, …) | Doctors' professional profile data (name, specialties, workplace, photo) — public by design | Directory | Until edited | Public |

## 2. Data in the browser

| Where | What | Why |
|---|---|---|
| httpOnly cookie (web app's own domain) | The API bearer token | Signed-in session; the browser's scripts cannot read it |
| `localStorage` key `z360:recently-viewed:v1` | „Последно прегледани“: up to 8 recently opened doctor/facility/pharmacy profiles (kind, slug, name, subtitle, avatar URL, time viewed) | The home page's recently-viewed list; never sent to the API or tied to an account; cleared with „Исчисти“ on the home page or by clearing site data |
| `sessionStorage` key `guidance_session` | Guidance session id + its secret | Lets the tab continue a questionnaire after reload; cleared on completion and when the tab closes |

No other cookies or storage are set for analytics (Plausible is cookieless).

## 3. Processors and outbound flows

| Processor | Configured by | What it receives | Notes |
|---|---|---|---|
| **Plausible** (analytics; plausible.io or self-hosted) | `NEXT_PUBLIC_PLAUSIBLE_DOMAIN`, `…_SCRIPT_URL`, `…_HOST` (off when unset) | Pageviews with the page address cut to **origin + path** (no query string, no fragment); `document.referrer`; the browser's IP address and user agent as part of the HTTP request (Plausible states it uses these only to compute a daily rotating hash and does not store them) | Manual mode since 2026-10-06; the site refuses to load a non-manual script. With `Referrer-Policy: strict-origin` (also same-site), `document.referrer` carries only an origin, never a path or `?q=`. |
| **Sentry** (error reporting, API and web) | `SENTRY_DSN`, `NEXT_PUBLIC_SENTRY_DSN` (off when unset) | Exception details, stack traces, the request URL and (API) request body. `send_default_pii` is off on both sides (no cookies, client IP or sensitive headers). **API scrubber** (`SentryEventScrubber`): in the request body, query string and URL query it filters values under credential keys (password, token, secret, signature, API key, authorization, email, hash), the search term `q`, triage `answers`/`values`, and two-factor material (one-time codes under the login challenge and panel actions, recovery codes, the encrypted set-up secret, including inside the admin panel's Livewire component snapshot); in free text (exception messages, the event message, breadcrumb messages and metadata) it pattern-scrubs email addresses, password hashes, Sanctum tokens and long token-like strings. Other parameters (e.g. a specialty or city filter) can still appear in an API event. **Web scrubber** (`apps/web/src/lib/sentry-scrub.ts`, browser and server): drops the query string and fragment entirely — keeping origin + path — from the request URL, `query_string`, the Referer header and navigation/fetch breadcrumbs (`url`/`from`/`to`), so search terms, filters and reset tokens never leave; filters `password`/`token`/`email` keys in a request body. | Performance tracing off by default (`traces_sample_rate` 0) |
| **Meilisearch** (search engine; self-hosted) | `SCOUT_DRIVER=meilisearch`, `MEILISEARCH_HOST` | Public directory entries (doctors, facilities), forum categories, and **approved forum topics' title and body**; no author or user fields | Only content that is already public |
| **Mail provider** (SMTP) | `MAIL_*` | Recipient name and address; message content: verification and password-reset links, welcome mail, "account already exists" notice, review/topic submitted/approved/rejected/removed notices (content title, rejection note), report outcome to the reporter (content title, kept/removed), new-report alerts and the daily moderation digest to staff (counts only) | Transport only; whichever SMTP service the operator configures |
| **Object storage** (S3-compatible, e.g. Cloudflare R2) | `MEDIA_DISK=s3`, `AWS_*` | Uploaded images: member avatars, doctor/facility/pharmacy images, site branding | Production example sets `MEDIA_VISIBILITY=private`; avatars are shown publicly next to forum posts |
| Redis | `REDIS_*` | Queue payloads and cache (see §1) | Operator-hosted |
| **OpenStreetMap** (map tiles; third party, not a processor) | always on, facility profiles with coordinates | The visitor's browser loads the map iframe directly: OpenStreetMap gets the IP address and user agent, **no referrer** (`referrerPolicy="no-referrer"`), so not which profile was open | Disclosed in the privacy policy |

## 4. What was changed on 2026-10-06 (owner decisions)

1. Searches are kept only as anonymous daily term counts (`search_term_daily`);
   the previous raw `search.query` events (query + user id) were aggregated and
   deleted by migration.
2. Plausible no longer receives query strings.
3. Public surfaces show a chosen, unique `username`; `users.name` is private,
   and so are initials (the earlier „Име П.“ display name is retired).
   Accounts from before 2026-10-14 were given a temporary `clen-…` name and
   choose their own at the next sign-in.
4. Guidance sessions are never linked to an account; existing links were
   removed and the column dropped.

## 5. Data subject rights in the product

| Right | How | Notes |
|---|---|---|
| Access / portability | Account page → „Ваши податоци“ → download (`GET /me/export`, through the web tier so the token never reaches the browser) | One JSON file: profile (name, username, when it was last changed and the member's previous usernames still held back, the retired display name, the sign-up consent time and terms version, email, verification and sign-up time, avatar URL, roles, moderated categories), every review / forum topic / reply in any moderation state with its rejection note, the content reports the member filed (what was reported, reason, note, status and times — not who handled them), their „Корисно“ votes (review id and time), consents (the forum community-rules acceptance per topic, with its time), devices (token names and times) and the member's own `analytics_events`. Never another member's data: replies name another member's topic only while it is public; moderators are not identified. Limited to 5 per hour. |
| Erasure | Account page → delete, confirmed by re-entering the password (`DELETE /me`) | Anonymisation in place (`App\Actions\AnonymiseUser`): `name` emptied, `display_name` and `username` cleared (the username is held back from others for six months in an unlinked `username_history` row; earlier held names are unlinked too), `email` replaced with a unique `…@deleted.invalid` placeholder (the real address is free to register again), password replaced with an unusable random hash, `email_verified_at`, `remember_token`, second-factor secrets, suspension fields and `registration_contested_at` cleared, avatar file deleted from the media disk, all API tokens, roles, direct permissions and community-moderation scopes removed, `sessions` rows and the `password_reset_tokens` row for the old address deleted, and the account's `analytics_events` unlinked (`user_id` set to null). Published reviews and forum topics/replies **stay public** with the author shown as „Избришан корисник“ (no join date or post counts, no „Автор“ tag on replies, so a deleted author's posts cannot be linked to each other); **pending** reviews, topics and replies are withdrawn (rejected with a staff note, no mail) so they can never be published; the free-text `note` on the member's content reports is cleared (the reports and „Корисно“ votes themselves stay, linked only to the anonymised row). Staff accounts cannot delete themselves through the API; an administrator handles them. Mail already queued before deletion may still be delivered. |
| Restriction of sessions | Account page → „Уреди“ | Lists active sign-ins (device label, created, last used); the member can sign out one or all others. |
| Suspension (staff) | Admin panel → Clients → Suspend (permission `clients.suspend`, Administrator by default) | Reason required, visible to staff only; sign-in is refused with „Оваа сметка е привремено оневозможена…“ (code `auth.account_suspended`, never the reason) and existing tokens stop working until the suspension is lifted. Content is not touched. |

## 6. Open points for counsel / the owner

- Erasure keeps the member's public reviews and forum content (under „Избришан
  корисник“). Whether members must also be able to remove that content on
  request, and what to do with their pending or rejected items (kept today,
  including rejection notes), is a legal/owner decision.
- A suspended member cannot sign in, so cannot use self-service export or
  deletion; such requests go through an administrator (no panel action for
  anonymisation yet).
- `analytics_events` still links logins, registrations and contributions to a
  user id for 180 days (list in §1); deletion unlinks them.
- Password-reset rows are not pruned on a schedule.
- Reviews and forum posts are kept indefinitely, also after moderation
  rejection.
- API error events sent to Sentry can include non-credential request
  parameters such as a specialty or city filter; the search term `q` is
  filtered (§3).
