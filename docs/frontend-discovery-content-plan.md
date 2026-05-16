# Frontend — discovery, content depth & search (planning)

**Status:** Planning · **not** implementation checklist (implementation tasks live in [TASKS.md](../TASKS.md)).

**Audience:** Product, design, engineering — and AI sessions Reloading context.

**Last updated:** 2026-05-16

---

## 1. Purpose

Increase **day-to-day value** for visitors (patients, caregivers, general public) by:

- surfacing **more relevant, honest, data-backed** entry points from the home page;
- making **search** match mental models (“I type once → I see everything relevant”) instead of forcing an intermediate modal workflow;
- upgrading **directory list** filters (starting with `/doctors`) to feel modern, scannable, and mobile-friendly.

This plan assumes **Macedonian-first** UI ([`apps/web/src/i18n/mk.ts`](../apps/web/src/i18n/mk.ts)), **API-first** data ([`docs/architecture.md`](./architecture.md)), and **no new npm/composer dependencies** unless explicitly approved ([`TASKS.md`](../TASKS.md)).

---

## 2. Medical trust & SaaS positioning (non-negotiable)

These principles should shape copy and layout — not only “nice sections”.

| Topic | Risk | Mitigation in UI |
|--------|------|------------------|
| **“Top / best doctors”** | Implies national medical superiority or endorsement | Frame as **ratings on this.platform only** (e.g. “Најдобро оценети **на Zdravje360**”), show **review count**, optional **minimum reviews** threshold, short footnote: reviews are moderated but subjective. |
| **Specialty counts** | Stale or zero-count erodes trust | Counts must be **live from API** (published doctors only); hide or de-emphasize specialties with 0 public profiles. |
| **Hospitals & clinics** | Users may confuse directory with emergency routing | Teaser copy stays **navigational**; keep **emergency strip** in footer/header patterns; no “call 112 here” replacement. |
| **Sponsored / featured** | Pay-to-trust perception | Keep **clear labeling** ([`SponsoredBadge`](../apps/web/src/components/ui/sponsored-badge.tsx) pattern); do not mix paid placement into “top rated” modules without separation. |
| **Guidance (symptom flow)** | Diagnosis implication | All hub links toward `/guidance` keep **non-diagnostic** language; align with [`docs/triage-safety.md`](./triage-safety.md). |

---

## 3. Search information architecture (target)

### 3.1 Today (baseline)

- **Header / ⌘K:** opens a **modal** (`SearchDialogProvider`) with name + city and deep links into **per-directory** filtered lists.
- **`/search`:** similar hub: form + cards linking to doctors/facilities/pharmacies/products with query params.

This is good for **power users** who already know which vertical they want; it is weaker for **exploratory** search (“one box, show me what exists”).

### 3.2 Target behavior (product intent)

| Entry | Behavior |
|-------|-----------|
| **Hero search / primary field** (home and optionally global) | Single submit: **`q`** (and optionally **`city`** once) → lands on a **unified results** experience that aggregates matches across **doctors, facilities, pharmacies, products** (and optionally forum **later**). |
| **Result page** | Grouped sections or tabs with **counts**; each row links to detail; **filters** refine within type (specialty, facility type, etc.). |
| **“Напредно пребарување” / Advanced search** | Opens the **existing modal** (or a dedicated `/search?mode=advanced`) for users who want **per-directory** shortcuts **without** leaving the mental model of “pick a silo first”. |

### 3.3 Keyboard & header

- **⌘/Ctrl+K:** product decision — either **focus** the primary search field on the current page, **navigate** to `/search?q=` with focus, or open **advanced** only if we keep palette mental model. Document the chosen behavior in the same PR that implements it.
- **Header loupe:** should match hero primary behavior (not modal-only), unless we explicitly keep modal for parity with mobile.

### 3.4 Backend / R3 alignment

- **Near term (R1-friendly):** implement unified results via **orchestrated parallel** `fetch` in Next.js (server component) calling existing list endpoints (`/doctors`, `/facilities`, `/pharmacies`, `/products`) with the same `q` — acceptable with **rate limit** and **pagination caps** per vertical.
- **Mid term:** optional `GET /api/v1/search?q=` aggregate endpoint in Laravel (single DB round-trip or union query) — update [`docs/api-contract.md`](./api-contract.md) when added.
- **Long term (R3):** Meilisearch-backed unified index; web consumes one API. See [`TASKS.md`](../TASKS.md) R3.

**Amendment note:** [`docs/frontend-ui-transformation.md`](./frontend-ui-transformation.md) T1 described search primarily as **overlay**; this plan **supersedes** that for **primary** search only after product sign-off. Keep overlay as **advanced** path.

---

## 4. Homepage — proposed sections

Order can be tuned; all strings in `mk.ts`.

| Section | Intent | Data source | Notes |
|---------|--------|-------------|--------|
| **Existing hero + trust + quick actions** | Entry & credibility | Current | Keep; ensure hero submit matches §3.2. |
| **Истакнати лекари (current)** | Partnership / editorial | `GET /doctors?featured=1` | Already shipped; align copy with sponsored disclosure policy. |
| **“Најдобро оценети на платформата”** | Social proof | `GET /doctors` sorted by `avg_rating` + `min_reviews` filter | **New API/query params** or client filter; legal copy block under carousel/grid. |
| **“Истражете по специјалност”** | Discovery | Specialties + **counts** | **API:** extend `GET /specialties` with `doctors_count` (published) or dedicated resource; cards link to `/doctors?specialty=slug`. |
| **“Болници и клиники”** | Facility discovery | `GET /facilities?type=…` or curated list | Card grid filtered to `hospital` + `clinic` (MK labels from i18n); link “ви сите установи”. |
| **“Како функционира”** | Onboarding / trust | Static 3–4 steps | Illustrations optional later; steps: најди лекар / спореди информации / рецензии под модерација / насоки не се дијагноза. |
| **Forum / guidance teasers** | Engagement | Optional API snippets | Short “latest topic” or static CTA if data not ready. |

---

## 5. Page-by-page improvement plan

### 5.1 `app/page.tsx` (home)

- Implement sections in §4; respect **performance** (parallel data fetches, `loading.tsx` skeletons).
- **SEO:** extend metadata or structured data only if product wants rich results (defer if R1 blocker).

### 5.2 `app/doctors/page.tsx` (list)

- **UI refactor:** sticky filter **surface** as a **toolbar**: search input, specialty **combobox or chip row**, city **autocomplete-style** input, clear filters, result count.
- **Mobile:** filters in **bottom sheet** or full-width **drawer** (CSS-only first; avoid new deps).
- **Empty / error:** stronger guidance (“размислете за помалку строг филтер”).
- **Accessibility:** field labels, `aria-live` for result counts.

### 5.3 `app/facilities/page.tsx`

- Mirror doctors patterns where applicable; **type** filter (clinic / hospital / lab) more prominent for “Болници и клиники” journeys.

### 5.4 `app/pharmacies/page.tsx` / `app/products/page.tsx`

- Align filter UI with doctors refactor (shared **directory filter shell** component).
- Products: keep **price informational** disclaimers visible near filters.

### 5.5 Detail routes (`doctors/[slug]`, etc.)

- **Cross-links:** “Други лекари од истата специјалност”, “Установи во ист град”.
- **Trust:** review count + distribution (future); keep within API reality.

### 5.6 `app/search/page.tsx` + `search-dialog-context.tsx`

- **Split behaviors:** primary **unified** flow vs **advanced** modal.
- **Migrate** keyboard shortcut and header icon **after** unified route exists.
- Remove duplicate forms where possible; single source of truth for `q`/`city` param names.

### 5.7 `app/forum/**/*`

- Category landing: short **what is moderated** blurb.
- Thread: **related doctors/facilities** links only if product-approved (optional).

### 5.8 `app/guidance/page.tsx`

- Hub panel: link to **emergency** + **disclaimer** above fold; no change to rule engine.

### 5.9 `app/account/**/*` & `app/login/page.tsx`

- Light touches: **breadcrumb consistency**, “затворена бета” reminder where appropriate.

### 5.10 Legal (`privacy`, `terms`, `disclaimer`)

- No layout experiments near beta **without legal** review; copy-only via counsel.

---

## 6. Dependency summary (API / web)

| Need | Likely change |
|------|----------------|
| Specialty cards with counts | Laravel: `withCount` on published doctors; extend `SpecialtyResource`; tests. |
| Top-rated doctors list | Laravel: list doctors sortable by `review_summary` / aggregated rating with thresholds; or document client-side sort limits. |
| Unified search page | Next: new route or expand `/search` layout; parallel fetches; section components. Optional Laravel aggregate later. |
| Shared filter UI | Next: extract `DirectoryFiltersShell` from `filter-form.tsx` patterns. |

---

## 7. Suggested phasing (for TASKS)

1. **P0 — Discovery UX (web-heavy):** home sections that need **no** API change (static “Како функционира”, facilities teaser with existing filters).
2. **P1 — API small:** specialty `doctors_count`; top-rated query params.
3. **P2 — Search IA:** unified results page + advanced modal; update header/⌘K.
4. **P3 — Directory filter redesign:** doctors first, then roll to facilities/pharmacies/products.

Phasing can overlap **after** product picks P0 vs beta gate.

---

## 8. Related documents

| Doc | Relationship |
|-----|----------------|
| [`TASKS.md`](../TASKS.md) | Actionable checkboxes (**Track W7**). |
| [`docs/continuity-handoff.md`](./continuity-handoff.md) | Summary pointer for fresh chats. |
| [`docs/frontend-ux-plan.md`](./frontend-ux-plan.md) | Historical W-track; W7 extends it. |
| [`docs/frontend-ui-transformation.md`](./frontend-ui-transformation.md) | T1–T4 complete; search overlay amended by §3 above. |
| [`docs/api-contract.md`](./api-contract.md) | Update when new query params or endpoints ship. |
