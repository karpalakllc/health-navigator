# Personal data inventory

Factual inventory of where the platform holds or sends personal data, written
for legal counsel reviewing the privacy notice
(`apps/web/src/content/legal/privacy.tsx`, which this document does **not**
change). It describes the code as of 2026-10-06, including the privacy-by-default
changes of that date. Where something is *not* done (no deletion endpoint, no
pruning), that is stated rather than implied.

Defaults quoted below are the shipped defaults; an operator can change the ones
marked *(configurable)*.

## 1. Database tables

"Access" names the admin-panel (Filament, `/admin`) permissions that expose the
data; staff panel access requires a second factor (authenticator app). The
public API exposes only what is listed under "Public".

| Table | Personal data held | Purpose | Retention | Access |
|---|---|---|---|---|
| `users` | `name` (real name, **private**), `display_name` (public name the person chooses; default "first name + last initial."), `email`, `password` (bcrypt hash), `email_verified_at`, `registration_contested_at`, `avatar_path` (uploaded photo), `role`/`user_kind`, `app_authentication_secret` and `app_authentication_recovery_codes` (staff second factor; encrypted with `APP_KEY`, codes also hashed), `remember_token`, timestamps | Accounts for reviews and the forum; staff accounts for moderation | Until the account is deleted. **There is no self-service account deletion or data-export endpoint.** Reviews and forum content reference the user with `restrict` foreign keys, so a user with content cannot be deleted without first deleting the content. | Own account: `GET /me` (name, display name, email, role, avatar). Panel: `clients.view` / `clients.update` (members), `staff.*` (staff). **Public: `display_name` and avatar image only.** |
| `reviews` | `user_id`, `rating`, `body` (free text about a doctor, facility or pharmacy — may describe treatment), `status`, `rejection_note`, `moderated_by_id`, timestamps | Public ratings of providers; moderation | Indefinite (no purge) | Public once approved: rating, body, `display_name`, date. Pending/rejected: the author (`/me/reviews`) and panel `reviews.view`. |
| `forum_topics`, `forum_posts` | `user_id`, `title`/`body` (free text; health questions are the forum's purpose), `status`, `rejection_note`, `moderated_by_id`, timestamps. `forum_topics.community_rules_accepted_at`: when the author ticked the single consent (community rules, no diagnosis, call 194/112 in an emergency) while creating the topic; null for topics from before 2026-10-06 and for staff- or seeder-created topics | Community forum; moderation; record of the author's consent | Indefinite (no purge); the consent time lives and is deleted with its topic | Public once approved: title, body, author `display_name`, author's join date and post counts, staff/moderator badge. Pending: the author and `forum_topics.view` / `forum_posts.view` / `forum.moderate`. Consent time: panel `forum_topics.view` only (topic view page); never in the public or `/me` API. |
| `forum_category_moderator` | `user_id` ↔ category | Scoped community-moderator rights | Until changed | Panel `clients.assign_roles` |
| `model_has_roles` / `model_has_permissions` (Spatie) | user ↔ role | Authorisation | Until changed | Panel `roles.*`, `clients.assign_roles` |
| `triage_sessions` | **No user link** (removed 2026-10-06). Session UUID, SHA-256 hash of a per-session secret, flow id, `terms_accepted_at`, `emergency_stopped`, `outcome_code`, timestamps | Symptom-guidance questionnaire state | **90 days**, then deleted by `triage:purge-old-sessions` (daily 03:15) *(configurable: `--days`)* | Not exposed in the panel. Via the API only to the holder of the session secret. |
| `triage_session_answers` | Structured answers (option codes only, no free text) per session | Rule-based guidance outcome | Deleted with their session (cascade) | As above |
| `search_term_daily` | **No user, session or time of day.** `date`, normalised search term (lower-cased, max 64 chars), `count` | "Top searches" on the admin dashboard | **365 days** *(configurable: `--search-days`)*, purged by `analytics:purge-old-events` (daily 03:45) | Panel `analytics.view` |
| `analytics_events` | `event` name, `user_id`, optional `properties`, `occurred_at`. Events still recorded **with** `user_id`: `user.login`, `user.registration_started`, `user.registered`, `review.submitted` (properties: reviewable type/id), `forum.topic_created`, `forum.post_created` (properties: topic/category ids). Searches are no longer events. | Dashboard counts (registrations, logins, contributions) | **180 days** *(configurable: `--days`)*, same purge command | Panel `analytics.view` sees aggregated counts only; rows are not listed in the UI |
| `personal_access_tokens` | Hashed API bearer tokens per user, `last_used_at`, `expires_at` | Web/mobile sign-in | Tokens expire after 30 days *(configurable: `SANCTUM_TOKEN_EXPIRATION_MINUTES`)* but expired rows are **not pruned** (no scheduled `sanctum:prune-expired`) | Not exposed |
| `password_reset_tokens` | Email, hashed reset token, `created_at` | Password reset | Token valid 60 minutes; rows are **not pruned** on a schedule (replaced on the next request for the same address) | Not exposed |
| `sessions` | `user_id`, IP address, user agent, session payload | Admin-panel (Filament) web sessions only; the public site uses API tokens | Lifetime 60 minutes *(configurable: `SESSION_LIFETIME`)*; expired rows removed by Laravel's session garbage collection (probabilistic) | Not exposed |
| `cache` / Redis | Rate-limiter keys derived from IP address and, for login lockout, email + IP; mail cooldown keys | Abuse prevention | Minutes (limiter windows) | Not exposed |
| `jobs` / Redis queue | Queued mail jobs carry recipient address and name and the mail's content (e.g. review/topic title, rejection note) | Sending mail out of request | Until processed | Not exposed |
| `failed_jobs` | Payload of any job that failed — for mail jobs, as above | Retry/debugging | **Indefinite** (no scheduled `queue:prune-failed`) | Server operators only |
| `site_settings`, directory tables (`doctors`, `facilities` — pharmacies included, …) | Doctors' professional profile data (name, specialties, workplace, photo) — public by design | Directory | Until edited | Public |

## 2. Data in the browser

| Where | What | Why |
|---|---|---|
| httpOnly cookie (web app's own domain) | The API bearer token | Signed-in session; the browser's scripts cannot read it |
| `sessionStorage` key `guidance_session` | Guidance session id + its secret | Lets the tab continue a questionnaire after reload; cleared on completion and when the tab closes |

No other cookies or storage are set for analytics (Plausible is cookieless).

## 3. Processors and outbound flows

| Processor | Configured by | What it receives | Notes |
|---|---|---|---|
| **Plausible** (analytics; plausible.io or self-hosted) | `NEXT_PUBLIC_PLAUSIBLE_DOMAIN`, `…_SCRIPT_URL`, `…_HOST` (off when unset) | Pageviews with the page address cut to **origin + path** (no query string, no fragment); `document.referrer`; the browser's IP address and user agent as part of the HTTP request (Plausible states it uses these only to compute a daily rotating hash and does not store them) | Manual mode since 2026-10-06; the site refuses to load a non-manual script. Referrer caveat: with the current `Referrer-Policy: strict-origin-when-cross-origin`, a full same-site address (including `?q=`) can appear as referrer after a full page load from such a page; client-side navigations do not change it. |
| **Sentry** (error reporting, API and web) | `SENTRY_DSN`, `NEXT_PUBLIC_SENTRY_DSN` (off when unset) | Exception details, stack traces, the request URL and (API) request body. `send_default_pii` is off on both sides (no cookies, client IP or sensitive headers). **API scrubber** (`SentryEventScrubber`): in the request body, query string and URL query it filters values under credential keys (password, token, secret, signature, API key, authorization, email, hash), the search term `q`, triage `answers`/`values`, and two-factor material (one-time codes under the login challenge and panel actions, recovery codes, the encrypted set-up secret, including inside the admin panel's Livewire component snapshot); in free text (exception messages, the event message, breadcrumb messages and metadata) it pattern-scrubs email addresses, password hashes, Sanctum tokens and long token-like strings. Other parameters (e.g. a specialty or city filter) can still appear in an API event. **Web scrubber** (`apps/web/src/lib/sentry-scrub.ts`, browser and server): drops the query string and fragment entirely — keeping origin + path — from the request URL, `query_string`, the Referer header and navigation/fetch breadcrumbs (`url`/`from`/`to`), so search terms, filters and reset tokens never leave; filters `password`/`token`/`email` keys in a request body. | Performance tracing off by default (`traces_sample_rate` 0) |
| **Meilisearch** (search engine; self-hosted) | `SCOUT_DRIVER=meilisearch`, `MEILISEARCH_HOST` | Public directory entries (doctors, facilities), forum categories, and **approved forum topics' title and body**; no author or user fields | Only content that is already public |
| **Mail provider** (SMTP) | `MAIL_*` | Recipient name and address; message content: verification and password-reset links, welcome mail, "account already exists" notice, review/topic submitted/approved/rejected notices (content title, rejection note), daily moderation digest to staff (queue counts) | Transport only; whichever SMTP service the operator configures |
| **Object storage** (S3-compatible, e.g. Cloudflare R2) | `MEDIA_DISK=s3`, `AWS_*` | Uploaded images: member avatars, doctor/facility/pharmacy images, site branding | Production example sets `MEDIA_VISIBILITY=private`; avatars are shown publicly next to forum posts |
| Redis | `REDIS_*` | Queue payloads and cache (see §1) | Operator-hosted |

## 4. What was changed on 2026-10-06 (owner decisions)

1. Searches are kept only as anonymous daily term counts (`search_term_daily`);
   the previous raw `search.query` events (query + user id) were aggregated and
   deleted by migration.
2. Plausible no longer receives query strings.
3. Public surfaces show a chosen `display_name`; `users.name` is private.
   Existing accounts were given "first name + last initial."
4. Guidance sessions are never linked to an account; existing links were
   removed and the column dropped.

## 5. Open points for counsel / the owner

- No self-service account deletion or export of one's own data.
- `analytics_events` still links logins, registrations and contributions to a
  user id for 180 days (list in §1).
- Expired API tokens, password-reset rows and failed jobs are not pruned on a
  schedule.
- Reviews and forum posts are kept indefinitely, also after moderation
  rejection.
- Referrer caveat for Plausible (§3).
- API error events sent to Sentry can include non-credential request
  parameters such as a search query (§3).
