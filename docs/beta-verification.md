# Beta verification — invite-only closed beta

Practical checklist before inviting **external** testers. Not a heavy governance process — work through the sections, check boxes, go when required items pass.

**Related:** [mvp-acceptance.md](./mvp-acceptance.md) (internal MVP walkthrough — done) · [beta-closed.md](./beta-closed.md) (Path B policy) · [beta-content-readiness.md](./beta-content-readiness.md) (content/moderation) · [infra/deploy.md](../infra/deploy.md) (staging deploy)

**Launch mode:** Closed invite-only — staff-provisioned accounts only; no public registration.

---

## Required before external invites

All items below must pass on the **same staging URL** (or production URL, if that is what testers receive).

### Legal

- [ ] `/privacy`, `/terms`, `/disclaimer` reviewed and **approved by counsel** (no “Нацрт” / DRAFT banners on pages)
- [ ] Cookie notice acceptable inside privacy (no separate `/cookies` unless legal adds one later)
- [ ] Symptom guidance copy on staging does not claim diagnosis or emergency dispatch ([triage-safety.md](./triage-safety.md))

**DRAFT legal copy:** Allowed for **staging/internal QA only**. **Not allowed** when sending credentials to external beta testers.

### Staging works end-to-end

- [ ] `GET {API_URL}/api/v1/health` returns `{"data":{"status":"ok"}}` over HTTPS
- [ ] Web home loads over HTTPS; `NEXT_PUBLIC_API_URL` points at that API
- [ ] **Closed-beta banner** visible (`NEXT_PUBLIC_CLOSED_BETA=true`)
- [ ] **Footer links** to Privacy, Terms, Disclaimer open correctly on deployed URL
- [ ] Member login → account page (no CORS errors in browser console)
- [ ] Submit review → appears as **pending** in Filament
- [ ] Submit forum topic → appears as **pending** in Filament
- [ ] Symptom guidance (`/guidance`): complete normal path + red-flag → emergency-only outcome

### Content

- [ ] Staging has **real or realistic** doctors, facilities, pharmacies (not only local dev seed names)
- [ ] Pharmacy reference prices sanity-checked for obvious errors
- [ ] Published symptom guidance flow reviewed in Filament (red flags, outcomes)

**Content language debt:** UI chrome is MK (`apps/web/src/i18n/mk.ts`). Database text may stay EN/Latin — acknowledge in [beta-content-readiness.md](./beta-content-readiness.md); does not block beta if product agrees.

### Moderation & testers

- [ ] Named **moderators** with Filament access (`moderator` or `admin`)
- [ ] **Moderation SLA** documented and shared with testers (e.g. pending queue within N business days)
- [ ] **Tester provisioning** exercised once: admin creates `member` in Filament → tester logs in → can submit UGC ([beta-closed.md](./beta-closed.md))
- [ ] Tester comms sent: URL, credentials (secure channel), terms/disclaimer links, invite-only limits

### Security & observability

- [ ] **No default seed credentials** on staging/prod (`PlatformUserSeeder` and directory seeders do not run outside `local`/`testing`)
- [ ] Staff/admin passwords are strong and unique (not `password` from `.env.example`)
- [ ] **Sentry** receives test events for API and web (correct environment tag)
- [ ] **Health/uptime** check on API health endpoint and web `/` (PaaS monitor or external ping)

---

## Repo verification (before or with staging deploy)

Run locally or in CI — does not replace staging checks.

| Check | How | Pass |
|-------|-----|------|
| Tests | `cd apps/api && php artisan test` | All green |
| Web build | `cd apps/web && npm run lint && npm run build` | No errors |
| CI | Latest `main` workflow | Green |
| **No register / sign-up CTA** | Grep `apps/web/src` for `register`, `sign up`, `sign-up`, `Sign up` (exclude `instrumentation.ts` `register`) | No public registration UI or links |
| Invite-only copy | Login page + [beta-closed.md](./beta-closed.md) | States staff-provisioned accounts |
| Legal routes exist | Build output includes `/privacy`, `/terms`, `/disclaimer` | Present |
| Seed safety | Seeders guard `local`/`testing` only | Confirmed in code review |

Optional local smoke: directories, forum, guidance, login with seeded member — see [TASKS.md](../TASKS.md) R1 archive.

---

## Explicit non-blockers

Do **not** delay invite-only beta for:

- Meilisearch / unified search (R3)
- Sponsorships (R4)
- AI symptom guidance (G / 3f-b)
- English locale / `next-intl`
- Mobile apps (R8)
- OpenAPI export
- Full MK translation of database content
- Redis, CMS marketing pages, checkout, pharmacy integrations

---

## Lightweight sign-off

| | Name | Date |
|---|------|------|
| Product | | |
| Engineering | | |
| Legal (external invites) | | |
| Ops / moderation | | |

**Go** when all **Required before external invites** boxes are checked. **No-go** if any required item fails.

---

## Suggested order

1. Repo verification (engineering, ~half day)
2. Deploy staging per [infra/deploy.md](../infra/deploy.md)
3. Staging end-to-end + banner/footer/legal links on deployed URL
4. Parallel: legal approval, content load, moderators + SLA
5. Tester provisioning dry run
6. Sentry + health checks
7. Sign-off → invite first tester cohort
