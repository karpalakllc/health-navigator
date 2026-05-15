# Beta content & moderation readiness

Ops checklist for **closed beta sign-off**. Engineering R1 does not complete these items — owners are product/content/moderation.

## Content

- [ ] Staging has **real or realistic** doctors, facilities, and pharmacies (not only dev seed names).
- [ ] Pharmacy product/prices reviewed for obvious errors.
- [ ] **Content language debt acknowledged:** UI chrome is Macedonian (`apps/web/src/i18n/mk.ts`); **database** fields (names, descriptions, seeded guidance copy, forum posts) may remain EN/Latin until a later content pass — not part of R1. Track gaps if needed.
- [ ] Symptom guidance flow reviewed in Filament; red flags and outcomes appropriate for MK audience (legal review).

## Legal

- [ ] Static pages reviewed by counsel: `/privacy`, `/terms`, `/disclaimer` (replace `DRAFT` placeholders in `apps/web/src/content/legal/`).
- [ ] Cookie notice folded into privacy policy (no separate `/cookies` route in R1 unless legal requires it later).

## Moderation

- [ ] Named moderators with Filament access (`moderator` or `admin`).
- [ ] SLA documented (e.g. review queue checked within X business days).
- [ ] Process for rejecting harmful forum/review content.

## Security & access

- [ ] Production/staging: **no default seed passwords**; `PlatformUserSeeder` and directory seeders do not run outside `local`/`testing`.
- [ ] Tester accounts use unique passwords; offboarding process if tester leaves.

## Smoke test (staging)

- [ ] `GET /api/v1/health` OK
- [ ] Web home, directories, forum, `/guidance` load
- [ ] Member login → submit review → appears as pending in Filament
- [ ] Sentry receives a test event (then resolve/delete in Sentry UI)
