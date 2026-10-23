# First reviews — invitations, mobile flow and the share link

No doctor has a review yet. These features make writing the first ones easy
without weakening anything that keeps reviews honest. Each one is a switch in
**Admin → Platform → Site settings → Review invitations** (all on by default)
and is left out of the page entirely when off (decided on the server, so
nothing flashes).

| Switch (`site_settings` / `GET /settings/public`) | Default | What it does |
|---|---|---|
| `reviews_prompt_on_profile` | on | The W8-B card „Дали сте биле кај …?“ on doctor, facility and pharmacy profiles (after 15 s or 30 % scroll, below the fold, once per profile per device). |
| `reviews_prompt_after_search` | on | Back on the doctor list or `/search` after opening a doctor from them: „Сте биле кај {name}? Споделете го вашето искуство“. |
| `reviews_share_link` | on | „Препорачајте рецензија“ on doctor profiles (Web Share API, copy fallback, manual-copy field as last resort). |
| `reviews_mobile_sticky_cta` | on | „Напишете рецензија“ in the bottom bar of doctor profiles with no published review (phones only). |

When the settings cannot be read the web treats all four as off.

## Prompt after a search

- `SearchVisitMarker` on the results notes the search (sessionStorage).
  `SearchReturnRecorder` on a doctor profile remembers that doctor if it was
  opened within 30 minutes of the results — unless it is the viewer's own
  profile, they already reviewed it, or they still have to choose a username.
- `SearchReviewPrompt` at the end of the results (never above them, so nothing
  shifts) asks once. Caps (`lib/review-growth.ts`): once per session, once per
  7 days per device, never again for a doctor after „Не сега“ or a review, and
  not when the profile's own prompt already asked. Counted only when the card
  is on screen. No dialog, no focus moved; „Не сега“ is labelled with the
  doctor's name. Without working storage it stays quiet.

## The mobile „Напишете рецензија“ flow (390 px)

Before: signed out, the profile showed only „Најава за да оставите
рецензија“; after sign-in the visitor had to find the form again, the
register link lost the way back, and a comment under 10 characters was only
refused by the API.

Now:

1. The form is open to everyone (`#napishi-recenzija`). Signed out, its last
   step reads „Најава“ and the button „Продолжете кон најава“.
2. Sending signed out keeps rating, aspects and comment as a **draft** in
   localStorage (one draft, 24 h, deleted when sent) and goes to
   `/login?redirect=/doctors/{slug}#napishi-recenzija`. „Регистрирај се“ and
   the post-registration „Најави се“ carry the same redirect; after the e-mail
   check, `/verify-email` offers „Продолжете ја рецензијата за {name}“.
3. Back on the profile the draft is restored with a notice; the member checks
   it and sends it. Nothing is sent automatically.
4. Inline validation in Macedonian (rating required; comment empty or ≥ 10
   characters, `maxlength` 2000 with a counter); 48–56 px touch targets.
5. The pending card says „Вашата рецензија ќе биде објавена по проверка“,
   explains moderation, and links to „Мои рецензии“ (impact).

Unchanged anti-abuse: verified account, one review per member per profile
(one resubmission after a refusal), `throttle:api-reviews`, moderation of
every review, ALTCHA at registration, the 5-in-24 h burst flag.

## Share link

`https://<site>/doctors/{slug}?utm_source=share&utm_medium=referral&utm_campaign=napishi-recenzija#napishi-recenzija`

- **Identical for everyone.** No sender id, token or counter: nothing to tie
  a reward to, nothing that identifies who shared. The shared text promises
  nothing („Секоја рецензија се проверува пред објава“).
- **Patient to patient only.** The button is not shown to a doctor on their
  own managed profile, and there is no bulk or dashboard tool for doctors or
  facilities. (Anyone can still copy a public URL; the burst signal below is
  the safety net.)
- **Same checks.** A review that started from the link goes through exactly
  the same validation, limits and moderation.
- **Moderation signal.** With statistics consent (`z360:consent:v1`), a
  landing from the link is kept in sessionStorage for a day and the review is
  sent with `via_shared_link: true` (doctors only; ignored while the switch is
  off). Staff see „Started from the shared link“ on the review. Three or more
  such reviews on one profile within 48 h set `shared_link_burst_flagged_at`
  („Shared-link burst“ badge and queue filter) — a prompt for extra review,
  never an automatic rejection. The threshold is lower than the general
  5-in-24 h burst flag on purpose: five of any kind already raise that one.

## Code

- API: `SiteSetting` (flags), `ManageSiteSettings` (toggles),
  `ReviewController::viaSharedLink`, `ReviewBurstDetector::checkSharedLink`,
  Filament `ReviewsTable` / `ReviewInfolist`; tests in
  `tests/Feature/Api/V1/ReviewGrowthTest.php`.
- Web: `lib/review-growth.ts` (storage, caps, share URL, sticky rule),
  `components/reviews/search-review-prompt.tsx`, `review-share-button.tsx`,
  `continue-review-link.tsx`, `review-form.tsx` (guest mode, draft,
  validation), `ProfileCallBar` (`reviewHref`); E2E
  `e2e/reviews-growth.spec.ts`.
