# API performance at realistic volume

How the public API's hot queries behave with a realistic amount of data, what
was changed, and how to measure again. Numbers are from PostgreSQL 16 on a
developer laptop (Apple Silicon, everything in shared buffers), so absolute
times are small; the **buffer counts** are deterministic and are the better
before/after signal. Timings move by ±1–2 ms between runs on a busy machine.

## Volume data: `PerfSeeder`

`database/seeders/PerfSeeder.php` is opt-in (not part of `DatabaseSeeder`) and
refuses to run in any deployment (`DeploymentEnvironment`). It adds, on top of
the normal demo seed:

| Rows | Count |
|---|--:|
| members (reviewers / forum authors) | 2,500 |
| doctors (~15 % Latin-script names, `dr. …`), each with 1–2 specialties and 1–2 facilities | 5,000 |
| clinical facilities (clinic / hospital / laboratory), 2–6 departments each | 1,500 |
| pharmacies | 800 |
| products / `pharmacy_product` offers (120 per pharmacy) | 1,500 / 96,000 |
| reviews (88 % approved, 8 % pending, 4 % rejected; ~70 % doctors) | 50,000 |
| forum topics (90 % approved) / posts (skewed: a few hot threads) | 3,000 / 20,000 |

Names, cities and titles are Macedonian (Cyrillic, with some Latin
transliterations such as `Skopje`, `dr. Marija Petrovski`). It is
deterministic (`mt_srand(360)`), writes with 1,000-row multi-row inserts (no
model events, no Scout sync), backfills the review aggregates, and finishes
with `VACUUM (FULL, ANALYZE)` / `ANALYZE` so plans are costed on real sizes.

```bash
cd apps/api
# A disposable database only — this wipes it.
php artisan migrate:fresh --seed
php artisan db:seed --class=PerfSeeder   # ~7 s locally (~3 s more for migrate:fresh --seed)
```

Running the three migrations below against an already-seeded database took
0.19 s (lookup indexes), 0.53 s (pg_trgm + six GIN indexes) and 0.60 s (review
aggregate columns + backfill of 7,300 rows).

## How it was measured

A throwaway script bootstrapped the app, sent each request below through the
HTTP kernel (7 runs, median), captured every SQL statement it issued, and ran
`EXPLAIN (ANALYZE, BUFFERS)` on each with its real bindings. The pharmacy and
product modules were switched on for the run. Taxonomy caching was flushed
before every timed run so the table shows the uncached path.

| Key | Request |
|---|---|
| `doctors_default` | `/doctors` |
| `doctors_q_city_specialty_rating` | `/doctors?q=петров&city=скопје&specialty=kardiologija&sort=rating` |
| `doctors_sort_rating` | `/doctors?sort=rating` |
| `doctors_min_reviews` | `/doctors?sort=rating&min_reviews=12` |
| `doctors_q_latin` | `/doctors?q=nikolovski` (matches Cyrillic `Николовски`) |
| `facilities_city` / `facilities_q` | `/facilities?city=Битола` / `/facilities?q=медика` |
| `pharmacies_city` | `/pharmacies?city=skopje` |
| `pharmacy_products_q` | `/pharmacies/{slug}/products?q=paracetamol` |
| `products_q` | `/products?q=витамин` (PharmacyCatalog `from_price` / `offer_count`) |
| `search_unified` / `_short` | `/search?q=петровски` / `/search?q=ana` |
| `forum_recent`, `forum_topic_posts`, `forum_category_topics`, `forum_categories` | recent feed; busiest topic page; its category; category list |
| `doctor_reviews` | `/doctors/{most-reviewed}/reviews` |
| `specialties`, `departments` | taxonomy lists |

## Results

Each cell: **sum of EXPLAIN ANALYZE execution time over the request's queries
in ms (slowest single query) · shared buffers touched**. "After" is the
default planner configuration; the last column sets `random_page_cost = 1.1`
(see [the trigram caveat](#trigram-indexes-and-the-planner)).

| Endpoint | Before | After | After, `random_page_cost = 1.1` |
|---|--:|--:|--:|
| `doctors_default` | 2.51 (1.42) · 564 | 2.19 (1.34) · 339 | 3.68 (3.25) · 339 |
| `doctors_q_city_specialty_rating` | 4.17 (2.88) · 2521 | 2.75 (1.87) · 2401 | 1.83 (0.88) · 363 |
| `doctors_sort_rating` | **27.88 (27.06) · 78230** | 4.11 (1.96) · 396 | 5.82 (3.31) · 384 |
| `doctors_min_reviews` | **39.06 (22.94) · 90469** | 4.02 (2.48) · 351 | 1.30 (0.54) · 276 |
| `doctors_q_latin` | 9.30 (7.83) · 1143 | 9.24 (7.92) · 901 | 3.54 (2.63) · 871 |
| `doctor_detail` | 0.07 (0.03) · 58 | 0.07 (0.02) · 96 | 0.24 (0.06) · 96 |
| `facilities_city` | 2.61 (2.16) · 560 | 2.34 (2.03) · 307 | 2.42 (2.04) · 414 |
| `facilities_q` | 2.77 (2.59) · 390 | 2.52 (2.44) · 150 | 2.43 (2.31) · 257 |
| `pharmacies_city` | 1.04 (0.76) · 854 | 0.83 (0.67) · 607 | 2.21 (1.80) · 607 |
| `pharmacy_products_q` | **HTTP 500** | 5.72 (2.91) · 97 | 5.60 (2.79) · 97 |
| `products_q` | **123.43 (120.93) · 30143** | 7.24 (4.71) · 3373 | 7.34 (4.80) · 3373 |
| `search_unified` | 20.25 (7.55) · 759 | 15.25 (8.09) · 588 | 3.42 (2.73) · 511 |
| `search_unified_short` | 18.25 (6.67) · 1014 | 4.88 (2.48) · 779 | 5.08 (2.54) · 779 |
| `forum_recent` | 0.57 (0.53) · 162 | 0.49 (0.45) · 157 | 0.48 (0.45) · 157 |
| `forum_topic_posts` | 0.51 (0.18) · 801 | 0.28 (0.13) · 609 | 0.24 (0.09) · 610 |
| `forum_category_topics` | 0.11 (0.07) · 173 | 0.09 (0.05) · 167 | 0.09 (0.05) · 167 |
| `forum_categories` | 0.76 (0.76) · 1131 | 0.46 (0.46) · 1049 | 0.49 (0.49) · 1049 |
| `doctor_reviews` | 0.05 (0.01) · 81 | 0.04 (0.01) · 82 | 0.05 (0.01) · 82 |
| `specialties` (uncached) | 13.15 (13.15) · 4479 | 13.30 (13.30) · 4679 | 13.32 (13.32) · 4679 |
| `departments` (uncached) | 0.03 (0.03) · 1 | 0.03 (0.03) · 1 | 0.03 (0.03) · 1 |

Served from `TaxonomyCache` (the normal case), `/specialties`,
`/departments` and `/forum/categories` answer in ~0.5 ms with **no** taxonomy
query, and a revalidation with a matching `If-None-Match` is a body-less 304.

Headlines:

- **Rating sort / `min_reviews`: 27 ms → 2 ms, 78k → 400 buffers.** Two
  correlated `AVG`/`COUNT` subqueries ran for *every* published doctor before
  `LIMIT` could apply. They now read `doctors.rating_avg` / `reviews_count`
  through `doctors_rating_avg_reviews_count_index` (backward scan + incremental
  sort, 65 buffers).
- **Product catalogue: 121 ms → 4.7 ms, 30k → 3.4k buffers.** `from_price` and
  `offer_count` filtered `pharmacy_product` by `product_id`, which no index led
  with, so each product row was a sequential scan of all 96k offers.
- **Pharmacy shelf search was a 500 on every `?q=`** (a `BelongsToMany` passed
  where an Eloquent `Builder` is required) — found by this exercise, fixed, and
  covered by `PharmacyTest::test_shelf_products_can_be_searched_across_scripts`.
- **Every list row lost its review subqueries.** Doctor/facility/pharmacy lists,
  profiles and unified search no longer touch `reviews` at all
  (`QueryBudgetTest::test_directory_listings_do_not_query_the_reviews_table`).
- **Topic pages** read posts in index order (22 buffers instead of a 212-buffer
  bitmap scan + sort for a 300-post thread); **review listings** lost their sort
  step.

### Key plans

`/doctors?sort=rating` before — every published doctor evaluated twice:

```
Limit  (actual time=26.983..27.033 rows=15)  Buffers: shared hit=77926
  ->  Sort  Sort Key: (((SubPlan 3) IS NULL)), ((SubPlan 4)) DESC, doctors.full_name
        ->  Seq Scan on doctors (actual rows=4780)
              SubPlan 3 -> Aggregate (loops=4780) -> Index Scan using reviews_reviewable_type_reviewable_id_status_index
              SubPlan 4 -> Aggregate (loops=4780) -> Index Scan using reviews_reviewable_type_reviewable_id_status_index
Execution Time: 27.057 ms
```

after:

```
select * from "doctors" where "is_published" = true and "doctors"."deleted_at" is null
order by "doctors"."rating_avg" desc, "doctors"."full_name" asc limit 15
Limit  (actual time=1.944..1.946 rows=15)  Buffers: shared hit=65
  ->  Incremental Sort  Sort Key: rating_avg DESC, full_name  Presorted Key: rating_avg
        ->  Index Scan Backward using doctors_rating_avg_reviews_count_index on doctors (actual rows=62)
Execution Time: 1.959 ms
```

`min_reviews=12` after — the count is now an index condition:

```
Index Scan Backward using doctors_rating_avg_reviews_count_index on doctors
  Index Cond: (reviews_count >= 12)          Buffers: shared hit=27
Execution Time: 0.429 ms                      (before: 15.8 ms, 46,732 buffers)
```

`/products?q=витамин`, the `from_price` subquery, before / after:

```
SubPlan 1 -> Aggregate (loops=15)
  ->  Seq Scan on pharmacy_product (actual time=0.068..3.989 rows=57 loops=15)
        Filter: (is_available AND (product_id = products.id))  Rows Removed by Filter: 95960
Execution Time: 120.934 ms

SubPlan 1 -> Aggregate (loops=15)
  ->  Bitmap Index Scan on pharmacy_product_product_id_facility_id_index  Index Cond: (product_id = products.id)
Execution Time: 4.713 ms
```

Topic page posts, before / after:

```
Sort  Sort Key: published_at  ->  Bitmap Heap Scan on forum_posts (rows=325)  Buffers: shared hit=212
  ->  Bitmap Index Scan on forum_posts_forum_topic_id_status_created_at_index
Execution Time: 0.125 ms

Index Scan using forum_posts_forum_topic_id_status_published_at_index on forum_posts  Buffers: shared hit=22
Execution Time: 0.011 ms
```

Name search (`count(*)` of `/doctors?q=nikolovski`) — default costs vs
`random_page_cost = 1.1`:

```
Seq Scan on doctors  Filter: (... ((full_name)::text ~~* '%nikolovski%') OR ((full_name)::text ~~* '%николовски%'))
  Rows Removed by Filter: 4881
Execution Time: 7.924 ms

Bitmap Heap Scan on doctors
  ->  BitmapOr
        ->  Bitmap Index Scan on doctors_full_name_trgm_index  Index Cond: ((full_name)::text ~~* '%nikolovski%')
        ->  Bitmap Index Scan on doctors_full_name_trgm_index  Index Cond: ((full_name)::text ~~* '%николовски%')
Execution Time: 0.744 ms
```

## What changed

### Indexes (`2026_10_10_100000_add_lookup_indexes_for_directory_and_forum`)

PostgreSQL and SQLite do not index foreign keys by themselves, and a composite
key only serves lookups by its leading column.

| Index | Why |
|---|---|
| `pharmacy_product (product_id, facility_id)` | PK leads with `facility_id`; the catalogue looks up by product. |
| `doctor_facility (facility_id)` | PK leads with `doctor_id`; a facility's doctors. |
| `department_facility (department_id)` | Its unique key already leads with `facility_id` (the `?department=` filter uses that); this serves the reverse side and cascades. |
| `analytics_events (user_id)` | `ON DELETE SET NULL` scanned the table per deleted user. |
| `triage_sessions (user_id)` | Same; PostgreSQL only and only while the column exists (`Schema::hasColumn`), because SQLite's native `DROP COLUMN` refuses an indexed column if a later migration removes it. |
| `reviews (reviewable_type, reviewable_id, status, published_at)` | Replaces both `(reviewable_type, reviewable_id)` from `morphs()` — a strict prefix of the composite, so pure write cost — and `(…, status)`. `published_at` is the order of every public review listing. |
| `forum_posts (forum_topic_id, status, published_at)` | Replaces `(…, created_at)`: topic pages order approved posts by `published_at`; nothing orders them by `created_at`. |
| `doctors (rating_avg, reviews_count)` | `sort=rating` and `min_reviews` (in the aggregates migration). |

### Trigram indexes (`2026_10_10_100001_add_trigram_search_indexes`, PostgreSQL only)

`CREATE EXTENSION IF NOT EXISTS pg_trgm`, then GIN `gin_trgm_ops` indexes on
every column `ScriptInsensitiveSearch` filters with `ILIKE '%term%'`
(`App\Support\TrigramSearchIndexes::COLUMNS`): `doctors.full_name`,
`doctors.city`, `facilities.name`, `facilities.city`, `products.name`,
`forum_topics.title`. A btree cannot serve a leading wildcard; with trigram
indexes each Latin/Cyrillic variant becomes a bitmap index scan and the
variants are OR-ed (plan above). `DatabaseIndexesTest` asserts the indexes
exist and that the exact SQL the search scope emits can use
`doctors_full_name_trgm_index`.

**Managed PostgreSQL must allow `pg_trgm`.** It is a *trusted* extension
(PostgreSQL 13+), so the database owner can create it without superuser, and
the major managed providers allow-list it. If the host refuses, the migration
logs a warning and skips the indexes instead of failing the deploy — search
keeps working, just unindexed — and `php artisan platform:preflight` reports a
`database.pg_trgm` warning until the extension exists (then re-run the
migration's statements, or roll it back and migrate again).

#### Trigram indexes and the planner

At this volume (5,000 doctors ≈ 150 heap pages) PostgreSQL's default cost model
still prefers a sequential scan for most name/city searches: it prices a page
read from "disk" at `random_page_cost = 4` and badly under-prices the CPU cost
of case-insensitive `ILIKE` on Cyrillic text, so the measured 8 ms seq scan
looks cheaper to it than the 0.7 ms trigram path. Two consequences:

- As the tables grow, the seq-scan estimate grows linearly while the index
  path's does not, so the planner should switch to the indexes on its own
  (not measured beyond this volume).
- On SSD-backed servers — every managed offering — set
  `random_page_cost = 1.1` (the common SSD recommendation; the database owner
  can run `ALTER DATABASE <db> SET random_page_cost = 1.1`). With it, the
  doctor name search drops from 7.9 ms to 0.7 ms and unified search from
  ~20 ms to ~3 ms of database time (last column above). This is an operations
  setting, not something a migration should impose.

### Denormalised review aggregates (`2026_10_10_100002_add_review_aggregates_to_doctors_and_facilities`)

`doctors` and `facilities` (pharmacies are facilities) gained
`reviews_count` (unsigned int, default 0) and `rating_avg` (`decimal(3,2)`,
default 0), backfilled set-based by the migration.

- **Maintained in one place:** `Review::booted()` → `App\Support\ReviewAggregates`.
  Every way a review's approval can change — `approve()`, `reject()`, the
  Filament row and bulk actions (which call those), an edit of `rating` /
  `status` / the target, a delete — is a model save or delete. Each recomputes
  the affected target(s) with **one aggregate query** (`COUNT`, `SUM` of approved
  reviews), never an increment, so a lost or repeated write cannot drift the
  numbers. Reviews have no soft deletes, so there is no restore path.
- **Bulk writes** that bypass model events (the migration, `PerfSeeder`) use
  `ReviewAggregates::recomputeAll()` / the same SQL.
- **Truncated, not rounded.** `rating_avg` stores the average truncated to two
  decimals, computed in integer arithmetic. `ReviewSummary` still reports
  `round(avg, 1)`, and truncation preserves that exactly (`x.x5` is itself a
  two-decimal value). Rounding to two decimals first would not: 100/29 = 3.448
  becomes 3.45 and then 3.5. On the seeded data, rounding would have changed
  the published average of 19 of 7,323 profiles; truncation changes none
  (verified by comparing every row against the live `AVG`).
- Unrated rows hold `rating_avg = 0`, below any real average (1–5), so
  `sort=rating` is a plain `ORDER BY rating_avg DESC, full_name` (unrated last,
  as before) that the index can serve. Ties are now at two-decimal precision
  rather than full precision, which only changes the order of doctors whose
  averages agree to two decimals (name order breaks the tie).
- `ReviewSummary::eagerLoad()` / `eagerLoadInto()` (the `withCount`/`withAvg`
  subqueries) are gone; the API output is unchanged
  (`QueryBudgetTest::test_review_summary_values_survive_the_denormalised_path`,
  `ReviewAggregatesTest`).

### Taxonomy caching

`/specialties`, `/specialties/{slug}`, `/departments` and `/forum/categories`:

- **Server:** `App\Support\TaxonomyCache::remember()` keeps the resolved payload
  for 10 minutes under a per-group version. Saving, deleting or restoring a
  `Specialty`, `Department` or `ForumCategory` — and a `Doctor` / `ForumTopic`,
  whose counts the payloads embed — bumps the version
  (`Models\Concerns\InvalidatesTaxonomyCache`). A pivot-only change that saves
  no model (attaching a specialty to a doctor without saving the doctor) shows
  up within the 10-minute TTL.
- **HTTP:** the `cache.public` middleware (`SetPublicCacheHeaders`) adds
  `Cache-Control: public, max-age=300` and a content `ETag` to **200** responses
  and answers a matching `If-None-Match` with a body-less 304. Unlike Laravel's
  `cache.headers`, it never marks a 404/503 public. Never put it on a route that
  varies by viewer.

The API has no public languages / procedures / clinical-interests endpoints
(those taxonomies are only embedded in the doctor profile), so there is nothing
to cache for them.

### Web data cache (`apps/web/src/lib/api`)

- Taxonomy fetches (`fetchSpecialties`, `fetchDepartments`,
  `fetchForumCategories`) use `next: { revalidate: 300 }` (`TAXONOMY_CACHE`).
- Anonymous directory lists (`fetchDoctors`, `fetchFacilities`,
  `fetchPharmacies`, `fetchProducts`) use `next: { revalidate: 60 }` **only when
  every parameter is in a known-safe set** (`directoryCache()`,
  `directory-cache-policy.ts`): a specialty/department slug present in the
  cached taxonomy, a facility type or sort from its enum, a boolean, page ≤ 5.
  A cached fetch reaches the API without the visitor's address, in the
  site-wide rate-limit bucket, and every distinct URL is its own cache entry;
  free text (`q`, `city`, product `category`), an unknown slug, a product
  `pharmacy` filter or a deep page would make that set unbounded, so those
  listings stay `no-store` and forward the visitor. The API also rejects
  `page` > 1000 on every list endpoint, and the web pages clamp `?page=` to
  that range (`parseListPage`).
- Token-bearing reads (`lib/api/server.ts`) are always `no-store`
  (`cache-policy.test.ts`).

### Search indexing on the queue

`SCOUT_QUEUE` now defaults to `true` (`config/scout.php`, `.env.example`), so
Meilisearch updates no longer run inside the admin's save request. It needs the
queue worker that mail already requires. Renaming, (un)publishing, deleting or
restoring a specialty or department queues `ReindexTaxonomyMembers`, which
re-sends its doctors' / facilities' documents in chunks of 500 (those
documents embed the taxonomy names). `phpunit.xml` pins
`SCOUT_QUEUE=false` and the suite/E2E run `QUEUE_CONNECTION=sync`, so tests
index inline.

## Not changed (and why)

- **`/specialties` uncached (13 ms):** the per-specialty published-doctor
  count. It is now served from `TaxonomyCache`; worth revisiting only if
  specialties start changing often.
- **Pagination `count(*)`** on unfiltered lists is a seq scan of the published
  rows (~1–2 ms at 5,000 doctors). Fine at this scale.
- **Forum recent feed / category topics** already had fitting indexes
  (`forum_topics (status, last_post_at)` and the category composite).
