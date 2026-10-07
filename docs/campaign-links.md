# Campaign links (UTM) — how to tag what you share

Plausible (when it is switched on) reports each page as origin + path only. The
one exception is four campaign tags, so you can see which post, group or mail
brings people in (Plausible → **Campaigns** / **Sources**):

| Tag | Use it for | Examples |
|---|---|---|
| `utm_source` | Where the link is posted | `viber`, `facebook`, `instagram`, `newsletter`, `plakat` |
| `utm_medium` | The kind of channel | `social`, `email`, `print`, `chat` |
| `utm_campaign` | The push it belongs to | `lansiranje`, `forum-oktomvri`, `recenzii-esen` |
| `utm_content` | Which of several links/posts | `kopce-gore`, `post-2`, `qr-cekalna` |

Example (after your domain): `/forum?utm_source=viber&utm_medium=chat&utm_campaign=forum-oktomvri`

## Rules (enforced in `apps/web/src/lib/analytics/redact-url.ts`)

- **Short slugs only.** 1–64 letters (Latin or Cyrillic), digits, `.`, `_` or
  `-`. A value with spaces, `@`, `+`, `/`, `%`, or six or more digits in a row
  is dropped before anything is sent. Lower-case Latin with hyphens is the
  easiest to read in reports.
- **Never put a person in a tag**: no names, e-mail addresses, phone numbers,
  member ids or symptoms. A tag describes *where the link was posted*, never
  *who it was sent to* — one link per channel, not per recipient.
- **`utm_term` is not kept** (it is meant for search keywords, i.e. what
  someone looked for). Neither is anything else in the query string
  (`?q=`, filters, `fbclid`, `ref`…).
- Tags never change what a page shows and never make it a separate page for
  search engines: the canonical URL ignores `utm_*` (`listCanonicalPath`).
- Do not tag internal links on the site itself; that would overwrite the
  visitor's real source.

## Playbook

- Use a fixed vocabulary for `utm_source`/`utm_medium` (the table above) so the
  reports group cleanly; vary only `utm_campaign` and `utm_content`.
- For a printed poster or a QR code in a waiting room, use
  `utm_medium=print` and a `utm_content` per location (`qr-ordinacija-centar`)
  — no names of patients or staff.
- Asking people to write reviews or answer forum questions is fine; tying any
  reward, discount or prize to writing a review or to a rating is not
  („рецензиите не се купуваат“, consumer law — docs/legal/research-memo.md).
  Good targets for a campaign link: `/forum?view=unanswered`
  („Прашања без одговор“) and the profile pages people already know.
- Plausible keeps only aggregate counts per tag; no cookies are set and nothing
  links a visit to an account.
