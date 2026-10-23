# Forum upgrades

Additions to the forum (2026-10). Moderation is unchanged: new topics and
replies still go through the moderation settings, and nothing below publishes
content. The three server-backed features each have a switch in the admin
(Site settings › Forum community), on by default; the web hides a feature
completely when its switch is off and the API answers its endpoints with 404.

| Feature                                      | Switch                           | Where                          |
| -------------------------------------------- | -------------------------------- | ------------------------------ |
| Follow a topic                               | `forum_follow_enabled`           | thread, Мој форум, Известувања |
| „Најкорисен одговор“                         | `forum_accepted_answers_enabled` | thread, topic lists            |
| Profile cards for pasted links               | `forum_profile_cards_enabled`    | under posts                    |
| Quoting, drafts, „Ново“ since the last visit | — (device-only, no server state) | thread, composer               |

## Following a topic

- „Следи ја темата“ on the thread. Authors follow their own topic, and a
  member follows a topic when they reply to it; both can stop following.
- A reply becoming public (created approved, or approved later by a
  moderator) notifies every follower except its author, through
  `MemberNotifier`: an in-app line (`forum_reply`) and, when the member's
  settings allow it, an e-mail with a signed unsubscribe link.
- One unread line per topic: further replies raise its count and send no
  e-mail. Opening the topic (or the notifications page) reads it, so the next
  reply starts a new line and one new e-mail. A busy thread cannot flood an
  inbox.
- The line and the e-mail name the topic, never who replied.
- Member switch: „Нови одговори во темите што ги следите“ (on by default).
- Subscriptions are deleted with the account and on anonymisation, and are in
  the account export.
- API: `PUT`/`DELETE /forum/categories/{c}/topics/{t}/follow`,
  `GET /me/forum/following`; the thread's `viewer.is_following`.

## „Најкорисен одговор“

- The topic's author, or anyone who may moderate it, marks one published
  reply written by someone else; it can be unmarked or replaced.
- The thread shows it under the opening post (excerpt, author, link to the
  reply on whichever page it is) and tags the reply; lists show
  „Има најкорисен одговор“.
- A reply that is taken down or refused stops being the answer.
- API: `PUT /forum/categories/{c}/topics/{t}/accepted-answer` (`post_id`),
  `DELETE` the same; the thread's `accepted_answer`, `viewer.can_accept`,
  replies' `is_accepted`, lists' `has_accepted_answer`.

## Profile cards

A link to this site's doctor, facility or pharmacy profile in a topic or reply
(`https://<site>/doctors/<slug>`, `www.` too, or a bare `/doctors/<slug>`
starting a word) is shown as a small card under the post: name, kind,
specialty, city. Only published profiles, at most three per post, resolved on
every read (nothing stored; the body stays plain text). Links to other sites
are ignored.

## Device-only helpers

- **Quoting** — „Цитирај“ puts the post into the reply form as `> ` lines
  („име напиша:“ first, the post's own quotes left out, cut at 600
  characters). Lines starting with `>` render as a quote block.
- **Drafts** — unsent replies and new topics are kept in this browser's
  localStorage (`z360:forum-drafts:v1`, 14 days, at most 20) and restored
  with a „discard“ link; sending clears them.
- **„Ново“** — for topics this browser opened before, the activity it saw
  (`z360:forum-seen:v1`, keyed by a hash of the topic path, 90 days, at most
  300). Topic rows newer than that and replies published after the previous
  visit get „Ново“.

All storage access is guarded; without storage these simply do nothing.
