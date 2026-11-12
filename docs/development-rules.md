# Development and Deploy Rules

These rules are mandatory for every change. `AGENTS.md` contains the short
version for coding agents; this document is the source of truth.

## Branches and pull requests

- Work on a feature/fix branch. Never commit directly to `main`.
- Open a PR and link the related issue.
- Keep PRs reviewable; include screenshots or a short video for UI changes.
- `main` must require the `ci` and `quality` checks before merging.

## Ask before publishing

**Never commit, push, open a pull request, merge or deploy without asking
the maintainer first.** Working on a branch and running the checks locally is
always fine, but publishing to the remote is a decision of the maintainer.

- Ask for permission before the first `git commit` of a change.
- Ask for permission before every `git push`, including to a feature branch.
- Ask for permission before opening a pull request, and say which branch it
  targets and which issue it closes.
- Ask for permission before merging a pull request, even when `ci` and
  `quality` are green. Green checks are a requirement to merge, not a
  permission to merge.
- Ask for permission before deploying, running a destructive command against
  a production database or applying a migration outside the development
  environment.

The maintainer reviews the diff, decides when the work is published and
decides when it reaches `main`. Report the state of the work and wait.

## Definition of done

- Code follows Laravel, Livewire and TallStackUI conventions already used in the app.
- Inline documentation explains non-obvious domain rules, jobs, services,
  migrations and public component props.
- DRY has been reviewed: reuse existing services, traits and components before
  adding new logic.
- New behavior has tests, including a regression test for the bug/feature.
- `vendor/bin/pint --dirty` passes.
- `composer test` passes (full Pest suite, capped at 2 parallel workers).
- `npm run build` passes when frontend assets changed.
- Migrations have a working `down()` and have been tested with fresh + rollback.
- External services and storage are faked in tests. Never rely on a real S3
  bucket, FTP server or queue in CI.
- Destructive data operations require `--dry-run` plus an explicit `--force`
  (or equivalent) and are documented in the PR.
- Feature-specific technical docs are added under `docs/` when the feature has
  data migration, concurrency, rollback or operational requirements.

## Migrations

### Always verify against MySQL, never only SQLite

CI and the test suite run on SQLite (`phpunit.xml` uses `:memory:`). SQLite
accepts DDL that MySQL rejects, so a migration can pass every check and still
fail on deploy. This actually happened: `#341` created `index(['disk','path'])`
on `varchar(2048)` columns, passed CI, and failed on the server with
`1071 Specified key was too long; max key length is 3072 bytes`.

- Any migration with a driver branch (`$driver === 'mysql'`) **must** be run
  against a real MySQL/MariaDB before merging. `migrate --pretend` and the Pest
  suite only exercise the SQLite branch and prove nothing about MySQL.
- InnoDB's index limit is 3072 bytes. In `utf8mb4` that is 768 characters, so
  `string(2048)` (8192 bytes) always needs a prefix index:
  `rawIndex("`path`(191)")`.
- **Composite indexes need a prefix on every long column**, not just the first.
- SQLite has no prefix syntax: `rawIndex('`path`(191)')` throws
  `no such function: path`. The two drivers genuinely need different DDL.
- Validate by running `up()` **and** `down()` against real column types, then
  check `SHOW INDEX` reports `sub_part = 191` and `EXPLAIN` reports
  `type: ref` instead of a full scan.

- Prefer one consolidated migration per feature before merge.
- All schema changes must be additive unless there is a reviewed data plan.
- Backfills must be idempotent and safe to run on existing data.
- Scope generic JSON/settings columns to their feature (for example
  `gallery_preferences`, not `preferences`).
- `down()` must reverse the schema without dropping unrelated feature data.
- Data migrations that delete or rewrite records must provide a dry-run path.
- Run the migration smoke check:

```bash
touch database/database.sqlite
php artisan migrate:fresh --force
php artisan migrate:rollback --step=1 --force
php artisan migrate --force
```

`--step=1` verifies the latest migration's `down()` without being blocked by
legacy migrations that are not fully reversible.

## Testing

- Run the full suite with `composer test` (Pest in parallel, 2 workers so the
  run fits in this machine's memory). While iterating,
  use `./vendor/bin/pest --compact --filter=testName`.
- Feature tests cover the happy path, permissions and failure cases.
- UI changes require a browser test where practical. Browser tests are tagged
  `->group('browser')` so `composer test:feature` can skip them while iterating
  and `composer test:browser` runs them before the PR.
- Queue jobs must be tested with a fake/sync queue.
- Storage/URL tests must call `Storage::fake()` for the relevant disk.
- Tests must not depend on production bucket names, credentials or cache state.

### When to run the browser tests

The browser suite (Pest + Playwright, `--testsuite=Components`) is slow: each
test boots a real page in headless Chromium, so it takes minutes and it is the
main reason local iteration feels sluggish.

- **While developing**, run only what you changed: `--filter=testName` on a
  feature test, or the specific `.test.php` file. Do not run the browser suite
  on every save.
- **Before opening a pull request**, run it once:
  `composer test:browser` (or `composer test` for
  everything). That is the moment the browser tests earn their cost.
- If a browser test fails only under a full run and passes alone, re-run it
  alone first: `Target page, context or browser has been closed` and geometry
  timeouts are environment flakiness (RAM / parallel workers), not a
  regression. Say so in the PR instead of chasing it.

### Testing payments locally

Two ways to exercise the payment flow without moving real money:

- **Stripe test mode**: enable it in *Branding → Payments*. The team then uses
  the test secret key and a test connected account, so the checkout only
  accepts test cards (`4242 4242 4242 4242`, any future expiry and any CVC).
  The test account still has to complete the Stripe hosted onboarding, which
  accepts fake data. The onboarding flag of the team is synced with Stripe
  every time the *Stripe Connect* screen is loaded, so finishing the onboarding
  in another tab is detected.
- **Fake payments**: set `PAYMENTS_FAKE=true` in the local `.env` (only
  honoured when `APP_ENV=local`). The payment is then created directly, with
  a `fake_` intent id, and the whole flow (payment → invoice) can be tested
  without Stripe. The flag is ignored in the test suite and in production.

## Admin translation overrides

The admin translations panel writes JSON overrides to
`storage/app/translations/{en,es}.json`. The translation loader reads this
folder after `lang/`, so edits win at runtime. The files are not tracked by
git and must be included in backups. If the app runs on multiple servers, the
folder must be shared or the overrides will diverge.

## Storage alerts

The storage warning email is sent once per plan when usage reaches 95%. The
dashboard banner can be dismissed until the plan changes. Both flags reset when
`custom_storage_limit` or `lifetime_at` changes. Deploy the storage-alert
migration before restarting queue workers.

## Deployment checklist

1. Merge only after CI and quality are green.
2. Back up the production database, media storage and
   `storage/app/translations`.
3. Review `php artisan migrate --pretend` output.
4. Confirm the production environment has `APP_ENV=production` and
   `APP_DEBUG=false`, so the local-only helpers (like the fake payments mode)
   stay disabled.
5. Deploy code and run `composer install --no-dev` as appropriate.
6. Run `php artisan migrate --force`.
7. Run feature-specific reconciliation commands, for example:
   `php artisan storage:recalculate --dry-run` then the real command.
8. Run `npm ci && npm run build` when assets changed.
9. Clear/rebuild caches as appropriate.
10. Restart queue workers after job-code changes.
11. Run smoke tests for the changed flow.
12. Record the deployed commit/tag and rollback commit.

## Rollback

- Roll back code to the previous known-good tag/commit.
- Prefer disabling a feature flag before reverting schema.
- Roll back migrations only after confirming data retention needs.
- Version/history files are not automatically recoverable after deletion; use
  `photos:collapse-versions --force [--delete-files]` only as an explicit,
  reviewed operation.
- Keep `docs/file-versions.md` and this document updated with any change to the
  rollback procedure.
