# Verifying a pull request

How to review a PR on this repository so that what gets merged is production-level, down to the nits. Written from what five review rounds on [#1](https://github.com/karpalakllc/health-navigator/pull/1) and [#2](https://github.com/karpalakllc/health-navigator/pull/2) actually caught — every technique here has found a real defect at least once.

**Related:** [beta-verification.md](./beta-verification.md) · [api-contract.md](./api-contract.md) · [architecture.md](./architecture.md) · [infra/deploy.md](../infra/deploy.md)

---

## The standard

- **Nothing merges with a known issue.** "Small" is a priority, not an exemption. A misleading code comment is a defect: the next person acts on it.
- **A claim is not evidence.** Commit messages, PR descriptions and code comments are the thing under review, not the proof. Several of the worst findings on this repo were comments that confidently described behaviour the code did not have.
- **Every finding carries a reproduction.** If you cannot show it happening, you have a suspicion, not a finding — say so explicitly, or go get the evidence.
- **Review the head that is open now.** These branches move during review. Re-check the SHA before you write anything up, and state which SHA you reviewed.

---

## The one rule

**Verify against a running system, not against the diff.** Reading code tells you what it intends. Running it tells you what it does. On this repo the gap between the two has included:

- a rate limit that metered every user on the platform into one bucket, in code whose comment said the opposite;
- a CSP that blocked the error reporting the same PR had just enabled;
- a `revalidate = 3600` on a route that re-fetched on every request;
- a `robots.txt` shipping `Sitemap: http://127.0.0.1:3000/sitemap.xml`;
- a "fixed" mail throttle that still delivered twelve emails to one inbox.

None of those were visible in the diff. All were obvious within two minutes of a running server.

---

## 0. Set up a disposable worktree

Never review in the working copy someone is developing in, and never trust an already-installed `vendor/` or `node_modules/`.

```bash
# from the repo root
PR=1234                     # or the SHA you are reviewing
git fetch origin
git worktree add /tmp/review-$PR <sha>

cd /tmp/review-$PR/apps/api
cp .env.example .env
composer install --no-interaction --prefer-dist     # from the lockfile, not `update`
php artisan key:generate

cd ../web
npm ci                                              # `ci`, so the lockfile is what is tested
```

**Do not symlink `vendor/` or `node_modules/` from another worktree.** Composer's autoloader stores absolute paths, so a symlinked `vendor` resolves `App\…` classes to the *other* tree — you will get failures that do not exist and waste an hour proving they are not real. This has happened; install properly, it takes two minutes.

Tear down when finished:

```bash
git worktree remove --force /tmp/review-$PR
docker rm -f zdravje-pg-review
```

---

## 1. Run every gate yourself

Not "CI is green" — run them. CI can be misconfigured, skipped, or testing a different base (see [§6](#6-stacked-prs)).

### API

```bash
cd /tmp/review-$PR/apps/api
./vendor/bin/pint --test
php artisan test
```

### API on PostgreSQL

Production is PostgreSQL and the code branches on the driver — `ILIKE` vs `LIKE`, `->>'q'` vs `json_extract`, and NULL ordering, which is **inverted** between the two engines. SQLite-only runs cannot see those paths.

```bash
docker run -d --name zdravje-pg-review \
  -e POSTGRES_DB=zdravje_test -e POSTGRES_USER=zdravje -e POSTGRES_PASSWORD=secret \
  -p 55432:5432 postgres:16

DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 \
DB_DATABASE=zdravje_test DB_USERNAME=zdravje DB_PASSWORD=secret \
php artisan test
```

### Migrations, both directions

A migration that cannot roll back is fine when it says so; one that *fails* to roll back is a deploy hazard.

```bash
php artisan migrate:fresh --force      # against the Postgres above
php artisan migrate:rollback --step=5 --force
php artisan migrate --force
```

### Web

```bash
cd /tmp/review-$PR/apps/web
npm run format:check
npm run lint
npm run typecheck
npm test
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000 NEXT_PUBLIC_SITE_URL=https://www.example.com npm run build
```

Then the negative case, because this one is designed to fail:

```bash
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000 npm run build   # must abort: NEXT_PUBLIC_SITE_URL missing
```

**Record the numbers** (tests, assertions) in the review. A count that drops between rounds is a deleted test nobody mentioned.

---

## 2. Prove the new tests are real

A test written after the fix usually passes on the broken code too. Check:

```bash
# restore the pre-fix version of the files the PR changed
git show <parent-sha>:apps/api/app/Http/Controllers/Api/V1/AuthController.php \
  > apps/api/app/Http/Controllers/Api/V1/AuthController.php

php artisan test --filter=TheNewTest      # expect failures, with the reported symptom
git checkout -- apps/api                  # restore
```

If the new tests pass against the old code, they document behaviour rather than pin it — say so. When they fail, check the *message*: "429 where the owner should get 200" is a real regression test; a failure for an unrelated reason is not.

Also look for tests that cannot fail:

- a loop with no assertion inside, or over a list that can be empty — enumerations need a `assertGreaterThanOrEqual(n, count($items))` guard;
- `assertOk()` on a page that would render for any logged-in user;
- helpers that quietly do nothing (`RateLimiter::clear('api-login')` takes a **bucket key**, not a limiter name — that idiom was a no-op in ten places here).

---

## 3. Exercise the change live

Boot both tiers and drive the actual behaviour.

```bash
# API
cd /tmp/review-$PR/apps/api
printf '\nDB_CONNECTION=sqlite\nCACHE_STORE=file\nMAIL_MAILER=log\nTRUSTED_PROXIES=*\n' >> .env
touch database/database.sqlite && php artisan migrate --force
php artisan serve --port=8001 &

# Web, against it
cd ../web
NEXT_PUBLIC_API_URL=http://127.0.0.1:8001 NEXT_PUBLIC_SITE_URL=https://www.example.com npm run build
PORT=3001 NEXT_PUBLIC_API_URL=http://127.0.0.1:8001 npm start &
```

Use `APP_ENV=staging` when you need the production-only middleware (`TrustHosts` self-disables under `local` and in tests).

### Rate limits and client identity

Always go **through the web tier** for anything a browser reaches through a route handler — sign-in, reviews, forum posts, avatar upload. That is the path that broke.

```bash
# distinct clients must not share a bucket
for i in 1 2 3 4 5 6; do
  curl -s -o /dev/null -w "%{http_code} " -X POST http://127.0.0.1:3001/api/session/login \
    -H "Content-Type: application/json" -H "X-Forwarded-For: 203.0.113.$i" \
    -d '{"email":"a'$i'@example.com","password":"wrong-password-here"}'
done

# and the owner of an account under attack must still get in
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://127.0.0.1:3001/api/session/login \
  -H "Content-Type: application/json" -H "X-Forwarded-For: 198.51.100.7" \
  -d '{"email":"owner@example.com","password":"<correct>"}'
```

Ask of every limiter: **what is the key, and who controls it?** A key an attacker can rotate is not a limit; a key the victim shares with the attacker is a lockout.

### Mail volume

`MAIL_MAILER=log` turns the inbox into a countable file. Count messages, not log lines:

```bash
before=$(grep -c '^Message-ID:' storage/logs/laravel.log)
# ...trigger the flow N times...
after=$(grep -c '^Message-ID:' storage/logs/laravel.log)
echo "delivered: $((after - before))"
```

Trigger it from **every** endpoint that can cause a send. Registration, resend and password reset all reach the same mailers here, and a throttle on one route is not a throttle on the address.

### Response headers

```bash
curl -sD - -o /dev/null http://127.0.0.1:3001/login | grep -i "content-security-policy"
curl -s http://127.0.0.1:3001/login | grep -c '<script'        # compare with...
curl -s http://127.0.0.1:3001/login | grep -c '<script[^>]*nonce'   # ...this
```

For a CSP, check both directions: what it blocks, **and that everything the app itself needs to send is allowed** — Sentry's ingest host, the analytics beacon. A CSP that silently disables error reporting looks exactly like an application with no errors.

### The admin panel

Filament is Livewire; you cannot log in with a plain form POST. Add a throwaway route, drive the panel, then restore the file:

```php
// routes/web.php — TEMPORARY, remove after
Route::get('/__probe-login', function () {
    Auth::login(\App\Models\User::where('email', 'admin@example.test')->firstOrFail());
    return redirect('/admin');
});
```

```bash
curl -s -c /tmp/cj -b /tmp/cj -o /dev/null -L http://127.0.0.1:8001/__probe-login
for p in /admin /admin/doctors /admin/reviews /admin/manage-site-settings /admin/analytics-overview; do
  echo "$p $(curl -s -b /tmp/cj -o /dev/null -w '%{http_code}' http://127.0.0.1:8001$p)"
done
git checkout -- routes/web.php
```

Then fetch the assets the page references and confirm they are **200 at the version stamp of the installed package** — committed Filament assets go stale silently after an upgrade.

### Signed links and hosts

```bash
# the mailed link must never take its host from a header
curl -s -o /dev/null -X POST http://127.0.0.1:8001/api/v1/auth/email/resend \
  -H "Content-Type: application/json" -H "X-Forwarded-Host: evil.example.net" \
  -d '{"email":"pending@example.com"}'
grep -o "http[s]*://[^ \"<>]*email/verify[^ \"<>]*" storage/logs/laravel.log | tail -1

# and a forged Host must be refused outright (APP_ENV=staging)
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8001/api/v1/health -H "Host: evil.example.net"
```

### Localisation

Send `Accept-Language: mk` at every error path the PR touches and read the whole message, not the status code:

```bash
curl -s -X POST http://127.0.0.1:8001/api/v1/auth/register \
  -H "Accept: application/json" -H "Content-Type: application/json" -H "Accept-Language: mk" \
  -d '{"name":"X","email":"a@example.com","password":"abc","password_confirmation":"abc"}'
```

A half-translated sentence — English frame, Macedonian attribute — is the signature of a missing translation key, not a wrong one.

---

## 4. Mechanical sweeps

Cheap, and each has caught something here.

| Check | Command | Catches |
|---|---|---|
| Dependency advisories | `composer audit` · `npm audit` | vulnerable versions merged silently |
| Published-config drift | diff the app's `config/<pkg>.php` against `vendor/<pkg>/config/` | a package major that added or renamed keys |
| Vendor asset freshness | `php artisan filament:assets` then `git status` | committed assets stale against the installed version |
| Route table drift | `./scripts/api-routes.sh` vs the table in `api-contract.md` (pinned by `RouteTableIsCurrentTest`) | docs describing guards the routes no longer have |
| Translation coverage | render each rule's message under `mk` and reject `[A-Za-z]` (`TranslationCompletenessTest`) | English falling through a missing key |
| Bulgarian letters | `grep -nP '[йщъыэюяѐ]' apps/api/lang/mk/* apps/web/src/i18n/mk.ts` | copy that is not Macedonian |
| Debug leftovers | `grep -nE '\b(dd\|dump\|var_dump)\(' <changed files>` · `grep -n console.log` | what it says |
| Dead tests | `grep -rn "markTestSkipped\|\.skip("` | coverage that is not coverage |
| Platform bindings | count entries with `os`/`cpu` in `package-lock.json` | `npm ci` failing on Linux CI after a macOS `npm install` |
| PHP floor | `composer why-not php 8.4.10` | a runtime constraint stricter than anything requires |

**Formatting churn.** When a PR reformats files, prove the churn is inert before reading it as a diff: strip whitespace, trailing commas and JSX spacers from both sides and compare hashes per file. Anything that still differs, read line by line. A 3,500-line Prettier pass is a fine place to hide one changed conditional.

---

## 5. Read the diff for what tools cannot see

Run the tools first so your attention is free for these.

- **Comments and commit messages as claims.** Check each one against the code. "Keyed on the user first", "`use` is included", "the edge overwrites rather than appends" — all three were wrong here, and each was the only documentation of a security-relevant decision.
- **The other paths to the same behaviour.** A guard on one route is not a guard on the action. When a fix lands in one place, list every caller that reaches the same effect and check them all.
- **Limiter keys.** See [§3](#rate-limits-and-client-identity).
- **Enumeration and oracles.** Do the two branches of an auth flow differ in status, body, headers, timing, or mail volume? Registration, resend and password reset must be indistinguishable.
- **N+1 and per-row work.** Anything called inside a resource `toArray()` or a policy runs per row. `QueryBudgetTest` pins the list endpoints; new ones need their own budget.
- **Config coupling.** Does the change make an environment variable load-bearing? `APP_URL`, `TRUSTED_PROXIES`, `CLIENT_IP_HEADER`, `NEXT_PUBLIC_SITE_URL` all now break something specific when wrong. If a PR adds one, it owes `deploy.md` a line.
- **Migration safety.** Guarded for environment, reversible or explicitly not, and correct on a *seeded* database — not only a fresh one. Data-repair migrations should be targeted (`replace()`), never a blanket overwrite of copy an admin may have edited.
- **Copy accuracy.** A confirmation that asserts something untrue in one branch ("we sent a link" when nothing was sent) is a defect, not a nit.

---

## 6. Stacked PRs

When a PR is based on another branch:

```bash
git merge-base origin/<base-branch> origin/<pr-branch>   # must equal the base branch head
gh pr checks <n>                                          # a PR with no checks looks like one that passed
git merge --no-commit --no-ff <pr-sha>                    # trial merge, then --abort
```

**A change and the thing that makes it work must ship on the same branch.** The rule broke here once: the PHP version constraint landed on #1 while the CI runner version stayed on #2, and #1's API jobs failed at `composer install` before running a single test.

Re-check the base before merging — these branches move. Rebase so the green checks were computed against the base the PR will actually land on.

---

## 7. Configuration that fails silently

Treat any of these appearing in a diff as a prompt to check `infra/deploy.md` in the same review:

| Variable | Wrong value looks like |
|---|---|
| `TRUSTED_PROXIES` | every rate limit shares one bucket, or (`*` on a reachable origin) becomes spoofable |
| `CLIENT_IP_HEADER` | a caller-supplied header, so every caller picks its own bucket |
| `APP_URL` | in production, **every** request 400s; verification links read as invalid |
| `NEXT_PUBLIC_SITE_URL` | localhost baked into `robots.txt`, the sitemap and every canonical URL |
| `MAIL_*` | queued mail fails into `failed_jobs`; nobody is told |
| `MEDIA_DISK` | uploads vanish on redeploy |

---

## 8. Traps that produce false findings

Wrong findings cost more than missed ones — they burn the author's trust. These all produced a confident, wrong conclusion here:

- **Symlinked `vendor/`** → autoload resolves to another tree → phantom "class not found" and unrelated failures. Install properly.
- **The Sanctum guard memoises its user** across requests inside one test. Without `$this->app['auth']->forgetGuards()`, a second bearer token still resolves the first user — which reads exactly like a rate limiter keyed on IP instead of user. Laravel's middleware priority *does* run the guard before `ThrottleRequests`.
- **`TrustHosts` disables itself** under `local` and while running tests. A feature test proves nothing about it; force it on in a subclass.
- **Next request memoization** dedupes identical `fetch` calls within a render, so `generateMetadata` plus the page component is one API call, not two — measure before reporting a duplicate fetch.
- **`curl` header quoting.** `-H Accept:application/json` inside an unquoted shell variable does not arrive. A validation error rendering as a 302 redirect means Laravel did not see a JSON request — your curl is wrong, not the app.
- **Time-dependent limits** need `travel()`/`Carbon::setTestNow`, and the array cache honours both. Sleeping 61 seconds in a probe is fine; guessing is not.

---

## 9. Writing the review

Severity, and what each means here:

| | Meaning |
|---|---|
| **Blocking** | breaks a user-facing flow, loses data, or exposes something. Does not merge. |
| **High** | works, but wrong in production: a fix that cannot function, a silent misconfiguration. Fix in the same PR. |
| **Medium** | real defect with a bounded blast radius, or a residual worth naming. Fix or file with a reason. |
| **Nit** | comment that lies, stale doc, dead key, missing test for a live behaviour. Fix — this is the standard. |

Each finding: **what**, **where** (`file:line`), **evidence** (the reproduction, verbatim), **fix** (the smallest correct one). State what you could not verify — native copy quality, the real edge's headers, anything needing production — rather than implying you covered it.

---

## 10. Before merging

- [ ] Every gate re-run by the reviewer, on both database engines, with the numbers recorded
- [ ] New tests proven to fail against the pre-fix code
- [ ] The changed behaviour exercised on a running system, through the tier a user actually reaches it by
- [ ] `composer audit` and `npm audit` clean
- [ ] Mechanical sweeps clean (§4)
- [ ] Docs touched by the change updated in the same PR — `api-contract.md`, `deploy.md`, `architecture.md` decision log
- [ ] Every finding closed, including nits, or explicitly deferred with a reason in the PR
- [ ] PR description matches what the branch now contains
- [ ] For a stacked PR: base is current, CI actually ran, trial merge is clean
