# Continuity handoff (for humans + AI)

**Purpose:** Reload context after clearing a long chat. Read this first, then [TASKS.md](../TASKS.md) for the live checklist.

**Last updated:** 2026-05-16 (Track **W7** + [backend-improvement-plan.md](./backend-improvement-plan.md)).

---

## 1. Project snapshot

| Item | Detail |
|------|--------|
| **Product** | Zdravje360 — MK-first health directory (doctors, facilities, pharmacies, products), forum, reviews, rule-based symptom guidance (not diagnosis). |
| **Monorepo** | `apps/api` — Laravel 13 API + Filament admin; `apps/web` — Next.js 16 public site; `docs/`, `infra/`. |
| **Auth** | Invite-only / closed beta; Sanctum API tokens; web uses cookie bridge via `apps/web` API routes. |
| **Locale** | UI strings in `apps/web/src/i18n/mk.ts` (no `next-intl` unless reprioritized). |

---

## 2. What is done (high level)

### 2.1 Product / engineering (on `main`)

- **API:** v1 JSON endpoints for directory, forum, reviews, products, triage, auth/me; policies and tests.
- **Filament:** Full admin for directory, catalog, forum moderation, triage flows, users; MVP-1 moderation UX (dashboard, bulk actions, role gates).
- **Web:** Public routes for home, directories, search (full page + **global search overlay**), forum, guidance, account, login, legal pages.
- **UX transformation (T1–T4)** — see [frontend-ui-transformation.md](./frontend-ui-transformation.md) and `TASKS.md`:
  - **T1:** Header/footer shell, search icon → modal (`SearchDialogProvider`, Cmd/Ctrl+K), account menu, mobile sheet.
  - **T2:** Home trust strip, icon quick actions, `PageSection`, featured doctors blurb.
  - **T3:** `DirectoryCardGrid`, `DirectoryDetailLayout`, sticky responsive filters, shared list skeletons, product breadcrumbs.
  - **T4:** Login split layout + skeleton; `AccountLayout` / sub-nav; forum breadcrumbs + token forms + `StackedList`; guidance wizard in framed panel; loading routes (login, account, search, guidance, forum threads); `not-found` / `error` extra links; **`Pagination.pageParam`** for `topics_page` on account forum.
- **Ops / docs:** CI workflow (`.github/workflows/ci.yml`), Sentry wiring (web + api), deploy notes, beta/roadmap docs, rich demo seed.
- **Git:** Large integrative commit on **`main`** at **`36c3cce`** (feat: platform API, Filament admin, public web MVP), pushed to **`origin/main`** (`github.com/karpalakllc/health-navigator`).

### 2.2 Explicit non-goals (unless roadmap changes)

- Meilisearch / unified typeahead (R3+), sponsorships, mobile apps, **AI triage (G / 3f-b)**, public registration without product decision, new npm/composer deps without approval.

---

## 3. What to do next (priorities)

Use **[TASKS.md](../TASKS.md)** as source of truth; summary below.

| Area | Next work |
|------|-----------|
| **Pre-launch (active)** | [pre-launch-master-plan.md](./pre-launch-master-plan.md) — R1+R2+R3 before deploy, registration, settings, forum overhaul, hide triage/products, emails, analytics. Track B largely **done**. |
| **R1 / beta** | Run [beta-verification.md](./beta-verification.md); staging deploy per [infra/deploy.md](../infra/deploy.md); legal copy external sign-off; ops content/moderation. |
| **Admin — Track A** | A1–A4 in `TASKS.md`: Filament branding vs web tokens, table density/badges, moderation queue polish, form sections consistency (overlaps B-P3). |
| **Web — discovery (Track W7)** | Mostly shipped; optional homepage copy review. See [frontend-discovery-content-plan.md](./frontend-discovery-content-plan.md). |
| **Web (optional polish)** | Pharmacy/facility detail parity when API B-P1 ships; `next/image` for directory cards (lint warnings). |
| **CI** | Keep `main` green; rotate non-local credentials per beta checklist. |

**Do not** start R2+ feature epics or **G** without reprioritization and doc updates.

---

## 4. How we work (dynamics)

### 4.1 Before structural or cross-cutting changes

Read **[PROJECT_BRIEF.md](../PROJECT_BRIEF.md)**, **[docs/architecture.md](./architecture.md)**, **[TASKS.md](../TASKS.md)** + active roadmap phase (**R1 launch**). If a change conflicts, update docs in the same PR.

### 4.2 Scope discipline

- Prefer **one app per change** (`apps/api` *or* `apps/web`) unless cross-app is required.
- **Small, reviewable diffs**; avoid drive-by refactors.
- **No new dependencies** (Composer/npm) without explicit approval — align with R1.
- Matching existing code style; run **lint/build** for the touched app before handing off.

### 4.3 Git & GitHub

- **Commits:** Only when the user explicitly asks (user rule).
- **Push / PRs:** Only when asked; avoid force-push to `main`.
- **PRs:** User may request `gh pr create` with title + body + test plan.

### 4.4 Medical / legal UX

- Never present content as diagnosis or emergency care; triage/guidance must stay within [triage-safety.md](./triage-safety.md).
- Sponsored/featured content must be clearly labeled when implemented.

### 4.5 AI session hygiene

- This file + `TASKS.md` replace re-reading an entire long thread.
- Cursor rules: **`.cursor/rules/project.mdc`** (monorepo, R1 focus, no stray packages).

---

## 5. Quick navigation

| I need… | Go to… |
|---------|--------|
| Checklist & phases | [TASKS.md](../TASKS.md) |
| Public web target UX | [frontend-ui-transformation.md](./frontend-ui-transformation.md) |
| Discovery / search / home content (planned) | [frontend-discovery-content-plan.md](./frontend-discovery-content-plan.md) |
| Backend admin & API parity (planned) | [backend-improvement-plan.md](./backend-improvement-plan.md) |
| API shapes | [api-contract.md](./api-contract.md) |
| Triage constraints | [triage-safety.md](./triage-safety.md) |
| Beta gate | [beta-verification.md](./beta-verification.md) |
| Deploy | [infra/deploy.md](../infra/deploy.md) |
| Filament / policies detail | `apps/api/app/Filament/`, `apps/api/app/Policies/` |
| Web layout / search | `apps/web/src/components/layout/search-dialog-context.tsx`, `site-header-bar.tsx`, `site-footer.tsx` |
| i18n | `apps/web/src/i18n/mk.ts` |

---

## 6. Starting a fresh chat (suggested prompt)

Paste something like:

> Read `docs/continuity-handoff.md` and `TASKS.md`. We’re continuing Zdravje360 on `main`. Discovery/search work: see `docs/frontend-discovery-content-plan.md` (Track W7). \<your immediate task\>

That keeps the model aligned without replaying full history.
