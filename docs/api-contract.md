# API contract (Phase 2 + 3a–3f-a + 4a baseline)

Base URL: `{API_URL}/api/v1` (e.g. `http://127.0.0.1:8000/api/v1`).

## Conventions

| | |
|--|--|
| Success (single) | `{ "data": { ... } }` or `{ "data": [ ... ] }` (non-paginated lists) |
| Success (paginated list) | `{ "data": [ ... ], "meta": { "current_page", "per_page", "total", "last_page" } }` |
| Validation error | `422` with `{ "message": "...", "errors": { field: string[] } }` (Laravel validation) |
| Unauthorized | `401` with `{ "message": "Unauthenticated." }` |
| Forbidden | `403` with `{ "message": "..." }` |
| Not found | `404` with `{ "message": "Not found." }` |
| Too many requests | `429` with `{ "message": "Too many requests." }` |

## Authentication

**Strategy:** Laravel Sanctum **personal access tokens** (Bearer). Same mechanism for Next.js and future mobile clients.

| Channel | Mechanism |
|---------|-----------|
| API (`/api/v1/*`) | Bearer token; login does **not** start a web session |
| Filament (`/admin`) | Web session (separate from API tokens) |

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| `POST` | `/auth/login` | — | Body: `{ email, password, device_name? }` → `data`: `{ user, token, token_type }`. **Rate limit:** `api-login` (5/min per IP and per email). |
| `POST` | `/auth/logout` | Bearer | Revokes current token |
| `GET` | `/me` | Bearer | `data.user`: `{ id, name, email, role }` |

**Token expiration:** Sanctum `expiration` from `SANCTUM_TOKEN_EXPIRATION_MINUTES` (default **43200** = 30 days). New tokens receive `expires_at` accordingly.

**Web client:** Next.js stores the Bearer token in an httpOnly cookie via Route Handlers (`/api/session/login`); the browser never reads the token. Mobile uses Bearer directly.

**No public registration.** Member accounts are provisioned by staff/seeders until a registration epic ships.

## Public — directory

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/health` | `{ "data": { "status": "ok" } }` |
| `GET` | `/specialties` | Published specialties. `data[]`: `{ slug, name, description }` |
| `GET` | `/doctors` | Paginated doctors. Query: `specialty`, `city`, `q` (name, **min 2 chars**), `page`, `per_page` |
| `GET` | `/doctors` | Query also accepts `featured=1` for home highlights. List items include `review_summary`, `avatar_url`, `years_experience`, `accepts_new_patients`, `is_featured`. |
| `GET` | `/doctors/{slug}` | Doctor detail + `specialties[]`, `facilities[]` (published clinical only), `review_summary`, profile fields (`education`, `languages[]`, `clinical_interests[]`, `procedures[]`, `office_hours`, `consultation_fee_note`, …) |
| `GET` | `/doctors/{slug}/reviews` | Approved reviews. Item: `{ id, rating, body, author_name, published_at }` |
| `GET` | `/facilities` | Paginated **clinical** facilities only (`clinic`, `hospital`, `laboratory`). Query: `type`, `city`, `q`, `page`, `per_page`. **`type=pharmacy` is not accepted.** |
| `GET` | `/facilities/{slug}` | Clinical facility detail (pharmacy slugs → `404`) |
| `GET` | `/facilities/{slug}/reviews` | Approved reviews (includes pharmacy facilities by slug) |

## Public — pharmacies & catalog

Pharmacies are `facilities` with `type = pharmacy`. Use `/pharmacies*` for pharmacy browsing, not `/facilities?type=pharmacy`.

**Prices:** Admin-managed, informational only. Each offer includes `price`, `currency` (default `MKD`), and `price_updated_at` (when staff last set the price). Not for e-commerce on this platform.

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/pharmacies` | Paginated published pharmacies. Query: `city`, `q`, `page`, `per_page` |
| `GET` | `/pharmacies/{slug}` | Pharmacy detail + `review_summary` |
| `GET` | `/pharmacies/{slug}/products` | Paginated products offered at this pharmacy. Query: `q`, `category`, `page`, `per_page`. Item: `{ slug, name, category, price, currency, price_updated_at }` |
| `GET` | `/products` | Paginated published products. Query: `q`, `category`, `pharmacy` (slug), `page`, `per_page`. Item: `{ slug, name, category, from_price }` (`from_price` = min offer among published pharmacies) |
| `GET` | `/products/{slug}` | Product detail. `offers[]` (max 10, cheapest first): `{ pharmacy: { slug, name, city }, price, currency, price_updated_at }`, plus `offers_total`, `offers_truncated` |

`review_summary`: `{ count, average_rating }`.

## Public — forum

Moderated community discussions. **Informational only — not medical advice.** Pending content is never returned on public routes.

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/forum/categories` | Published categories. `data[]`: `{ slug, name, description, topics_count? }` |
| `GET` | `/forum/categories/{category}/topics` | Approved topics in category. Query: `q` (title, **min 2 chars**), `page`, `per_page`. Sort: pinned first, then `last_post_at` |
| `GET` | `/forum/categories/{category}/topics/{topic}` | Approved topic + paginated approved replies. Response: `{ data: { topic, posts }, meta }` |

Topic list item: `{ slug, title, author_name, replies_count, last_post_at, is_pinned, published_at }`.  
Topic detail: `{ slug, title, body, author_name, category, replies_count, is_locked, is_pinned, published_at }`.  
Post item: `{ id, body, author_name, published_at }`.

## Public — symptom guidance (internal: triage)

**Informational only — not diagnosis or emergency care.** One published flow at a time. Rule logic is server-side only and **not** returned by the API.

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/triage/flow` | Published flow: `{ title, intro_body, red_flags[], steps[] }`. Steps: `{ key, type, label, required, options[] }`. `404` if none published. |
| `POST` | `/triage/sessions` | Body: `{ accepted_terms: true }` (required). `201` → `{ session_id }`. Optional Bearer attaches `user_id`. **Rate limit:** `api-triage-sessions` (10/hour per IP). |
| `PUT` | `/triage/sessions/{id}/answers` | Body: `{ answers: [{ step_key, values: string[] }] }`. `red_flags` step may short-circuit emergency. → `{ session_id, emergency_stopped }`. |
| `POST` | `/triage/sessions/{id}/emergency` | Marks emergency, completes session → `{ session_id, emergency_stopped, outcome }`. |
| `POST` | `/triage/sessions/{id}/complete` | Evaluates rules → `{ session_id, outcome }`. Outcome: `{ outcome_code, title, body, handoffs[] }`. **Rate limit:** `api-triage-complete` (5/hour per IP). |

Handoff item: `{ type: home|doctors|facilities|emergency, label?, href? }`.

## Member (authenticated)

| Method | Path | Roles | Description |
|--------|------|-------|-------------|
| `POST` | `/doctors/{slug}/reviews` | `member` | Submit review → `pending`. **Rate limit:** `api-reviews` (10/hour, 20/day per user) |
| `POST` | `/facilities/{slug}/reviews` | `member` | Same (use for pharmacy slugs; web pharmacy pages use this path) |
| `GET` | `/me/reviews` | Bearer | Own reviews with `status` |
| `POST` | `/forum/categories/{category}/topics` | `member` | Create topic → `pending`. **Rate limit:** `api-forum-topics` (**5/day** per user) |
| `POST` | `/forum/categories/{category}/topics/{topic}/posts` | `member` | Reply on approved, unlocked topic → `pending`. **Rate limit:** `api-forum-posts` (**30/day** per user) |
| `GET` | `/me/forum/topics` | Bearer | Own topics (all statuses) |
| `GET` | `/me/forum/posts` | Bearer | Own replies (all statuses) |

## Platform (protected)

| Method | Path | Roles | Description |
|--------|------|-------|-------------|
| `GET` | `/platform/staff` | `admin`, `moderator` | Staff placeholder |
| `GET` | `/platform/admin` | `admin` | Admin placeholder |

## Roles

| Value | API | Filament `/admin` |
|-------|-----|-------------------|
| `admin` | Full platform routes | Yes |
| `moderator` | Staff routes | Yes |
| `member` | Reviews + forum create | No |

## Admin panel

- **Facilities** — all types including pharmacy; **product offers** relation manager visible only when `type = pharmacy`
- **Products** — catalog CRUD (`ProductResource`)
- **Reviews** — moderation queue
- **Forum** — categories CRUD; topic/reply moderation (approve/reject, pin, lock)
- **Symptom guidance** — flow, steps, red flags, rules, outcomes (one published flow)
- Publication: `is_published` + `published_at` on directory entities; offers require pivot `is_available` and both parents published

## Local dev users (seeded)

| Email | Role | Password |
|-------|------|----------|
| `admin@zdravje360.test` | admin | `password` |
| `moderator@zdravje360.test` | moderator | `password` |
| `member@zdravje360.test` | member | `password` |

## Not in this contract yet

`POST /auth/register`, checkout/cart/orders, stock sync, external pharmacy APIs, `GET /pharmacies/{slug}/reviews`, `GET /products/{slug}/pharmacies`, product-specific reviews, member forum edit/delete, triage AI (3f-b), `GET /triage/sessions/{id}` resume, sponsorships, Meilisearch, media/photos, maps/geo, opening hours.
