# Forum seeding: categories and starter topics

The forum opens with a fixed set of categories and 2–3 pinned starter topics in
each one. A staff account shown as **„Администратор“** writes them. The content
is based on what people in Macedonia already discuss most (Kajgana reply/view
counts plus coverage on the news portals): finding a good doctor, dental care,
hospitals, surgeries, ФЗОМ and Мој термин, chronic conditions, pregnancy and IVF.
It also covers the gaps no forum covers yet (varicose veins, gallbladder, carpal
tunnel, treatment abroad, waiting lists).

- Seeder: `apps/api/database/seeders/ForumStarterSeeder.php`
- Content (categories, titles, bodies, keywords): `apps/api/database/seeders/data/forum_starter.php`
- Tests: `apps/api/tests/Feature/Seeders/ForumStarterSeederTest.php`

## Running it

```bash
cd apps/api
php artisan db:seed --class=ForumStarterSeeder --force
```

`--force` is only needed in production. The seeder has no local-demo guard, so
it runs the same way everywhere. It is not part of `DatabaseSeeder`, so you run
it once yourself on each environment.

If `SCOUT_QUEUE=true`, a queue worker must be running for the search index to
pick up the topics. Otherwise run `php artisan scout:import "App\Models\ForumTopic"`
afterwards.

## What it does

- **Categories** are keyed by slug. A missing one is created and published. An
  existing one is changed only if it still has a name, description or order
  that the seeders themselves gave it earlier. This is how `general-health`
  loses its old disclaimer and `ishrana-i-zhivot` becomes „Исхрана, вежбање и
  слабеење“ with the same slug. Anything the team edited in the panel is kept,
  including a category that was unpublished.
- **Author**: the account at `FORUM_STARTER_AUTHOR_EMAIL` (default
  `forum-admin@zdravje360.test`; in production, use an address the team
  controls). It is created with the username „Администратор“,
  `user_kind = staff` (the forum shows the team badge), no role (so no panel
  or moderation rights) and a random password nobody knows. The name is on the
  reserved list, which only stops members from taking it. If another account
  already holds a look-alike name, the seeder stops with an error.
- **Topics** are keyed by author and slug. They are created approved and
  pinned. The rules topic („Добредојдовте — како да го користите форумот“) is
  also locked. Keywords are saved as confirmed. They are created through the
  model, so publication timestamps, the category-count cache and the Scout
  index entry all update the same way as for any approved topic. Each topic
  is dated one minute before the previous one, so listings show them in the
  order written in the content file.
- **Re-running** creates only what is missing. It never rewrites a topic that
  was edited, unpinned, locked, moved or rejected. A topic that was deleted
  outright comes back on the next run, so to retire one for good, remove it
  from the content file too.

## Writing rules for starter content

The tests check the second rule (no emergency numbers or disclaimer phrases):

- Open, warm questions that invite experiences: where, which doctor or clinic,
  cost, waiting time, recovery. No requests for a diagnosis, no medical
  advice, no made-up patient stories.
- No emergency numbers and no disclaimers. Topics about doctors or clinics
  end with a short line asking people to name doctors and clinics fairly, and
  to leave a review for an individual doctor on that doctor's profile.
- Leave out polarised subjects (vaccine debates, alternative medicine, COVID).
