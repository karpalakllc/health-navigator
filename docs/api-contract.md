# API contract (v1)

Base URL: `{API_URL}/api/v1` (e.g. `http://127.0.0.1:8000/api/v1`).

> **Maintenance:** the endpoint table below is generated — run
> `./scripts/api-routes.sh` and paste the result. Do not hand-edit it. This
> document previously drifted far enough to state, as a premise, that public
> registration did not exist, months after it shipped.

## Conventions

| | |
|--|--|
| Success (single) | `{ "data": { ... } }` or `{ "data": [ ... ] }` (non-paginated lists) |
| Success (paginated list) | `{ "data": [ ... ], "meta": { "current_page", "per_page", "total", "last_page" } }` |
| Error | `{ "message": "<localised>", "code": "<stable key>", "errors"?: { field: string[] } }` |
| Validation error | `422` with `errors` populated (Laravel validation) |
| Unauthorized | `401`, code `errors.unauthenticated` |
| Forbidden | `403`, code `errors.forbidden` |
| Not found | `404`, code `errors.not_found` |
| Too many requests | `429`, code `errors.too_many_requests` |
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
`Vary: Accept-Language` on every response. The web client requests `mk`
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
  redirects to `{FRONTEND_URL}/verify-email?status=verified|already|invalid`;
  an unsigned or expired link is `403`.
- `POST /auth/email/resend` re-sends the link and is equally non-committal (202).
- **Login requires a verified address**: correct credentials on an unverified
  account return `403` with code `auth.email_unverified`. This is not an oracle —
  it is only reachable by someone who already knows the password.
- Contributing endpoints (reviews, forum topics and replies, avatar upload)
  carry the `verified` guard and return the same `403` / `auth.email_unverified`.
- **Token expiration:** `SANCTUM_TOKEN_EXPIRATION_MINUTES`, default **43200**
  (30 days). Expired tokens are rejected — including on optional-auth routes.
- `POST /auth/forgot-password` always returns the same success payload,
  whether or not the address is registered.
- `GET /me` returns `id, name, email, role, community_roles,
  can_moderate_forum, avatar_url, avatar_initials, profile_avatar`.

**Web client:** Next.js stores the bearer token in an httpOnly cookie via route
handlers under `/api/session/*`; the browser never reads the token. Mobile uses
the bearer token directly.

## Rate limits

A baseline `throttle:api` of **120 requests/minute** applies to every v1 route,
keyed on the authenticated user when present and the client IP otherwise. The
tighter named limiters are layered on top:

| Limiter | Applies to | Limit |
|---------|-----------|-------|
| `api-login` | login, register, forgot/reset password | 5/min per IP **and** per email |
| `api-reviews` | review submission | 10/hour, 20/day |
| `api-forum-topics` | topic creation | 5/day |
| `api-forum-posts` | reply creation | 30/day |
| `api-triage-sessions` | guidance session create/answer/emergency | 10/hour |
| `api-triage-complete` | guidance completion | 5/hour |

> IP-keyed limits require `TRUSTED_PROXIES` to be set behind a load balancer,
> or every client shares one bucket. See `infra/deploy.md`.

## Endpoints

| Method | Path | Guards |
|--------|------|--------|
| `POST` | `/auth/email/resend` | `registrations` |
| `GET` | `/auth/email/verify/{id}/{hash}` | `ValidateSignature` |
| `POST` | `/auth/forgot-password` | — |
| `POST` | `/auth/login` | — |
| `POST` | `/auth/logout` | `auth:sanctum` |
| `POST` | `/auth/register` | `registrations` |
| `POST` | `/auth/reset-password` | — |
| `GET` | `/departments` | — |
| `GET` | `/doctors` | — |
| `GET` | `/doctors/{slug}` | — |
| `GET` | `/doctors/{slug}/reviews` | — |
| `POST` | `/doctors/{slug}/reviews` | `auth:sanctum`, `role:member`, `EnsureEmailIsVerified` |
| `GET` | `/facilities` | — |
| `GET` | `/facilities/{slug}` | — |
| `GET` | `/facilities/{slug}/reviews` | — |
| `POST` | `/facilities/{slug}/reviews` | `auth:sanctum`, `role:member`, `EnsureEmailIsVerified` |
| `GET` | `/forum/categories` | `module:forum` |
| `GET` | `/forum/categories/{category}/topics` | `module:forum` |
| `POST` | `/forum/categories/{category}/topics` | `auth:sanctum`, `module:forum`, `role:member`, `EnsureEmailIsVerified` |
| `GET` | `/forum/categories/{category}/topics/{topic}` | `module:forum`, `optional-auth` |
| `PATCH` | `/forum/categories/{category}/topics/{topic}/moderation` | `auth:sanctum`, `module:forum` |
| `POST` | `/forum/categories/{category}/topics/{topic}/posts` | `auth:sanctum`, `module:forum`, `role:member`, `EnsureEmailIsVerified` |
| `GET` | `/forum/topics` | `module:forum` |
| `GET` | `/forum/topics/recent` | `module:forum` |
| `GET` | `/health` | — |
| `GET` | `/me` | `auth:sanctum` |
| `POST` | `/me/avatar` | `auth:sanctum`, `EnsureEmailIsVerified` |
| `GET` | `/me/forum/posts` | `auth:sanctum` |
| `GET` | `/me/forum/topics` | `auth:sanctum` |
| `GET` | `/me/reviews` | `auth:sanctum` |
| `GET` | `/pharmacies` | `module:pharmacies` |
| `GET` | `/pharmacies/{slug}` | `module:pharmacies` |
| `GET` | `/pharmacies/{slug}/products` | `module:pharmacies` |
| `GET` | `/pharmacies/{slug}/reviews` | `module:pharmacies` |
| `POST` | `/pharmacies/{slug}/reviews` | `auth:sanctum`, `role:member`, `EnsureEmailIsVerified` |
| `GET` | `/platform/admin` | `auth:sanctum`, `role:admin` |
| `GET` | `/platform/staff` | `auth:sanctum`, `role:admin,moderator` |
| `GET` | `/products` | `module:products` |
| `GET` | `/products/{slug}` | `module:products` |
| `GET` | `/search` | — |
| `GET` | `/settings/public` | — |
| `GET` | `/specialties` | — |
| `GET` | `/specialties/{slug}` | — |
| `GET` | `/triage/flow` | `module:guidance` |
| `POST` | `/triage/sessions` | `module:guidance` |
| `PUT` | `/triage/sessions/{id}/answers` | `module:guidance` |
| `POST` | `/triage/sessions/{id}/complete` | `module:guidance` |
| `POST` | `/triage/sessions/{id}/emergency` | `module:guidance` |

### Notes

- `per_page` is capped at **50** on every list endpoint (`/search` caps at 10
  per vertical). `page` starts at 1.
- List `q` filters match **name/title only**; `city` is a separate parameter.
  Minimum query length is 2 characters, matching `SearchQuery::normalize`.
- Review lists accept `sort` (`newest|oldest|rating_high|rating_low`) and
  `rating` (1–5), and return `meta.viewer_review` when the caller has one.
- `GET /health` returns `data.status` of `ok` (200) or `degraded` (503) with a
  `checks` map. It is **exempt from maintenance mode**, so a 503 there always
  means real degradation.
- Forum content may be created already-approved when the author holds
  `forum.moderate` or the corresponding moderation setting is off; otherwise it
  is `pending`.
- Replying to a locked topic returns **422**, not 403.
- Guidance sessions are anonymous by default. A session created while
  authenticated is bound to that user and returns 404 to anyone else.

## Roles and permissions

Authorization uses **Spatie permissions**; the `users.role` column is a coarse
account type (`member`, `moderator`, `admin`), not the authorization source.

- Staff moderators and admins moderate through the Filament panel and the
  public moderation endpoint.
- **Community moderators** are client accounts holding the `Forum Moderator`
  role, optionally scoped to specific categories via `forum_category_moderator`.
  A scoped moderator is refused outside their categories, on both the API and
  the admin panel.

See [community-moderator-onboarding.md](./community-moderator-onboarding.md).

## Not in this contract yet

- Meilisearch is wired behind `SCOUT_DRIVER`; `/search` falls back to SQL.
- AI-assisted triage (gated — see [triage-safety.md](./triage-safety.md)).
- Sponsorships, mobile-specific endpoints (token refresh, device registry,
  push), cursor pagination, and a generated OpenAPI document.
