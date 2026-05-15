# Frontend UI/UX transformation — master plan

**Status:** Planning document (not yet implemented as a single release).  
**App:** `apps/web` (Next.js public site).  
**Locale:** Macedonian-first (`src/i18n/mk.ts`); no `next-intl` unless explicitly reprioritized.  
**Constraint:** Real API data only; no fictional maps, bookings, or diagnosis UX — see [architecture.md](./architecture.md) and [triage-safety.md](./triage-safety.md).

This plan **supersedes the “good enough” bar** from the earlier incremental [frontend-ux-plan.md](./frontend-ux-plan.md) (W1–W6). Those tasks built foundation; **this document defines the target product-grade experience** you described: powerful, modern, professional, and user-friendly — informed by Lovable-style *patterns* only, not a clone.

---

## 1. Goals

| Goal | Success looks like |
|------|---------------------|
| **Trust** | Clear hierarchy, medical-adjacent calm aesthetics, obvious emergency path, no “startup gimmick” clutter. |
| **Discovery** | Users find doctors, facilities, pharmacies, products, and guidance without learning a separate “search product.” |
| **Cohesion** | One design system: header, footer, lists, detail, auth, forum share typography, spacing, and interaction language. |
| **Mobile-first** | Primary tasks in thumb reach; no horizontal nav dumps; readable tap targets (min 44px). |
| **Maintainability** | Prefer composable UI in `components/ui` + `components/layout` + domain folders; extend existing tokens in `globals.css`. |

**Non-goals (unless roadmap changes):** public registration, Meilisearch-powered typeahead, paid maps SDK, barcode/vitamins flows, Next.js BFF.

---

## 2. Navigation & information architecture

### 2.1 Primary nav (header)

**Visible labels (MK order, adjustable for length):**

1. **Почетна** → `/`  
2. **Лекари** → `/doctors`  
3. **Установи** → `/facilities`  
4. **Аптеки** → `/pharmacies`  
5. **Производи** → `/products`  
6. **Насоки** → `/guidance`  
7. **Форум** → `/forum`  

**Remove** `/search` as a **top-level nav link**. Search becomes a **global affordance** (see §3), not a destination in the main menu.

Optional: group “directory” under one “Услуги / Директориум” mega-menu on large screens *only if* MK copy stays short — avoid hiding everything behind one vague label unless user testing asks for it.

### 2.2 Search model (replacement for “Search page” in nav)

**Recommended pattern:** **command-palette / spotlight** opened from a **search icon** in the header (keyboard: `/` or `Ctrl+K` / `Cmd+K` where it doesn’t conflict with the browser).

**Contents of the overlay (staged rollout):**

| Stage | Behaviour |
|-------|-----------|
| **MVP (no new API deps)** | Form: text query + optional city (reuse fields from current `/search`). **Results:** grouped sections — “Лекари”, “Установи”, “Аптеки”, “Производи” — each row deep-links to the existing list routes with `?q=` and `city=` query params (reuse `directorySearchHref` logic from `src/lib/search.ts`). |
| **Later (R3+)** | Same UI shell wired to Meilisearch or unified search endpoint if product adds one. |

**Route strategy:**

- Keep **`/search`** as an optional **shareable fallback** or **redirect target** from empty states, *or* remove from nav only and leave route for bookmarks — product choice. **Primary UX** = modal/drawer, not “another page” in the nav.  
- Hero on home can keep a **single field** that opens this same modal **pre-filled** or submits the same multi-destination links inline (current behaviour can evolve to “open search overlay” on focus).

### 2.3 Account & auth placement

- Logged **out:** primary CTA **“Најава”** (button style) in header; no duplicate weak text links.  
- Logged **in:** compact **account menu** (avatar/initial + dropdown) with: Сметка, Мои рецензии, Мој форум, **Одјава**.  
- Mobile: same entry in **header right**; bottom bar optional (see §4.2).

---

## 3. Global shell — header (spec)

**Problems today:** functional but reads “internal app”: long link row, `/search` as peer route, footer/header visual mismatch (zinc vs tokens).

**Target structure (desktop):**

```
[ Logo + wordmark ]  [ Primary nav — 6–7 items, compact ]     [ 🔍 ] [ Најава | Account ▼ ]
```

- **Height:** ~64–72px; sticky; `backdrop-blur` + subtle border; background matches `card` / `background` tokens.  
- **Logo:** keep Z mark or introduce simple SVG mark (same doc); wordmark “Zdravje360” with tightened tracking.  
- **Active state:** underline + soft pill **or** bottom indicator — pick one system.  
- **Search:** icon-only button with `aria-label`; opens overlay.  
- **Beta banner:** if `NEXT_PUBLIC_CLOSED_BETA`, integrate as slim dismissible strip **above** header or inside header top row — avoid pushing nav into two messy rows.

**Mobile:**

- **Not** a second full-width nav scroller for 7 text links as the only pattern. Prefer: **hamburger / sheet** with grouped links + persistent **search icon** and **login**.  
- Alternative: **bottom bar** with 4 items (e.g. Почетна, Директориум, Насоки, Профил) + “More” sheet for the rest — decide in build; document choice in README for testers.

---

## 4. Global shell — footer (spec)

**Problems today:** single column of legal text; acceptable for MVP, not for “professional” positioning.

**Target layout (lg+): 3–4 columns**

| Column | Content |
|--------|---------|
| **Brand** | Short positioning line (MK), optional newsletter placeholder *only if* product adds list later — otherwise omit. |
| **Директориум** | Лекари, Установи, Аптеки, Производи (text links). |
| **Ресурси** | Насоки за симптоми, Форум, Пребарување (opens search overlay — same as header). |
| **Правно** | Приватност, Услови, Ограничување; copy that reviews/prices/guidance are informational. |

**Always visible (full width above or below columns):**

- Emergency strip: **194 / 112** with high contrast (not aggressive red block — calm alert style).  
- Closed-beta note if applicable.

**Visual:** use design tokens (`border-border`, `bg-muted/30`, `text-muted-foreground`), not raw `zinc-50`/`zinc-200`, so footer matches header and main.

---

## 5. Page-by-page blueprint

Each page: **purpose**, **layout**, **hero/intro**, **primary CTA**, **components**, **empty/error**, **SEO**.

### 5.1 Home (`/`)

- **Purpose:** Establish trust + route to the five directory pillars + guidance + forum.  
- **Layout:** Hero (headline + supporting line + **one** primary action: “Пребарај” opens search overlay or focuses search). **Trust strip** (short: moderated content, informational only). **Quick actions** as large tappable cards (existing pattern, refined grid + iconography). **Featured doctors** carousel or grid with “Види ги сите”. Optional: **Featured facilities** when API supports flag or editorial pick later.  
- **SEO:** Strong `title` / `description` (MK).  
- **Empty:** N/A for hero; featured block hides if API fails (graceful).

### 5.2 Doctors — list (`/doctors`)

- **Purpose:** Scannable directory with filters that feel lightweight.  
- **Layout:** Page title + one-line value prop; **sticky filter bar** (collapsible on mobile); **responsive card grid**; pagination or “load more” (keep pagination unless product changes).  
- **Improvements:** Filter chips for “Прима нови пациенти”, “Истакнат” if query params exist; saved filters via URL only (no cookies).  
- **Empty:** Keep demo hint + illustration placeholder (CSS shape).

### 5.3 Doctor detail (`/doctors/[slug]`)

- **Purpose:** Credibility + next step (contact, facilities, reviews).  
- **Layout:** Hero (photo, name, specialty, badges); **two columns** md+: main (bio, education, procedures); **sticky aside** (contact, hours map, facility links, review summary).  
- **A11y:** Heading order, review section landmarks.

### 5.4 Facilities — list & detail

- Align with doctors: card grid, type filter as **segmented control** or pills.  
- Detail: hero + sidebar map link + doctors list + reviews.

### 5.5 Pharmacies — list & detail

- Emphasize **location** and **product catalog** entry; shelf table with readable typography.  
- Price disclaimer visible but not dominating.

### 5.6 Products — list & detail

- List: cards + category emphasis.  
- Detail: **comparison table** (existing) styled as enterprise SaaS table (zebra, clear “best price”).

### 5.7 Forum — category, topics, thread

- **Purpose:** Community trust + moderation visibility.  
- **Layout:** Category cards; topic list with pinned/locked **icons**; thread with clear post hierarchy, quoted safety banner.  
- **Auth CTAs:** login prompts as **inline cards**, not raw links.

### 5.8 Guidance (`/guidance`)

- Wizard: **stepper** visual, urgency colours for emergency path (already started).  
- Intro: not a wall of text — card + checkbox + primary actions.

### 5.9 Search overlay (new primary UX)

- **Trigger:** header icon + home hero.  
- **Structure:** modal (`role="dialog"`, focus trap, `aria-modal`). Sections of results; “Пребарај уште” deep links.  
- **Implementation note:** Prefer **native `<dialog>`** or small in-house modal first; **Radix/shadcn Dialog** only after explicit dependency approval per [TASKS.md](../TASKS.md) / project rules.

### 5.10 Login (`/login`)

- **Purpose:** Fast, trustworthy auth.  
- **Layout:** Split layout optional: left brand panel (gradient + trust bullets), right form; or single centred card with logo.  
- **Form:** labels, error states, loading on submit, link to terms/disclaimer footnote.

### 5.11 Account hub & subpages

- Consistent **sidebar** on desktop for account; mobile: subnav under title.  
- Cards for “Мои рецензии / Мој форум” with real counts when API allows.

### 5.12 Legal (`/privacy`, `/terms`, `/disclaimer`)

- Readable **prose** width, table of contents sticky on lg+, print-friendly.

### 5.13 System: loading, error, 404

- **Per-route `loading.tsx`** where data-heavy (directories, forum).  
- **404** with suggested links (home, doctors, guidance).  
- **error.tsx** with retry + support path.

---

## 6. Design system evolution

### 6.1 Typography

- **Display / headings:** Either keep **Geist** or introduce **DM Sans** / **Source Sans 3** for a slightly warmer health feel — **one decision** for the whole site.  
- Scale: `text-sm` body, `text-base` for important lists, `text-3xl–4xl` for page heroes; limit weights to 500/600 for headings.

### 6.2 Colour & elevation

- Reinforce `primary` (trust blue), `accent` (care green), `destructive` / **alert** token for emergency only.  
- Cards: single shadow level + `rounded-2xl`; avoid mixing 5 border radii.

### 6.3 Iconography

- Prefer **inline SVG** sprite or **Lucide-style** thin strokes (if adding icons: use **inline SVG** or a **tree-shakeable** set — **new icon package only with approval**).

### 6.4 Motion

- 150–200ms transitions on hover/focus; **no** gratuitous parallax.  
- Respect `prefers-reduced-motion`.

---

## 7. Component backlog (minimum to ship transformation)

| Component | Use |
|-----------|-----|
| `Dialog` / `Sheet` | Search overlay, mobile menu. |
| `Input`, `Label` | Forms, search. |
| `Tabs` | Optional facility type filters. |
| `Accordion` | Footer mobile, FAQ later. |
| `DropdownMenu` | Account menu. |
| `Breadcrumb` | Detail pages (structured data friendly). |
| `Toast` / inline alert | Form errors, copy feedback. |

Build **in-house** with Tailwind first where trivial; introduce Radix primitives only after approval.

---

## 8. i18n & content

- All **new** chrome strings in `mk.ts` under `nav`, `footer`, `search.*`, `shell.*` namespaces.  
- Avoid hard-coded MK in JSX except for legally fixed numbers (194/112).

---

## 9. Rollout phases (implementation order)

Suggested **4 milestones** (each shippable):

| Phase | Scope | Outcome |
|-------|-------|---------|
| **T1 — Shell** | New header (nav without search link), search overlay v1, footer refactor, account dropdown | Site feels “designed” on every route. |
| **T2 — Home + templates** | Home hero + trust + actions; standardise `PageShell` / section spacing | First impression matches quality bar. |
| **T3 — Directory depth** | List + detail templates for doctors, facilities, pharmacies, products | Core product credible. |
| **T4 — Auth + forum + guidance** | Login/account polish; forum hierarchy; guidance stepper | Full funnel consistent. |

Parallel: **Track A** Filament branding in `apps/api` — align primary with web tokens ([TASKS.md](../TASKS.md)).

---

## 10. Dependencies & Risks

- **New npm packages:** require explicit approval (project rule). Default path = native `dialog`, CSS-only patterns, existing Tailwind.  
- **Search overlay without Meilisearch:** Results are **four parallel deep links** into filtered lists — still useful; set user expectations in MK microcopy (“Пребарување по име низ директориумите”).  
- **Scope creep:** Illustrations, blog, dark mode — defer unless added to TASKS.

---

## 11. References (internal)

- Current incremental plan: [frontend-ux-plan.md](./frontend-ux-plan.md)  
- Tasks / checkbox backlog: [TASKS.md](../TASKS.md) → Frontend UI transformation  
- Architecture: [architecture.md](./architecture.md)

---

## 12. Decisions to confirm with you (product)

1. Keep `/search` route for SEO/bookmarks vs redirect to `/?search=open`.  
2. Mobile: **hamburger + sheet** vs **bottom navigation** (or hybrid).  
3. Font: stay on **Geist** vs switch display font for warmer health brand.  
4. Whether closed-beta banner stays global or moves into account/login only for logged-in users.

Once these are decided, implementation can follow **T1→T4** without reopening architecture.
