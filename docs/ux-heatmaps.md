# UX heatmaps — anonymous click and scroll statistics

Self-built and anonymous: where visitors click, where they click on things that
do nothing (dead clicks), where they click repeatedly in frustration (rage
clicks), and how far they scroll — per page template and device class. No
third party is involved.

## What is collected

The web tracker (`apps/web/src/lib/ux/tracker.ts`) is loaded by
`components/layout/ux-insights.tsx` after hydration, when the browser is idle.

Per click:

| Field | Meaning |
|---|---|
| `r` | Page **template** (`/doctors/[slug]`). Never the address, slug, query or fragment. |
| `vc` | `mobile` (< 640 px), `tablet` (640–1023), `desktop` (≥ 1024) |
| `wb` | Viewport width rounded down to 80 px |
| `x` | Click position as a whole % of the page width (0–99) |
| `y` | 10 px band from the top of the document (0–1999); `null` on a fixed/sticky element (header, tab bar), which has no stable page position |
| `k` | Target key `context/element` (below) |
| `d` | Dead click: nothing interactive at or above the target |
| `g` | Rage click: the third click within 700 ms and 30 px (counted once per burst) |

Per page view: the deepest scroll milestone reached (0/25/50/75/90/100 %) and
the time to the first click in buckets (< 1 s, 1–3, 3–10, 10–30, > 30 s, none).

**Target keys** never contain text. `context` is the nearest `data-track`
attribute (`doctor-card`, `facility-card`, `pharmacy-card`, `forum-topic`,
`site-nav`, `tab-bar`, `breadcrumbs`, `pagination`, `home-how-it-works`,
`home-forum-band`) or else the landmark (`header`, `nav`, `main`, `footer`,
`aside`, `search`, `dialog`, `form`, `page`). `element` is what was clicked:
`link`, `button`, `input-<type>`, `select`, `label`, `summary`, `role-<role>`,
`focusable`, `pointer` (a `cursor: pointer` element without semantics), or —
for dead clicks — `heading`, `text`, `img`, `icon`, `table`, `media`, `area`,
`disabled`. Both halves come from **closed lists** (contexts, element kinds,
input types, ARIA roles) kept in `apps/web/src/lib/ux/schema.ts` and
`apps/api/app/Support/Ux/UxSchema.php`; `UxRoutesParityTest` checks they
match, and the API refuses a batch with any other key, so a key can never
carry free text and the number of distinct rows is fixed. A `data-track`
value outside the list is ignored (the landmark is used). To get finer keys
for a component, add `data-track="<name>"` (lowercase, hyphens) to it, add
the name to both lists, and give it words in
`apps/api/app/Support/Ux/UxTargetDescriber.php` (`UxTargetDescriberTest`).

## Privacy guarantees

- Only anonymous **daily counters** are stored (`ux_heatmap_cells`,
  `ux_element_stats`, `ux_page_stats`); there is no row per click or visit.
- Never read or sent: text content, `aria-label`/`alt`/`title`, form values,
  keystrokes, URLs/slugs/queries, ids or classes, cookies, IP address, user or
  session ids, user agent or any fingerprint. No cookie or browser storage is
  used by the tracker; its rage/scroll bookkeeping lives in memory only and no
  identifier is in the payload.
- Global Privacy Control or Do Not Track on → the tracker is never loaded; the
  route handler also drops batches carrying `Sec-GPC: 1` / `DNT: 1`.
- Not tracked at all: `/account/**`, `/login`, `/register`,
  `/forgot-password`, `/reset-password/**`, `/verify-email`, the doctor
  claim/correction/objection forms, `/forum/new`, `/design-system`, unknown
  pages (allow-list in `apps/web/src/lib/ux/routes.ts` = `apps/api/config/ux.php`).
- Text-selection drags are ignored; script-dispatched clicks are ignored.
- Batches go by `navigator.sendBeacon` to the same-origin `/api/ux/events`
  (shared origin guard, JSON only, 16 KB cap, rebuilt field by field), which
  relays them to `POST /api/v1/ux/events`. The API validates every value
  against a fixed vocabulary (anything else → the whole batch is refused) and
  rate-limits per visitor network — the IPv4 address or the IPv6 /64 — at
  60/min and 600/h (the `api-ux-events` limiter). The address is never stored
  with the counters. The limiter's cache key is an HMAC-SHA256 of the network
  under `APP_KEY`, so it cannot be turned back into an address without the
  key; it expires with its window (at most an hour), and with the database
  cache store expired rows are deleted hourly by `cache:purge-expired`, so a
  key is gone at most two hours after the last batch.
- Retention: 180 days (`analytics:purge-old-events`, `--ux-days`,
  `UX_RETENTION_DAYS`).

## Settings

| Variable | Where | Default |
|---|---|---|
| `NEXT_PUBLIC_UX_SAMPLE_RATE` | web (build time) | `1` (every tab); `0` turns tracking off |
| `UX_RETENTION_DAYS` | API | `180` |
| `UX_OVERLAY_TTL_MINUTES` | API | `120` |

## How to use it (owner / staff)

1. Admin → **Platform → UX анализа** (needs `analytics.view`).
2. Pick the **page template**, **device** and **period**. You see:
   - **Мртви кликови** — what people click that does not react (e.g.
     `Картичка на лекар → наслов (не е интерактивно)`: people expect the
     doctor's name to open the profile).
   - **Бесни кликови** — repeated rapid clicks on one spot: something slow,
     broken or confusing.
   - **Најкликани елементи**, the **scroll funnel** (share of views that
     reached 25/50/75/90/100 % of the page) and **time to first click**.
3. **Heatmap on the page itself:** check the address (for profile templates a
   real published profile is filled in; all profiles of a template share one
   map), press **„Создај линк за топлинска мапа“**, then
   **„Отвори ја страницата…“**. The public page opens with a heat layer for
   your device class (resize the window to switch between mobile, tablet and
   desktop data). The panel at the bottom left switches between all / dead /
   rage clicks and „само слична ширина“ (±80 px of your window width);
   dashed lines show how many views reached each quarter of the page.
   You can browse to other pages in that tab and the layer follows.
   **„Затвори“** removes it for the tab.

The link holds a token signed with `APP_KEY`, valid 120 minutes, in the URL
**fragment** (`#ux-heatmap=…`), which browsers never send to a server; the
page moves it into the tab's sessionStorage and removes it from the address
bar. The overlay code is a separate chunk loaded only when such a token is
present, and the public HTML contains no heatmap data: the overlay fetches
`/api/ux/heatmap` with the token in a header, and the API re-verifies the
signature, the expiry and that the staff member still has `analytics.view` on
every request (403 otherwise). A tab with the overlay is never tracked.

Caveats: x is a percentage of the page width, so on centred desktop layouts
clicks from very different window widths spread out — use „само слична
ширина“. Content that differs between pages of one template (long vs short
profiles) blurs the lower part of the map; the target tables are the more
reliable signal there.
