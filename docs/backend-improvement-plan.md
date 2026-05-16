# Backend — admin, API parity & directory depth (planning)

**Status:** Planning · implementation checklist in [TASKS.md](../TASKS.md#backend--track-b-admin--api).

**Audience:** Engineering (API/Filament), product — and AI sessions reloading context.

**Last updated:** 2026-05-16

**Related:** [architecture.md](./architecture.md) · [api-contract.md](./api-contract.md) · [frontend-discovery-content-plan.md](./frontend-discovery-content-plan.md) (web consumes these APIs) · [continuity-handoff.md](./continuity-handoff.md)

---

## 1. Purpose

Bring **`apps/api`** to the same completeness bar as recent **doctor** and **clinical facility** work:

- Staff can **create and maintain all directory types** in Filament without workarounds.
- **Public API** shapes match what admin can edit and what the web already displays (or will display soon).
- **Tests + contract docs** stay in sync with each slice.

This plan is **R1-safe**: no Meilisearch, sponsorships, AI triage, public registration, or new Composer packages unless explicitly approved ([TASKS.md](../TASKS.md)).

---

## 2. Non-negotiables

| Rule | Notes |
|------|--------|
| **API-first** | Business rules and validation live in Laravel; Filament is a client. |
| **Permissions** | Moderators **view** directory/triage/categories; **admins** mutate directory, catalog config, triage, categories (MVP-1). |
| **Pharmacies** | Still `facilities` rows with `type = pharmacy`; public routes stay under `/pharmacies*`, not `/facilities?type=pharmacy`. |
| **Medical UX** | No diagnosis/emergency replacement copy in API; triage stays within [triage-safety.md](./triage-safety.md). |
| **Dependencies** | No new Composer/npm deps without approval. |
| **Parked epics** | R3 search index, R4 sponsorships, G (AI triage), visual triage rule builder — do not start here. |

---

## 3. Baseline (already shipped)

Use this as “done”; do not re-plan unless regressions appear.

| Area | Shipped |
|------|---------|
| **Doctor admin** | Taxonomies (specialties, languages, clinical interests, procedures) as tables + searchable multi-select; office-hours repeater; facility relations; featured flag. |
| **Clinical facility admin** | Departments taxonomy; emergency flag; lat/lng; office-hours repeater; doctor relations; shared `AdminSelect` / `OfficeHours` helpers. |
| **List search** | Latin/Cyrillic `q` on doctors, clinical facilities, pharmacies, products, pharmacy shelf, forum topics (`ScriptInsensitiveSearch`). |
| **Discovery API (W7)** | `GET /specialties` with doctor counts; doctor `sort` + `min_reviews`; web unified search via parallel list calls (no aggregate endpoint yet). |
| **Catalog** | Product CRUD; pharmacy shelf via `ProductsRelationManager` on pharmacy facilities. |
| **UGC** | Review + forum moderation in Filament; member writes via API + cookie bridge. |
| **Guidance** | Triage flow CRUD; JSON rules with validation (no visual builder). |

---

## 4. Gaps (audit summary)

### 4.1 Admin — directory & catalog

| Gap | Impact |
|-----|--------|
| **`FacilityForm` omits `pharmacy` type** | Cannot create pharmacies in admin; editing seeded pharmacies may break type field. |
| **No dedicated pharmacy admin UX** | Pharmacies mixed in “Facilities” table; clinical fields (departments, emergency, doctors) shown inappropriately for pharmacy type. |
| **Product ↔ pharmacy offers one-way** | Shelf on pharmacy edit works; **no inverse** offers manager on `ProductResource`. |
| **Product `category` is free text** | Inconsistent filtering; optional taxonomy later (R6). |
| **Filament polish (Track A)** | Branding, table density, moderation queue UX, form section consistency — parallel, mostly UI. |

### 4.2 API — parity & routes

| Gap | Impact |
|-----|--------|
| **`PharmacyDetailResource` missing map fields** | Web cannot show embed/directions on pharmacy pages though model has lat/lng. |
| **Reviews only under `/facilities/{slug}/reviews`** | Works for any published `Facility` including pharmacy, but contract/docs imply facility = clinical; web may use inconsistent paths; no `/pharmacies/{slug}/reviews` alias. |
| **No unified `GET /search`** | Acceptable for R1 (web orchestrates); optional single endpoint in B-P4. |
| **Taxonomies only on detail** | Only `GET /specialties` is public; languages/departments/etc. are admin-only (OK unless filters need them). |

### 4.3 Tests & contract

| Gap | Impact |
|-----|--------|
| **Pharmacy review API tests** | Untested list/submit for pharmacy slugs. |
| **New taxonomy migrations** | Feature tests for departments on facilities; thin coverage for Language/Procedure admin-only. |
| **`api-contract.md`** | Facility detail fields documented; pharmacy detail + admin pharmacy workflow not fully described. |

### 4.4 Explicitly out of this track

- Meilisearch (R3), sponsorship campaigns (R4), AI triage (G).
- Triage session admin UI / export (R2 ops).
- Public registration, password reset (R1 Path B = N/A).
- Product images, price history, external stock APIs (R6).

---

## 5. Phasing

Implement as **small PRs** (one epic per PR where possible). IDs map to [TASKS.md](../TASKS.md#backend--track-b-admin--api).

```text
B-P0 (blockers) ──► B-P1 (pharmacy/catalog) ──► B-P2 (API parity)
        │
        └──► B-P3 (admin UX / Track A) in parallel when touching Filament
B-P4 (optional R1 API) ──► B-P5 (hardening) ──► R2/R3/R6 as reprioritized
```

### B-P0 — Admin blockers (pharmacy workflow)

**Goal:** Staff can create and edit pharmacies like any other directory entity.

| ID | Deliverable |
|----|-------------|
| **B0.1** | Add `pharmacy` to facility type select **or** introduce `PharmacyResource` that scopes query to `type = pharmacy` and uses a **pharmacy-specific form** (recommended: separate resource + shared concerns to avoid clinical fields on pharmacies). |
| **B0.2** | **Conditional form sections:** hide departments, emergency, doctor staff when `type = pharmacy`; show shelf relation manager only for pharmacy (already partially true). |
| **B0.3** | List/table: type filter badges; default clinical list excludes pharmacy if split resource. |
| **B0.4** | Seed/docs: confirm `RichDemoSeeder` pharmacies editable after admin fix. |
| **B0.5** | Feature test or Filament smoke: create pharmacy + attach product offer (PHPUnit; no Dusk required for MVP). |

**Acceptance:** Admin can create a published pharmacy with shelf price without DB/console hacks.

---

### B-P1 — Pharmacy & catalog depth

**Goal:** Parity between pharmacy public pages and clinical facility depth where it makes sense.

| ID | Deliverable |
|----|-------------|
| **B1.1** | Extend **`PharmacyDetailResource`** (and web types) with `latitude`, `longitude` when set; office hours already present. |
| **B1.2** | **Pharmacy admin form:** lat/lng, office-hours repeater (reuse `OfficeHours`), contact fields — mirror facility contact fieldset without clinical blocks. |
| **B1.3** | **`ProductResource`:** `PharmaciesRelationManager` (or offers RM) — attach/edit pivot from product side. |
| **B1.4** | Shelf ergonomics: stale `price_updated_at` visibility (MVP-4 partial — verify table columns, bulk “mark updated”). |
| **B1.5** | Rich demo: at least one pharmacy with coordinates for map QA. |

**Acceptance:** Product and pharmacy are maintainable from either side of the offer pivot; pharmacy detail API supports map UX.

---

### B-P2 — API parity, reviews & contract

**Goal:** Predictable public API for web and future clients.

| ID | Deliverable |
|----|-------------|
| **B2.1** | **`GET` + `POST` `/pharmacies/{slug}/reviews`** — thin aliases to existing facility review logic (published pharmacy only). |
| **B2.2** | Tests: pharmacy review list + member submit; assert clinical facility slug still works on `/facilities/...`. |
| **B2.3** | Update **`docs/api-contract.md`**: pharmacy detail fields, review paths, facility vs pharmacy split. |
| **B2.4** | Optional list filters (only if web needs): `GET /facilities` — `has_emergency`, `department` slug; document query params. |

**Acceptance:** Web can call pharmacy-native review URLs; contract matches implementation.

---

### B-P3 — Filament ergonomics (overlaps Admin Track A)

**Goal:** Daily moderation and content entry feel cohesive. Can ship incrementally.

| ID | Deliverable | TASKS cross-ref |
|----|-------------|-----------------|
| **B3.1** | Panel branding / primary color aligned with web tokens | A1 |
| **B3.2** | Directory tables: density, type/emergency badges, publication state | A2 |
| **B3.3** | Moderation queue: preview body, filters, bulk actions polish | A3 |
| **B3.4** | Shared form patterns: fieldsets, icons, helper text on remaining resources | A4 |
| **B3.5** | Taxonomy resources: slug auto-generation hint, duplicate-name guardrails | new |

**Acceptance:** Documented in Filament; no API change required unless exposing new fields.

---

### B-P4 — Optional R1 discovery API (defer if web OK without)

**Goal:** Reduce Next.js fan-out or unlock future filters. **Pick per item.**

| ID | Deliverable | Notes |
|----|-------------|--------|
| **B4.1** | `GET /api/v1/search?q=&city=` — single Laravel action, capped per vertical, parallel queries internally | Align with [frontend-discovery-content-plan.md](./frontend-discovery-content-plan.md) §3.4 mid-term |
| **B4.2** | `GET /specialties/{slug}` — specialty meta + published doctor count | R6 directories |
| **B4.3** | `GET /facilities` filters: `has_emergency`, `department` | Only if list UI needs |
| **B4.4** | Public taxonomy index endpoints | **Low priority** — names already on doctor/facility detail |

---

### B-P5 — Hardening & ops handoff

**Goal:** Safe to run beta with confidence.

| ID | Deliverable | Roadmap |
|----|-------------|---------|
| **B5.1** | Expand feature tests for new admin/API slices (pharmacy, offers, map fields) | R1 |
| **B5.2** | Policy audit: pharmacy resources use `AdminManagesDirectoryRecords` | R1 |
| **B5.3** | Triage session purge job | **R2** (D7) — not B-P5 unless reprioritized |
| **B5.4** | Redis-backed rate limits | **R2** (D2) |
| **B5.5** | OpenAPI export | Post-R1 nice-to-have |

---

## 6. Suggested implementation order

For a **backend-focused sprint** (frontend paused):

1. **B-P0** — pharmacy admin unblock (highest leverage).
2. **B-P1** — pharmacy API + product inverse offers + demo coords.
3. **B-P2** — pharmacy review routes + contract + tests.
4. **B-P3** — Filament polish in parallel (A1–A4).
5. **B-P4** — only items requested by web/product.
6. **B-P5** — tests and policy pass before beta verification sign-off.

---

## 7. PR checklist (per slice)

- [ ] Migrations backward-safe; SQLite + Postgres CI green.
- [ ] `php artisan test` for touched area; update or add Feature tests.
- [ ] `docs/api-contract.md` updated for any new/changed public fields or routes.
- [ ] Filament: moderator cannot mutate directory (policy smoke).
- [ ] Rich demo seeder updated when new fields matter for local QA.
- [ ] No new Composer dependencies without approval.

---

## 8. Decision log (record choices here)

| Date | Decision | Rationale |
|------|----------|-----------|
| 2026-05-16 | Plan created; pharmacy admin is P0 | `FacilityForm` excludes `pharmacy` type — blocks catalog ops |
| 2026-05-16 | **Shipped:** separate `PharmacyResource` | Scoped Filament CRUD + shelf RM; `FacilityResource` clinical-only; product inverse offers |
| 2026-05-16 | **Shipped:** B-P1–B-P2 (partial) | Pharmacy detail lat/lng; `/pharmacies/{slug}/reviews`; facility reviews clinical-only |
| 2026-05-16 | **Shipped:** B-P3 / Track A | Filament blue brand, shared table columns, moderation UX, taxonomy helpers |
| | **TBD:** Product category taxonomy vs free text | Defer to R6 unless filtering pain in beta |

---

## 9. Related docs to update when implementing

| Change type | Doc |
|-------------|-----|
| New routes/fields | [api-contract.md](./api-contract.md) |
| Binding technical choice (e.g. split pharmacy resource) | [architecture.md](./architecture.md) decision log |
| Checklist status | [TASKS.md](../TASKS.md) Backend Track B |
| Session handoff | [continuity-handoff.md](./continuity-handoff.md) |
