# Notice and action

How Zdravje360 handles reports of reviews and forum content, takedown requests,
and the right of reply for reviewed doctors and facilities. This is the
operating process. The public terms (`apps/web/src/content/legal/terms.tsx`,
„Модерација и пријавување“) describe exactly this; they are still a draft for
legal review, and the two must change together.

## What can be reported, and by whom

- **Content:** published reviews (doctor, facility, pharmacy profiles), forum
  topics (the opening post) and forum replies. Pending or already hidden
  content cannot be reported; the API answers 404, as the public page does.
- **Who:** any signed-in account with a verified email, about someone else's
  content (reporting your own is refused; the web does not offer it). On the
  web the „Пријави“ link sits at the end of each review and post; signed-out
  visitors, and anyone whose session has expired, are sent to sign in and
  brought back.
- **Reasons** (`reason` codes): `spam` (spam or advertising), `abuse` (abuse or
  harassment), `false_information`, `personal_data` (someone's personal or
  health data), `other`. An optional note of up to 500 characters (plain text).
- **Limits:** one report per account per item — a repeat is accepted but
  changes nothing (idempotent). At most 10 reports per 10 minutes and 40 a day
  per account.
- The reporter is emailed the outcome once the report is resolved („Вашата
  пријава е прегледана“: the content was removed, or it stays up). The mail is
  neutral and never names the moderator. The author is never told who
  reported them.

## The queue

Admin panel → **Community → Reports** (open reports first, oldest first; the
menu badge shows how many are open). Staff are told without opening the panel:
`reports:alert-staff` runs every 10 minutes and emails one summary of the
reports that arrived since the last run (counts by reason, the open total and
a link to the queue; no reporter, no note, no reported text) to everyone with
`content_reports.view`, plus `REPORT_ALERT_EMAIL` when set. The daily
moderation digest (07:00) also counts open reports. Visible to roles holding
`content_reports.view`; resolving needs `content_reports.resolve`. Both are
held by the built-in **Administrator** and **Moderator** roles. **Forum
Moderators** (community members scoped to categories) do not see the queue,
because it also contains review reports; they keep moderating their
categories as before.

Each row shows the reason, the reporter's note, what was reported (with an
excerpt and a link to the full text on the report page) and how many open
reports the same item has. Two actions, each closing **every** open report on
that item at once:

- **Keep content** — the content stays published. Use when the report does not
  hold up (a negative but honest review is not a reason to remove it).
- **Hide content** — unpublishes the item through the same rejection path as
  pre-moderation: status `rejected`, `moderated_by_id` / `moderated_at` set,
  the reason stored as `rejection_note`. The reason is **required**: it starts
  as a general one and can be picked from a list of common reasons or typed. Also requires the right to
  moderate that content (`reviews.update` for reviews; forum moderation for
  topics and replies). Effects:
  - the moderator also picks the **public reason** (спам, навреда, лажни
    информации, лични податоци, незаконска содржина, друго); it starts from
    the most common report reason. A removed review stays in the profile's
    list, and a removed reply in its thread, as a placeholder: „Рецензијата е
    отстранета на {датум} — причина: {категорија}“. The placeholder never shows
    the text, rating, author or the reason written to the author. Content
    refused before it was ever published leaves no placeholder;
  - otherwise the item disappears from every public list, profile, topic page
    and search (a removed topic leaves no placeholder; forum topics leave the
    Meilisearch index on save);
  - the removal is counted on the public „Транспарентност“ page (`/transparency`,
    monthly figures by public reason);
  - review averages and counts are recomputed; a hidden reply no longer counts
    in the topic's reply total;
  - the author receives the „Содржината е отстранета“ email with the reason —
    this is the **statement of reasons**. Write reasons in Macedonian, without
    naming the reporter;
  - every reporter of the item receives the outcome email.

There is no interim or partial hide: a report ends as kept or removed. Two
moderators resolving the same item at once cannot remove it twice (the item
is re-read under a row lock).

Who resolved each report and when is stored on the report
(`resolved_by_id`, `resolved_at`); reports are never deleted from the panel.
Each resolution is also written to the audit log (**Platform → Activity
log**, `audit.view`, kept 365 days), with review moderation and reply
changes, doctor-profile edits and featured/sponsored toggles.

## Turnaround

The terms promise one goal: **every report reviewed within 24 hours.** Take
`personal_data` (someone's identity or health information), threats and
harassment first. When unsure, hide and review with a second moderator; a
hidden item can be restored by approving it again from Reviews or Forum
(that also clears its placeholder).

## Contested reviews (doctor or facility disagrees)

A doctor or facility that disagrees with a review usually has two options, in
this order:

1. **Official response (right of reply).** They send their response to the
   team (email to the platform address). Staff with `reviews.respond` open the
   review in the admin panel → **Add official response**, paste it, and save.
   It is published under the review, labelled „Одговор од <profile name>“, and
   can be edited or removed by staff at any time. Plain text only (markup is
   stripped), at most 2,000 characters. Check that the sender really
   represents the profile before publishing. Do not edit the meaning; fix
   only obvious typos, and never add a patient's personal or health data.
   **Or the doctor writes it.** Staff can link a doctor's own member account
   to their profile (admin panel → doctor → **Assign account**, after
   verifying the person outside the platform, e.g. through the workplace or
   the Лекарска комора register; members can ask with „Ова е мој профил“ on
   the profile, queued under **Directory → Profile claims**). The doctor then
   writes one reply per published review of their profile on „Мој профил“.
   While *Settings → Doctor accounts → Require admin approval for doctor
   replies* is on (the default) the reply waits in **Community → Doctor
   replies**: approve it, or reject it with a reason the doctor sees. Check
   it reveals or confirms nothing about a patient — not even that the
   reviewer was one. An edit goes back to waiting. Approved, it shows as
   „Одговор од лекарот“ signed with the profile name. The doctor cannot hide,
   edit or delete reviews and sees reviewers only by their public name; they
   can report a review like any member. A response staff entered stays
   staff's (the doctor cannot overwrite it).
2. **Takedown request.** A review is removed only if it breaks the rules:
   spam or advertising, abuse or harassment, statements of fact that are shown
   to be false (not opinions), personal or health data of anyone, or content
   unrelated to the profile. Disagreement with an honest opinion or a low
   rating is not a reason. Record the request as a report (or handle it from
   the review's page) and resolve it with **Keep** or **Hide** as above.

Legal orders (court or authority) are handled by an administrator: hide the
content immediately and keep a copy of the order outside the platform.

## Review bursts (staff-only signal)

When a profile receives 5 or more reviews (any status) within 24 hours, every
review in that window is flagged: a „Burst“ badge in **Reviews** and a
„Review bursts“ filter. Nothing happens automatically — no review is hidden,
delayed or rejected because of the flag. Look at the burst before approving:
similar wording, new accounts, the same day. One review per account per
profile and a verified e-mail address are required to write a review.

## Helpful votes („Корисно“)

Members can mark a published review as helpful once (and take the mark back).
They cannot mark their own review. Votes are not moderated; a hidden review
simply stops being shown with its count.
