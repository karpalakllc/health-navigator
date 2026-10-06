# API contract (v1)

Base URL: `{API_URL}/api/v1` (e.g. `http://127.0.0.1:8000/api/v1`).

> **Maintenance:** the endpoint table below is generated — run
> `php artisan docs:route-table --write` (from `apps/api`), or
> `./scripts/api-routes.sh` (a wrapper that prints the same table) and paste
> the result. `RouteTableIsCurrentTest` fails when it is stale. Do not hand-edit it. This
> document previously drifted far enough to state, as a premise, that public
> registration did not exist, months after it shipped.

## Conventions

| | |
|--|--|
| Success (single) | `{ "data": { ... } }` or `{ "data": [ ... ] }` (non-paginated lists) |
| Success (paginated list) | `{ "data": [ ... ], "meta": { "current_page", "per_page", "total", "last_page" } }` |
| Error | `{ "message": "<localised>", "code": "<stable key>", "errors"?: { field: string[] } }` |
| Validation error | `422`, code `validation.failed`, `errors` populated per field; `message` is the first field error plus a localised "(and N more errors)" |
| Unauthorized | `401`, code `errors.unauthenticated` |
| Forbidden | `403`, code `errors.forbidden` (`message` is the policy's own denial text when it gives one) |
| Not found | `404`, code `errors.not_found` — including paths under `/api` that match no route |
| Method not allowed | `405`, code `errors.method_not_allowed`, with an `Allow` header |
| Too many requests | `429`, code `errors.too_many_requests`, with a `Retry-After` header |
| Server error | `500`, code `errors.server_error`; the exception message is never exposed (outside local debug mode) |
| Module disabled | `503`, code `module.unavailable` |
| Maintenance mode | `503`, code `maintenance.active` |

### Error codes

`code` is a stable machine-readable key that mirrors the translation key in
`apps/api/lang/{locale}/api.php`. Clients should branch on `code` and render
their own copy; `message` is a human-readable convenience and its wording may
change. Codes are part of the public contract — renaming one is a breaking
change.

### Language

Responses are localised. The API negotiates `Accept-Language` across `mk` and
`en`, defaulting to **Macedonian**, and sets `Content-Language` plus
`Vary: Accept-Language` on every response — error responses included, even for
a path that matches no route. The web client requests `mk`
explicitly because its UI is Macedonian-only.

## Authentication

**Strategy:** Laravel Sanctum **personal access tokens** (Bearer). Same
mechanism for the Next.js web client and future mobile clients.

| Channel | Mechanism |
|---------|-----------|
| API (`/api/v1/*`) | Bearer token; login does **not** start a web session |
| Filament (`/admin`) | Web session (separate from API tokens) |

- **Public registration is enabled**, gated by the `registrations_enabled`
  site setting (`403`, code `registration.disabled`, when off).
- **Registration is verify-then-activate and deliberately non-committal.**
  `POST /auth/register` always answers **`202`** with the same body, whether or
  not the address is already registered, and **never returns a token**. Which
  email the address owner receives is the only thing that differs (verification
  link vs. "you already have an account"). This is what stops signup being
  usable to discover who has an account.
- `GET /auth/email/verify/{id}/{hash}` is a **signed** link from that email. It
  redirects to `{FRONTEND_URL}/verify-email?status=verified|verified_set_password|already|invalid`;
  `verified_set_password` means the address was confirmed but the registration
  had been contested (signed up for more than once), so the stored password was
  discarded and a password-reset link was mailed instead. An unsigned or
  expired link is `403`.
- Completing `POST /auth/reset-password` also confirms the address (the reset
  link proves control of the mailbox), so it finishes a pending or contested
  registration.
- `POST /auth/email/resend` re-sends the link and is equally non-committal (202).
- **Login requires a verified address**: correct credentials on an unverified
  account return `403` with code `auth.email_unverified`. This is not an oracle —
  it is only reachable by someone who already knows the password.
- **Staff cannot sign in here.** Correct credentials for an account that holds
  `admin.access` (Administrator, Moderator — the admin panel requires them to
  enrol an authenticator) return `403` with code `auth.staff_use_admin`, and no
  token. The API has no second-factor step, so a token issued on the password
  alone would carry the account's moderation powers without the factor the
  panel demands. Checked before the verified-address rule; like it, only
  reachable with the right password. Two-factor protects the admin panel only:
  a community moderator who has opted in to an authenticator still signs in
  here with their password, and keeps their tokens when they enrol.
- **Existing tokens follow the same rule.** A token is refused (`401`, not
  deleted) for as long as its account holds `admin.access` — including one
  issued before the account was promoted — and works again after demotion,
  until it expires. Enrolling an authenticator deletes the existing tokens of
  an account that holds `admin.access`.
- Contributing endpoints (reviews, forum topics and replies, avatar upload)
  carry the `verified` guard and return the same `403` / `auth.email_unverified`.
- **Token expiration:** `SANCTUM_TOKEN_EXPIRATION_MINUTES`, default **43200**
  (30 days). Expired tokens are rejected — including on optional-auth routes.
- `POST /auth/forgot-password` always returns the same success payload,
  whether or not the address is registered.
- `GET /me` returns `id, name, display_name, email, role, community_roles,
  can_moderate_forum, avatar_url, avatar_initials, profile_avatar`.
- **Two names.** `name` is the person's real name and is **private** (only
  `/me`, the admin panel and mail). `display_name` is what everything public
  shows: `author_name` on reviews and forum topics/posts, and `author.name` on
  forum authors. `POST /auth/register` requires `display_name`;
  `PATCH /me/profile` (`{ "display_name": "…" }`, verified accounts) changes it
  and returns `{ user }` as `/me` does. Rules: trimmed with runs of whitespace
  collapsed, at most 40 characters, Unicode letters, spaces and `. - '` only,
  starting with a letter, at least two letters, no word mixing Cyrillic and
  Latin, and no title, role or platform name in either script (д-р/dr, проф,
  доктор, админ…, модератор, тим/team, поддршка/support, здравје, официјал —
  `DisplayName::rejection()`). **Not unique.**

**Account data rights and devices (D5, D6, D7):**

- **Suspension.** Staff with `clients.suspend` can suspend a client account
  in the admin panel (reason required, staff-only). Correct credentials for a
  suspended account return `403` with code `auth.account_suspended` and no
  token — like the rules above, only reachable with the right password. Its
  existing tokens are refused (`401`, not deleted) from the next request on,
  including on optional-auth routes, and work again once the suspension is
  lifted. Its content is not touched.
- `GET /me/export` downloads the caller's own data as one JSON attachment
  (`format: zdravje360.account-export`, `version: 1`): `profile`, `reviews`,
  `forum_topics`, `forum_posts`, `consents` (forum community-rules acceptance
  per topic, with time), `content_reports` (the caller's own reports: what,
  reason, note, status and times — never who resolved them), `helpful_votes`
  (the reviews the caller marked „Корисно“), `devices` and `activity` (the
  caller's own analytics events). Nothing about other members: replies name another member's topic
  only while it is approved. `Cache-Control: no-store`. 5 per hour
  (`429`, code `account.export_throttled`).
- `DELETE /me` with `{ "password": "…" }` deletes the account by
  anonymisation: name, display name, email (replaced with a non-deliverable
  placeholder, so the address can register again), password, avatar (file
  removed), roles, community-moderation scopes, tokens, panel sessions and
  reset links are cleared; published reviews and forum content stay public
  with the author shown as `Избришан корисник` (`author.member_since` `null`,
  counts `0`, `is_topic_author` `false`); pending reviews, topics and replies
  are withdrawn (rejected, no mail); the notes on the member's reports are
  cleared. Wrong password: `422` on `password`. Staff accounts: `403`, code
  `account.staff_cannot_delete`. 5 attempts per hour.
- `GET /me/tokens` lists the caller's active tokens (`id, name, created_at,
  last_used_at, expires_at, is_current`), current first. `name` is the login's
  `device_name` (the web tier sends a coarse "browser · OS" label).
  `DELETE /me/tokens/{id}` revokes one (another member's id is `404`, like an
  unknown one); `DELETE /me/tokens` revokes all but the current one and
  returns `revoked`.

**Web client:** Next.js stores the bearer token in an httpOnly cookie via route
handlers under `/api/session/*`; the browser never reads the token. Mobile uses
the bearer token directly.

## Rate limits

A baseline `throttle:api` applies to every v1 route: **300 requests/minute** per
authenticated user, **1200/minute** per client IP otherwise (high on purpose — the
web tier's server-side rendering arrives from one address). The tighter named
limiters are layered on top:

| Limiter | Applies to | Limit |
|---------|-----------|-------|
| `api-login` | login, register, forgot/reset password, email verify | 40/min per IP |
| `api-verification-resend` | verification email resend | 10/min per IP |
| `api-profile` | `PATCH /me/profile` (display name) | 10/hour per user |
| `api-account-export` | `GET /me/export` | 5/hour per user |
| `api-account-delete` | `DELETE /me` (password re-entry) | 5/hour per user |
| `api-reviews` | review submission | 10/hour, 20/day |
| `api-forum-topics` | topic creation | 5/day |
| `api-forum-posts` | reply creation | 30/day |
| `api-reports-burst` / `api-reports-daily` | content reports (inline `throttle:` with a prefix) | 10 per 10 min, 40/day per user |
| `api-review-helpful` | „Корисно“ on/off (inline `throttle:` with a prefix) | 60 per 10 min per user |
| `api-triage-sessions` | guidance session create/answer/emergency | 10/hour |
| `api-triage-complete` | guidance completion | 5/hour |

Login additionally locks an account out after **5 failed attempts per minute**,
counted per email + IP (`AuthController`). It counts failures, not requests, so
nobody can hold an account locked by merely sending traffic.

> IP-keyed limits require `TRUSTED_PROXIES` to be set behind a load balancer,
> or every client shares one bucket. See `infra/deploy.md`.

## Endpoints

<!-- BEGIN generated route table -->
| Method | Path | Guards |
|--------|------|--------|
| `DELETE` | `/me` | `auth:sanctum`, `throttle:api-account-delete` |
| `DELETE` | `/me/doctor/change-requests/{changeRequest}` | `auth:sanctum`, `verified`, `throttle:120,1,api-doctor-dashboard`, `throttle:60,60,api-doctor-dashboard-writes` |
| `DELETE` | `/me/doctor/reviews/{review}/reply` | `auth:sanctum`, `verified`, `throttle:120,1,api-doctor-dashboard`, `throttle:60,60,api-doctor-dashboard-writes` |
| `DELETE` | `/me/tokens` | `auth:sanctum` |
| `DELETE` | `/me/tokens/{token}` | `auth:sanctum` |
| `DELETE` | `/reviews/{review}/helpful` | `auth:sanctum`, `verified`, `can:create,App\Models\Review`, `throttle:60,10,api-review-helpful` |
| `GET` | `/auth/email/verify/{id}/{hash}` | `signed`, `throttle:api-login` |
| `GET` | `/departments` | `cache.public` |
| `GET` | `/doctors` | `cache.public:60` |
| `GET` | `/doctors/{slug}` | `cache.public:60` |
| `GET` | `/doctors/{slug}/reviews` | `auth.sanctum.optional` |
| `GET` | `/facilities` | `cache.public:60` |
| `GET` | `/facilities/{slug}` | `cache.public:60` |
| `GET` | `/facilities/{slug}/reviews` | `auth.sanctum.optional` |
| `GET` | `/forum/categories` | `module:forum`, `cache.public` |
| `GET` | `/forum/categories/{category}/topics` | `module:forum` |
| `GET` | `/forum/categories/{category}/topics/{topic}` | `module:forum`, `auth.sanctum.optional` |
| `GET` | `/forum/topics` | `module:forum` |
| `GET` | `/forum/topics/recent` | `module:forum` |
| `GET` | `/health` | — |
| `GET` | `/home/highlights` | `cache.public` |
| `GET` | `/languages` | `cache.public` |
| `GET` | `/me` | `auth:sanctum` |
| `GET` | `/me/doctor` | `auth:sanctum`, `verified`, `throttle:120,1,api-doctor-dashboard` |
| `GET` | `/me/doctor/reviews` | `auth:sanctum`, `verified`, `throttle:120,1,api-doctor-dashboard` |
| `GET` | `/me/export` | `auth:sanctum`, `throttle:api-account-export` |
| `GET` | `/me/forum/posts` | `auth:sanctum` |
| `GET` | `/me/forum/topics` | `auth:sanctum` |
| `GET` | `/me/reviews` | `auth:sanctum` |
| `GET` | `/me/tokens` | `auth:sanctum` |
| `GET` | `/pharmacies` | `module:pharmacies`, `cache.public:60` |
| `GET` | `/pharmacies/{slug}` | `module:pharmacies`, `cache.public:60` |
| `GET` | `/pharmacies/{slug}/products` | `module:pharmacies`, `cache.public:60` |
| `GET` | `/pharmacies/{slug}/reviews` | `module:pharmacies`, `auth.sanctum.optional` |
| `GET` | `/products` | `module:products`, `cache.public:60` |
| `GET` | `/products/{slug}` | `module:products`, `cache.public:60` |
| `GET` | `/search` | — |
| `GET` | `/settings/public` | — |
| `GET` | `/specialties` | `cache.public` |
| `GET` | `/specialties/{slug}` | `cache.public` |
| `GET` | `/triage/flow` | `module:guidance` |
| `PATCH` | `/forum/categories/{category}/topics/{topic}/moderation` | `auth:sanctum`, `module:forum` |
| `PATCH` | `/me/doctor` | `auth:sanctum`, `verified`, `throttle:120,1,api-doctor-dashboard`, `throttle:60,60,api-doctor-dashboard-writes` |
| `PATCH` | `/me/profile` | `auth:sanctum`, `verified`, `throttle:api-profile` |
| `POST` | `/auth/email/resend` | `throttle:api-verification-resend` |
| `POST` | `/auth/forgot-password` | `throttle:api-login` |
| `POST` | `/auth/login` | `throttle:api-login` |
| `POST` | `/auth/logout` | `auth:sanctum` |
| `POST` | `/auth/register` | `registrations`, `throttle:api-login` |
| `POST` | `/auth/reset-password` | `throttle:api-login` |
| `POST` | `/doctors/{slug}/claim-requests` | `auth:sanctum`, `verified`, `throttle:5,1440,api-doctor-claims` |
| `POST` | `/doctors/{slug}/reviews` | `auth:sanctum`, `can:create,App\Models\Review`, `verified`, `throttle:api-reviews` |
| `POST` | `/facilities/{slug}/reviews` | `auth:sanctum`, `can:create,App\Models\Review`, `verified`, `throttle:api-reviews` |
| `POST` | `/forum/categories/{category}/topics` | `auth:sanctum`, `module:forum`, `can:create,App\Models\ForumTopic`, `verified`, `throttle:api-forum-topics` |
| `POST` | `/forum/categories/{category}/topics/{topic}/posts` | `auth:sanctum`, `module:forum`, `can:create,App\Models\ForumPost`, `verified`, `throttle:api-forum-posts` |
| `POST` | `/forum/categories/{category}/topics/{topic}/reports` | `auth:sanctum`, `verified`, `throttle:10,10,api-reports-burst`, `throttle:40,1440,api-reports-daily`, `module:forum` |
| `POST` | `/forum/posts/{post}/reports` | `auth:sanctum`, `verified`, `throttle:10,10,api-reports-burst`, `throttle:40,1440,api-reports-daily`, `module:forum` |
| `POST` | `/me/avatar` | `auth:sanctum`, `verified` |
| `POST` | `/me/doctor/avatar` | `auth:sanctum`, `verified`, `throttle:120,1,api-doctor-dashboard`, `throttle:60,60,api-doctor-dashboard-writes` |
| `POST` | `/me/doctor/change-requests` | `auth:sanctum`, `verified`, `throttle:120,1,api-doctor-dashboard`, `throttle:60,60,api-doctor-dashboard-writes` |
| `POST` | `/pharmacies/{slug}/reviews` | `auth:sanctum`, `module:pharmacies`, `can:create,App\Models\Review`, `verified`, `throttle:api-reviews` |
| `POST` | `/reviews/{review}/reports` | `auth:sanctum`, `verified`, `throttle:10,10,api-reports-burst`, `throttle:40,1440,api-reports-daily` |
| `POST` | `/triage/sessions` | `module:guidance`, `throttle:api-triage-sessions` |
| `POST` | `/triage/sessions/{id}/complete` | `module:guidance`, `throttle:api-triage-complete` |
| `POST` | `/triage/sessions/{id}/emergency` | `module:guidance`, `throttle:api-triage-sessions` |
| `PUT` | `/me/doctor/reviews/{review}/reply` | `auth:sanctum`, `verified`, `throttle:120,1,api-doctor-dashboard`, `throttle:60,60,api-doctor-dashboard-writes` |
| `PUT` | `/reviews/{review}/helpful` | `auth:sanctum`, `verified`, `can:create,App\Models\Review`, `throttle:60,10,api-review-helpful` |
| `PUT` | `/triage/sessions/{id}/answers` | `module:guidance`, `throttle:api-triage-sessions` |
<!-- END generated route table -->

### Notes

- `per_page` is capped at **50** on every list endpoint (`/search` caps at 10
  per vertical). `page` starts at 1.
- List `q` filters match **name/title only**, except doctors (name or a
  published specialty name) and clinical facilities (name or a published
  department name), on `/search` too; `city` is a separate parameter.
  Minimum query length is 2 characters, matching `SearchQuery::normalize`.
- Doctor, facility and pharmacy lists put `is_featured` items **first**, inside
  the current filters, then apply the chosen sort (`/doctors?sort=name|rating`;
  facilities and pharmacies sort by name); totals and pages are unaffected.
  `/search` does the same per vertical on the SQL path; with Meilisearch,
  `is_featured` is a sortable attribute applied after relevance, so it only
  breaks ties between equally relevant hits (needs `php artisan
  search:reindex` once to push the setting). Every item carries `is_featured`;
  doctors also carry `is_sponsored`, which must stay visibly labelled.
- Images are URLs or `null`: doctors `avatar_url` (photo); facilities and
  pharmacies `avatar_url` (logo) and `cover_url` (wide header, WebP, at most
  1600×900), on both list and detail payloads.
- `GET /home/highlights` feeds the home page in one call: `specialties`
  (up to 8 published specialties with at least one published doctor, by
  `doctors_count`), `cities` (up to 12 `{name, doctors_count}`, published
  doctors only, because each links to `/doctors?city=`; Latin and Cyrillic
  spellings of one city are merged), and `recent_reviews` (up to 4 approved
  reviews with a body, newest `published_at` first, of published doctors,
  clinical facilities and — while that module is on — pharmacies: `id`,
  `rating`, `excerpt` ≤160 characters, `author_name` (the public display
  name), `published_at`, `target {kind, slug, name}`).
  Staleness, end to end: the API's own copy is dropped on any doctor,
  specialty, facility or review save (approving, rejecting, unpublishing,
  deleting) and otherwise expires after 5 minutes; the pharmacies switch is
  part of its key. The web tier re-reads it at most every 60 seconds (Next
  data cache), so a moderation or profile change reaches the home page within
  about a minute. A member's display-name change saves none of those models,
  so it can take up to the API's 5 minutes plus that minute. Other HTTP
  clients may also keep the `public, max-age=300` response for 5 minutes.
- Doctor, facility and pharmacy **list** items (and `/search` sections) carry
  `phone` (string or `null`) and `office_hours` (day-label → hours map, `[]`
  when unset), the same fields as the profiles.
- `/doctors?language=<slug>` filters on the doctor's languages. The slug must
  be a published language (`GET /languages`, which lists only languages some
  published doctor speaks, with `doctors_count`); anything else is **422**.
- `cache.public` routes answer `Cache-Control: public, max-age=…` with a
  content `ETag`, and a matching `If-None-Match` gets a body-less **304**.
  Taxonomies use 300 s; directory and product lists/profiles use 60 s
  (`cache.public:60`). Only anonymous 200s are marked: a request with an
  `Authorization` header gets the default `no-cache, private`, and the public
  answer carries `Vary: Authorization, Accept-Language`. Review lists and
  `/search` are never marked.
- Review lists accept `sort` (`newest|oldest|rating_high|rating_low|helpful`) and
  `rating` (1–5), and return `meta.viewer_review` when the caller has one.
  `meta.rating_counts` is `{"1": n, …, "5": n}` over **approved** reviews of the
  profile, independent of the `rating` filter and the page. Each review carries
  `helpful_count`, `response` (`null`, or `{body, responder_name, responded_at,
  source}`: the doctor's or facility's reply, plain text; `source` is `staff`
  when staff entered it on the profile's behalf, `doctor` when the linked
  doctor wrote it — shown only once approved) and, **only on a
  signed-in request**, `viewer.has_voted_helpful`; anonymous payloads carry no
  viewer state.
- `PUT`/`DELETE /reviews/{id}/helpful` mark and unmark a published review as
  helpful (Member role, verified; idempotent; your own review is a 422) and
  return `{helpful_count, has_voted_helpful}`.
- Reports: `POST /reviews/{id}/reports`, `POST /forum/posts/{id}/reports` and
  `POST /forum/categories/{category}/topics/{topic}/reports` take `reason`
  (`spam|abuse|false_information|personal_data|other`) and an optional `note`
  (≤ 500). Only publicly visible content can be reported (otherwise 404; a
  pharmacy review while the pharmacies module is off is not public, for
  „Корисно“ too). Your own review, topic or reply is a 422 (`content`). The
  first report answers 201, a repeat by the same account 200 with the same body
  and no new row. Process: [notice-and-action.md](./notice-and-action.md).
- Forum replies carry `is_topic_author` (written by the topic's opener; never
  for a deleted account); the payload never includes account ids. On a
  signed-in request the topic and each reply carry `viewer.is_own` (the web
  hides „Пријави“ on it), and the topic's `viewer.can_moderate` is present only
  when true; anonymous payloads carry no `viewer`.
- **Doctor accounts („Мој профил“).** Staff link one member account to one
  doctor profile (admin panel). `GET /me` then carries
  `user.managed_doctor` (`{slug, full_name, is_published}`, else `null`).
  Everything under `/me/doctor` (verified accounts) resolves the profile from
  the account, never from the URL; an unlinked, suspended or deleted account
  gets **404** `doctor_account.not_linked`.
  `GET /me/doctor` returns `doctor` (current values; `specialties` and
  `facilities` as `{id, name, is_primary}`, `language_ids`,
  `clinical_interest_ids`, `procedure_ids`), `pending_change_request`,
  `recent_change_requests`, `stats {review_count, average_rating,
  unanswered_reviews, pending_replies}`, `settings.replies_require_moderation`
  and the `options` the selects offer. `PATCH /me/doctor` saves `bio`, `phone`,
  `email`, `consultation_fee_note`, `accepts_new_patients`, `office_hours`
  (day → hours), `language_ids`, `clinical_interest_ids`, `procedure_ids`
  at once (plain text; any other key is ignored — slug, publication,
  featured and sponsored are never the doctor's). `POST /me/doctor/avatar`
  (multipart `avatar`, ≤ 5 MB) re-encodes the photo to WebP.
  `POST /me/doctor/change-requests` takes `full_name`, `title`,
  `subspecialty`, `education`, `years_experience`, `city`, `specialty_ids` /
  `primary_specialty_id`, `facility_ids` / `primary_facility_id` and an
  optional `message`; it stores the field-level diff `{field: {old, new}}`
  for staff (201), 422 `doctor_account.no_changes` when nothing differs, 409
  `doctor_account.change_request_pending` while one waits. `DELETE
  /me/doctor/change-requests/{id}` withdraws a pending one.
  `GET /me/doctor/reviews?filter=all|unanswered` lists the profile's approved
  reviews (public author name only) with `reply {body, source, status,
  responded_at, rejection_note}` and `can_reply`. `PUT
  /me/doctor/reviews/{id}/reply` (`body`, 2–2000, plain text) writes or
  replaces the doctor's one reply — `pending` until staff approve while
  `doctor_replies_require_moderation` is on (default), and an edit waits
  again; 409 `doctor_account.reply_staff_exists` under a staff-entered
  response. `DELETE` removes the doctor's own reply. A review of another
  profile is 404. The linked doctor cannot review their own profile (422
  `review`).
- `POST /doctors/{slug}/claim-requests` („Ова е мој профил“; verified member)
  takes `message` (10–1000) and `contact` (5–255). 201 on the first request,
  200 for a repeat while it is pending; 409 `doctor_account.claim_taken`,
  `claim_already_yours` or `claim_already_manager`; 429
  `doctor_account.claim_limit` beyond 3 open requests. Staff verify the person
  outside the platform and assign the account in the admin panel.
- `GET /health` returns `data.status` of `ok` (200) or `degraded` (503) with a
  `checks` map. It is **exempt from maintenance mode**, so a 503 there always
  means real degradation.
- Forum content may be created already-approved when the author holds
  `forum.moderate` or the corresponding moderation setting is off; otherwise it
  is `pending`.
- Replying to a locked topic returns **422**, not 403.
- Guidance sessions are **never linked to an account**, even when the caller
  is signed in. `POST /triage/sessions` returns `session_id` and a secret
  `session_token` (only its SHA-256 is stored); every later call on the session
  (`answers`, `emergency`, `complete`) must send it as the
  `X-Guidance-Token` header. A missing or wrong token is `404`, the same as an
  unknown id.
- `/search` does not record who searched. The normalised query (lower-cased,
  whitespace collapsed, truncated to 64 characters) is counted per day in
  `search_term_daily` after the response is sent; there is no per-search row.

## Roles and permissions

Authorization uses **Spatie roles and permissions** only. Built-in roles:
`Administrator`, `Moderator` (staff), `Forum Moderator` (community) and
`Member`.

- **Member** carries `reviews.create` and `forum.post`; registration assigns
  it. The contribution endpoints check those permissions (`can:create` on the
  Review / ForumTopic / ForumPost policies), so removing the role stops an
  account posting, and granting it lets a staff account post. Staff do not
  hold it by default.
- The `role` field on `GET /me` (`member`, `moderator`, `admin`) is derived for
  display: `admin` for the Administrator role, `moderator` for other staff,
  `member` otherwise. `community_roles` lists every Spatie role name held
  (including `Member`). Neither is an authorization input.
- `users.user_kind` (`staff` / `client`) only decides whether an account is
  managed under Staff or Clients in the admin panel. The legacy `users.role`
  column was dropped in 2026_10_13_100000.
- Staff moderators and admins moderate through the Filament panel and the
  public moderation endpoint.
- **Community moderators** are client accounts holding the `Forum Moderator`
  role, optionally scoped to specific categories via `forum_category_moderator`.
  A scoped moderator is refused outside their categories, on both the API and
  the admin panel.

- `doctors.assign_owner` (link or unlink a doctor's account, handle profile
  claims) and `audit.view` (the admin activity log) are held by the
  Administrator only by default. Deciding a doctor's change request needs
  `doctors.update`; moderating a doctor's reply needs `reviews.respond`.

See [community-moderator-onboarding.md](./community-moderator-onboarding.md).

## Not in this contract yet

- Meilisearch is wired behind `SCOUT_DRIVER`; `/search` falls back to SQL.
- AI-assisted triage (gated — see [triage-safety.md](./triage-safety.md)).
- Sponsorships, mobile-specific endpoints (token refresh, device registry,
  push), cursor pagination, and a generated OpenAPI document.
