# Frontend UI/UX improvement plan

> **Next step:** The **target** public-web experience (full shell, all pages, search overlay) is specified in **[frontend-ui-transformation.md](./frontend-ui-transformation.md)**. This file remains the **incremental W-track history** and technical notes from the first polish pass.

**Context:** Local-first until launch. MVP backend + flows work; the public web and Filament admin need a **visual and interaction upgrade** inspired by patterns in the Lovable reference (`health-navigator-mk-main` on Desktop), **not** a port of that codebase.

**Brand:** Zdravje360 (keep name; reference uses “МоеЗдравје” only as UX inspiration).

**Principles:**
- Real API data only — no mock catalogs or fake maps.
- Macedonian-first UI (`mk.ts`); extend dictionary, do not add `next-intl` unless reprioritized.
- Medical trust: disclaimers, emergency paths, no diagnosis language — align with [triage-safety.md](./triage-safety.md).
- **Do not** block on deploy, Meilisearch (R3), maps SDK, or features we have not built (barcode scanner, vitamins, booking).

---

## Reference vs Zdravje360 (gap summary)

| Area | Reference (Lovable) | Zdravje360 today | Gap |
|------|---------------------|------------------|-----|
| **Design tokens** | HSL theme: medical blue + emerald, warning/destructive, `--radius: 1rem` | Geist + zinc utilities only | No semantic health palette |
| **Components** | shadcn/Radix (~40 primitives) | ~10 custom layout pieces | No Button, Input, Card, Badge, Dialog, Skeleton |
| **Home** | Hero search, quick actions, featured sections, motion | Title + 4 link cards | Weak entry hub |
| **Navigation** | Sticky glass header, mobile bottom nav, active states | Flat header, wraps on mobile, no `/guidance` in nav | Mobile + wayfinding |
| **Lists** | Rich cards, chip filters, sticky filter bar, empty states | Text rows in `EntityList`, basic form filters | Discovery feel |
| **Detail** | 2-col + sticky sidebar CTA, breadcrumbs, review histogram | Single column, `BackLink` only | Hierarchy + trust |
| **Guidance** | Polished wizard cards, urgency colors | Functional wizard; one EN string | Visual urgency system |
| **Products/pharmacy** | Price comparison table, “best deal” row | Shelf list + disclaimer | Compare UX (data allowing) |
| **Loading / errors** | Skeleton available (unused) | None | No `loading.tsx` / `error.tsx` |
| **SEO** | N/A (SPA) | Root metadata only | Per-route metadata |
| **Admin** | N/A | Filament defaults | Separate track below |

**Do not copy from reference:** barcode scanner, seasonal vitamins, hospital dialog mockups, fake map tiles, English triage copy, static JSON data, or routes we do not support.

---

## Recommended tracks (can run in parallel)

```text
Track W — Web UI/UX (this doc)          Track A — Admin polish (Filament)
        │                                        │
        ├─ W0 Profile schema + API               ├─ A1 Shell & density
        ├─ D0 Rich demo seed                     ├─ A2 Directory forms/tables
        ├─ W1 Foundation                         ├─ A3 Moderation UX
        ├─ W2 App chrome                         ├─ A2 Directory forms/tables
        ├─ W3 Home & search hub                  ├─ A3 Moderation UX
        ├─ W4 Directories                        └─ A4 Guidance admin readability
        ├─ W5 Guidance / forum / account
        └─ W6 Polish & a11y
```

**Suggested order:** **W0 + D0** first (schema + realistic local data). Then **W1 → W2** (tokens + chrome). Then **W3 + W4** for biggest user-visible lift. **W5** and **W6** can overlap. **Track A** can start after **W1**.

---

## Track W — Web

### W0 — Rich directory profiles (API + admin) ✅ started

**Goal:** Support profile depth beyond the reference (not 1:1), editable in Filament.

**Doctor fields:** `subspecialty`, `years_experience`, `education`, `languages[]`, `clinical_interests[]`, `procedures[]`, `consultation_fee_note`, `avatar_url`, `office_hours` (JSON), `accepts_new_patients`, `is_featured`.

**Facility fields:** `website`, `avatar_url`, `office_hours` (JSON).

**API:** Exposed on detail/list resources; `GET /doctors?featured=1` for home.

**Migration:** `2026_05_21_120000_add_profile_fields_to_doctors_and_facilities.php`

### D0 — Rich demo seed (local) ✅ started

**Goal:** `php artisan migrate:fresh --seed` yields showcase content for UI work.

- [x] `RichDemoSeeder` + `database/seeders/data/rich-profiles.php` — MK bios, tags, hours, avatars
- [x] Extra demo members (`marija@`, `stefan@`) + approved reviews on featured doctors
- [ ] Optional: expand to all seeded doctors / more pharmacies (incremental)

**Run locally:**

```bash
cd apps/api && php artisan migrate:fresh --seed
```

### W1 — Design foundation (1 PR)

**Goal:** One visual language for all pages.

- [ ] Add CSS variables in `globals.css` (Tailwind v4 `@theme`): `primary`, `accent`, `muted`, `destructive`, `warning`, `card`, `border`, `radius` — tuned for health (reference: blue ~213° 80% 50%, emerald accent ~160° 55% 40%).
- [ ] Optional: switch display font to **DM Sans** or keep Geist — decide once (reference uses DM Sans).
- [ ] Introduce minimal UI primitives under `components/ui/`:
  - `button.tsx` (variants: primary, secondary, ghost, destructive)
  - `input.tsx`, `textarea.tsx`, `select.tsx`
  - `card.tsx` (header/body/footer slots)
  - `badge.tsx` (default, success, warning, destructive)
  - `skeleton.tsx`
- [ ] Utility classes: `card-hover`, `glass` (header), `text-gradient` (hero only, subtle).
- [ ] Widen layout token: `max-w-4xl` for prose/account; `max-w-6xl` or `max-w-7xl` for directory browsing — use `layout.ts` constants.

**Acceptance:** Home + one list page use tokens only (no raw `zinc-900` buttons).

---

### W2 — App chrome (1 PR)

**Goal:** Navigation that matches a health product on desktop and phone.

- [ ] **Header:** sticky, `backdrop-blur`, border; logo mark (simple icon + wordmark); **include `/guidance`** in primary nav.
- [ ] **Active route** styling (primary tint background on current section).
- [ ] **Mobile:** sheet/drawer menu **or** bottom nav with 4–5 items (Home, Guidance, Doctors, Pharmacies, Account) — pick one pattern and stay consistent.
- [ ] **Footer:** keep legal/emergency; optional column layout on `lg+` like reference.
- [ ] Account: logout in header when session exists; reduce duplicate account links in nav if bottom bar has Account.

**Acceptance:** iPhone-width viewport: no wrapped 8-link nav bar; guidance reachable in ≤2 taps.

---

### W3 — Home & search hub (1 PR)

**Goal:** Real hub, not a link index.

- [ ] **Hero:** headline + subtitle from `mk.ts`; prominent search field routing to `/search` or doctors with `q` (wire existing search page).
- [ ] **Quick actions** (4–6 tiles): Doctors, Facilities, Pharmacies, Products, Symptom guidance, Forum — icon + gradient or solid primary/accent backgrounds (reference pattern, Zdravje copy).
- [ ] **Optional API-driven blocks** (if seed data exists): 3 featured doctors, 3 specialties — `GET` published lists with `per_page=3`; hide section when empty.
- [ ] Light motion: CSS only (`transition`, `hover`) unless you approve Framer Motion dependency.

**Acceptance:** New user understands what the platform does in &lt;10 seconds without reading footer.

---

### W4 — Directories — lists & detail (2 PRs)

**Lists PR**

- [ ] Replace plain `EntityList` rows with **`DirectoryCard`** (doctor / facility / pharmacy / product variants): title, meta line (city, specialty, type), optional rating summary, chevron/hover lift.
- [ ] **Chip filters** for specialty/city/type where API supports it; sticky filter bar under header on scroll.
- [ ] **Empty state** component: icon, title, “clear filters” action.
- [ ] Fix i18n gaps: facilities `"Name"` label, products `from X MKD` → `tFormat`.
- [ ] **Sort** (if API adds `sort` param later, stub UI disabled; else client-side name sort only where cheap).

**Detail PR**

- [ ] **Breadcrumbs:** Home → section → entity name.
- [ ] **Layout:** `lg:grid-cols-3` — main content + **sticky sidebar** (contact CTAs, `tel:`/`mailto:`, map link from address via OpenStreetMap URL — no map SDK required).
- [ ] **Doctor:** facilities as cards/chips; review summary more prominent; star display component (read-only, accessible).
- [ ] **Pharmacy:** product shelf as table or cards with price emphasis; highlight lowest price if multiple rows (when API provides comparable data).
- [ ] **Reviews block:** clearer separation form vs list; pending state for author.

**Acceptance:** Doctor list and detail feel closer to reference `Doctors.tsx` / `DoctorProfile.tsx` while using live API.

---

### W5 — Guidance, forum, account (1–2 PRs)

**Guidance**

- [ ] Step cards: `rounded-2xl` bordered panels; urgency outcome colors (self-care / see doctor / emergency) mapped to tokens.
- [ ] Fix hardcoded EN emergency CTA in `guidance-wizard.tsx`.
- [ ] Progress indicator (step N of M).

**Forum**

- [ ] Category grid cards on `/forum`.
- [ ] Topic list: status badge, reply count, last activity.
- [ ] Thread: clearer post hierarchy (author, time, body spacing).

**Account**

- [ ] Hub cards with icons; human-readable role label from `mk`.
- [ ] Review/forum lists: align with `ModerationStatusBadge` + stars.

**Acceptance:** Guidance is the most polished flow; forum/account match directory quality.

---

### W6 — Production polish (1 PR)

- [ ] `loading.tsx` + `error.tsx` for major routes (or segment groups).
- [ ] Custom `not-found.tsx` (MK copy, links home).
- [ ] Per-page `metadata` / `generateMetadata` for doctors, facilities, guidance, home.
- [ ] Focus rings and skip link; audit forms for `aria-invalid` / error text.
- [ ] Remove or gate `foundation-status.tsx` dev component.

**Acceptance:** Slow API shows skeleton; broken slug shows friendly 404; shareable doctor page has title in tab.

---

## Track A — Admin (Filament), parallel

**Goal:** Same brand cues; faster moderation — no feature creep.

| ID | Work | Notes |
|----|------|--------|
| A1 | Panel branding | Logo, primary color from W1 tokens, compact sidebar |
| A2 | Directory tables | Avatar/initial column, published badge colors, row actions |
| A3 | Moderation | Queue widget polish; preview body in table; bulk actions confirm |
| A4 | Forms | Section headings, helper text consistency with web disclaimers |
| A5 | “View on site” | Already shipped — verify with `WEB_PUBLIC_URL` locally |

Admin does **not** need shadcn in Next.js duplicated in Filament — use Filament theme CSS variables aligned to W1 hex/HSL choices.

---

## Explicitly out of scope (until product says otherwise)

| Item | Why |
|------|-----|
| Deploy / staging | User decision — local only for now |
| Meilisearch unified search UI | R3; keep SQL search hub |
| Mapbox/Google Maps embed | Cost + scope; OSM link is enough in W4 |
| Barcode scanner, vitamins module | Reference-only fantasies |
| Dark mode | Reference has it; add only if requested (W1 can reserve tokens) |
| Public registration | Path B invite-only |
| shadcn full port | Optional: add **selected** Radix primitives, not entire `components/ui` tree at once |

---

## Quick wins (can do in &lt;1 day before W1)

- [ ] Add `/guidance` to `site-header.tsx` nav.
- [ ] Fix i18n: facilities filter label, products price prefix, guidance emergency button.
- [ ] Home directory cards: `rounded-2xl`, `card-hover`, primary border on hover.
- [ ] `facilityTypeLabel()` on facilities list subtitle.

---

## Success criteria (local “fully working + polished”)

1. **Visual:** Cohesive health brand (blue + green accents), not generic zinc blog.
2. **Mobile:** Usable nav without horizontal link soup.
3. **Discovery:** Home + lists invite browsing; empty states helpful.
4. **Trust:** Disclaimers visible; guidance urgency visually distinct; emergency numbers one tap away.
5. **Engineering:** `npm run lint && npm run build` green; no regressions to API auth/reviews/forum flows.
6. **Admin:** Moderator can run queue daily without confusion (Track A).

When these pass on **local seed data**, the app is ready for **your** deploy decision — not blocked on beta verification checklist.

---

## Suggested PR sequence

| # | Scope | Est. |
|---|--------|------|
| 1 | Quick wins + W1 foundation | 2–3 days |
| 2 | W2 chrome | 1–2 days |
| 3 | W3 home | 1–2 days |
| 4 | W4 lists | 2–3 days |
| 5 | W4 detail | 2–3 days |
| 6 | W5 guidance/forum/account | 2–3 days |
| 7 | W6 polish | 1–2 days |
| A* | Filament A1–A4 (parallel after PR 1) | 2–4 days |

**Total (web track):** ~2–3 weeks focused work. Parallel admin adds ~1 week without blocking web.

---

## Related docs

- [TASKS.md](../TASKS.md) — backlog checkboxes under **Frontend UX**
- [mvp-acceptance.md](./mvp-acceptance.md) — functional flows (already met)
- [beta-verification.md](./beta-verification.md) — resume when you choose to deploy
