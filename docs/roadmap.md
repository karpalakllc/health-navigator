# Zdravje360 — Roadmap (one-page)

Canonical planning model after **domain foundations + public UX** (Phases 0–4, 3a–3f-a). Detailed checklists live in [TASKS.md](../TASKS.md).

**Legend:** ✅ shipped · 🎯 active · ⏸ parked · ⏳ planned · 🚫 gated / post-beta

---

## Active focus 🎯

| Phase | Theme | Status |
|-------|--------|--------|
| **UX polish** | Web + admin UI | 🎯 **Now** — [frontend-ux-plan.md](./frontend-ux-plan.md) (local-first) |
| **R1 launch** | Beta verification + ops | ⏸ When ready — [beta-verification.md](./beta-verification.md), deploy, legal, invites |

**MVP (product finalization):** ✅ **Complete** — MVP-1…MVP-5; acceptance script [mvp-acceptance.md](./mvp-acceptance.md).

**Not in current focus:** Meilisearch (R3), sponsorships (R4), AI triage (G), mobile (R8), visual rule builder, full rebrand.

---

## Parked / complete

| Track | Status |
|-------|--------|
| **MVP** product finalization | ✅ Shipped (see [TASKS.md](../TASKS.md)) |
| R1 **repo** (MK dictionary, legal pages, Sentry, deploy runbook) | ✅ Done |
| R1 **ops** (staging, legal external, invites) | 🎯 Active — verification checklist |

---

## Shipped foundations ✅

| Layer | Delivered |
|-------|-----------|
| Platform | PostgreSQL API, Sanctum + Filament, CI, [api-contract.md](./api-contract.md), cookie-bridge member writes |
| Directories | Doctors, clinical facilities, pharmacies, products |
| UGC | Moderated reviews, forum (categories, topics, replies) |
| Guidance | Rule-based **Symptom guidance** (3f-a); [triage-safety.md](./triage-safety.md) |
| Public web | Shell, login, account, `/search` hub (SQL), `/guidance` |

---

## Beta scope (reference — not active work)

**In (when launch resumes):** Everything shipped + R1 ops checklist + beta verification.

**Out:** Meilisearch, sponsorships, **3f-b AI triage**, mobile, checkout, deep expansions, English locale.

**Registration:** **Path B (locked)** — invite-only; see [beta-closed.md](./beta-closed.md).

---

## Roadmap phases

| Phase | Theme | Priority | Notes |
|-------|--------|----------|--------|
| **MVP** | Product finalization | ✅ Complete | [mvp-acceptance.md](./mvp-acceptance.md) · [TASKS.md](../TASKS.md#mvp--product-finalization-complete) |
| **R1** | Beta launch blockers | 🎯 **Active** | Deploy, external legal, [beta-verification.md](./beta-verification.md) |
| **R2** | Post-beta scale | ⏳ | Redis, queues, triage retention job, runbooks |
| **R3** | Search | ⏳ | Meilisearch + index jobs + unified search API/web |
| **R4** | Monetization | ⏳ | Sponsored placements + Filament scheduling |
| **R5** | Editorial CMS | ⏳ | Marketing pages beyond static legal |
| **R6** | Domain depth | ⏳ Optional | Forum/pharmacy/directory/reviews/guidance pick-list |
| **G** | **3f-b AI triage** | 🚫 Gated | Legal + triage-safety v2 + feature flag |
| **R8** | Mobile | ⏳ Later | Same API v1 |

---

## Dependency sketch

```text
MVP ✅ ──► R1 launch (active: beta-verification, deploy)
       │
       └──► R2 (scale) ──► R3 (search)
                    │
                    ├──► R4 (sponsorships)
                    └──► R5 (CMS marketing)

G (AI triage) ── independent gate; after 3f-a only

R8 (mobile) ── after stable auth + API v1
```

---

## Related docs

| Doc | Role |
|-----|------|
| [TASKS.md](../TASKS.md) | Active MVP checklists, parked beta gate, R1–R8 |
| [beta-verification.md](./beta-verification.md) | Pre-invite checklist (**parked**) |
| [PROJECT_BRIEF.md](../PROJECT_BRIEF.md) | Product vision and constraints |
| [architecture.md](./architecture.md) | System design (not the roadmap) |
| [triage-safety.md](./triage-safety.md) | Symptom guidance safety scope |
