# Notice and action

How Zdravje360 handles reports of reviews and forum content, takedown requests,
and the right of reply for reviewed doctors and facilities. This is the
operating process; the public terms (`apps/web/src/content/legal/terms.tsx`) are
still a draft and must be aligned with it by legal counsel before launch.

## What can be reported, and by whom

- **Content:** published reviews (doctor, facility, pharmacy profiles), forum
  topics (the opening post) and forum replies. Pending or already hidden
  content cannot be reported; the API answers 404, as the public page does.
- **Who:** any signed-in account with a verified email. On the web the
  „Пријави“ link sits at the end of each review and post; signed-out visitors
  are sent to sign in and brought back.
- **Reasons** (`reason` codes): `spam` (spam or advertising), `abuse` (abuse or
  harassment), `false_information`, `personal_data` (someone's personal or
  health data), `other`. An optional note of up to 500 characters (plain text).
- **Limits:** one report per account per item — a repeat is accepted but
  changes nothing (idempotent). At most 10 reports per 10 minutes and 40 a day
  per account.
- The reporter is not told the outcome, and the author is never told who
  reported them.

## The queue

Admin panel → **Community → Reports** (open reports first, oldest first; the
menu badge shows how many are open). Visible to roles holding
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
  the moderator's note stored as `rejection_note`. Also requires the right to
  moderate that content (`reviews.update` for reviews; forum moderation for
  topics and replies). Effects:
  - the item disappears from every public list, profile, topic page and search
    (forum topics leave the Meilisearch index on save);
  - review averages and counts are recomputed; a hidden reply no longer counts
    in the topic's reply total;
  - the author receives the „Содржината не е објавена“ email with the note —
    this is the **statement of reasons**. If the moderator leaves the note
    empty, a neutral default is used. Write notes in Macedonian, without
    naming the reporter.

Who resolved each report and when is stored on the report
(`resolved_by_id`, `resolved_at`); reports are never deleted from the panel.

## Turnaround

| Report | Target |
|--------|--------|
| `personal_data` (someone's identity or health information), threats, harassment | same working day |
| everything else | within 3 working days |

When unsure, hide first and review with a second moderator; a hidden item can
be restored by approving it again from Reviews or Forum.

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
2. **Takedown request.** A review is removed only if it breaks the rules:
   spam or advertising, abuse or harassment, statements of fact that are shown
   to be false (not opinions), personal or health data of anyone, or content
   unrelated to the profile. Disagreement with an honest opinion or a low
   rating is not a reason. Record the request as a report (or handle it from
   the review's page) and resolve it with **Keep** or **Hide** as above.

Legal orders (court or authority) are handled by an administrator: hide the
content immediately and keep a copy of the order outside the platform.

## Helpful votes („Корисно“)

Members can mark a published review as helpful once (and take the mark back).
They cannot mark their own review. Votes are not moderated; a hidden review
simply stops being shown with its count.
