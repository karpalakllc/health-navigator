# MVP internal acceptance — scripted walkthrough

Use this checklist on **local** (or internal staging) to confirm the team can run directory content and UGC moderation without engineering. This is **not** external beta verification — see [beta-verification.md](./beta-verification.md) after this pass.

**Automated guard:** `apps/api/tests/Feature/MvpAcceptanceFlowTest.php` covers the API portion (publish → submit → moderate → public visibility).

---

## Prerequisites

| Item | Notes |
|------|--------|
| API running | `http://127.0.0.1:8000` — health: `GET /api/v1/health` |
| Web running | `http://127.0.0.1:3000` — `NEXT_PUBLIC_API_URL` matches API |
| Database seeded | `cd apps/api && php artisan migrate:fresh --seed` (local only) |
| Filament admin | `/admin` — `admin@zdravje360.test` / `password` (local defaults) |
| Test member | `member@zdravje360.test` / `password` |
| Optional | `WEB_PUBLIC_URL=http://127.0.0.1:3000` in `apps/api/.env` for Filament “View on site” |

**Engineering baseline (run before walkthrough):**

```bash
cd apps/api && php artisan test
cd apps/web && npm run lint && npm run build
```

---

## A — Publish directory content (admin)

1. Log in to Filament as **admin**.
2. **Doctors → Create:** fill name, slug, city; set **Published** on; save.
3. Open the record → **View on site** (if `WEB_PUBLIC_URL` set) or visit `/doctors/{slug}` on the web app.
4. Confirm the doctor appears on `/doctors` and detail page loads.
5. *(Optional)* Repeat for a **clinical facility** and a **pharmacy**; attach a doctor to a facility from either edit form (searchable multi-select).

**Pass:** Published directory rows are visible on the public site; drafts are not listed.

---

## B — Member submits UGC (web)

1. Log out of Filament. On the web app, go to **Login** and sign in as **member@zdravje360.test**.
2. Open the published doctor from step A → scroll to **Reviews** → submit rating + comment (min length per form).
3. Confirm success / pending messaging; review must **not** appear in the public list yet.
4. **Account → Reviews** — status shows pending.
5. **Forum →** pick a published category → **New topic** — title + body; submit.
6. **Account → Forum** — topic shows pending; category listing must **not** show the topic yet.

**Pass:** Member can submit review and forum topic; both stay pending until moderated.

---

## C — Staff moderates (Filament)

1. Log in as **moderator** or **admin**.
2. Dashboard **Moderation queue** shows pending counts > 0 (or open **Community → Reviews / Forum topics**).
3. Filter **Status → Pending** if needed; **Approve** the review and the forum topic (single or bulk).
4. Confirm queue counts decrease.

**Pass:** Moderator can approve pending UGC without touching directory CRUD (moderator has view-only on directory).

---

## D — Verify on public web

1. Log out or use a private window (no member session required).
2. Doctor detail — approved review visible with rating.
3. Forum category — approved topic listed; open topic — opening post visible.
4. Re-check **Account** as member — statuses show approved where applicable.

**Pass:** Approved content is public; pending/rejected content stays hidden.

---

## E — Admin-only sanity (quick)

| Check | How |
|-------|-----|
| Moderator cannot edit doctors | Filament → Doctors → Create/Edit should be absent or forbidden |
| Admin can set member password | Users → member → Set password |
| Symptom guidance loads | `/guidance` — complete flow; red-flag path shows emergency-oriented outcome |
| Legal pages | Footer → Privacy, Terms, Disclaimer |

---

## Sign-off

| Role | Name | Date | Notes |
|------|------|------|-------|
| Product / ops | | | Walkthrough A–D completed |
| Engineering | | | Tests + web build green; no open P0 |

When all rows are signed, mark **MVP-5** complete in [TASKS.md](../TASKS.md) and resume [beta verification](./beta-verification.md) for external launch prep.
