# Development and Deploy Rules

These rules are mandatory for every change. `AGENTS.md` contains the short
version for coding agents; this document is the source of truth.

## Branches and pull requests

- Work on a feature/fix branch. Never commit directly to `main`.
- Open a PR and link the related issue.
- Keep PRs reviewable; include screenshots or a short video for UI changes.
- `main` must require the `ci` and `quality` checks before merging.

## Definition of done

- Code follows Laravel, Livewire and Flux conventions already used in the app.
- Inline documentation explains non-obvious domain rules, jobs, services,
  migrations and public component props.
- DRY has been reviewed: reuse existing services, traits and components before
  adding new logic.
- New behavior has tests, including a regression test for the bug/feature.
- `vendor/bin/pint --dirty` passes.
- `php artisan test --compact` passes.
- `npm run build` passes when frontend assets changed.
- Migrations have a working `down()` and have been tested with fresh + rollback.
- External services and storage are faked in tests. Never rely on a real S3
  bucket, FTP server or queue in CI.
- Destructive data operations require `--dry-run` plus an explicit `--force`
  (or equivalent) and are documented in the PR.
- Feature-specific technical docs are added under `docs/` when the feature has
  data migration, concurrency, rollback or operational requirements.

## Migrations

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

- Feature tests cover the happy path, permissions and failure cases.
- UI changes require a browser test where practical.
- Queue jobs must be tested with a fake/sync queue.
- Storage/URL tests must call `Storage::fake()` for the relevant disk.
- Tests must not depend on production bucket names, credentials or cache state.

## Deployment checklist

1. Merge only after CI and quality are green.
2. Back up the production database and confirm storage backup policy.
3. Review `php artisan migrate --pretend` output.
4. Deploy code and run `composer install --no-dev` as appropriate.
5. Run `php artisan migrate --force`.
6. Run feature-specific reconciliation commands, for example:
   `php artisan storage:recalculate --dry-run` then the real command.
7. Run `npm ci && npm run build` when assets changed.
8. Clear/rebuild caches as appropriate.
9. Restart queue workers after job-code changes.
10. Run smoke tests for the changed flow.
11. Record the deployed commit/tag and rollback commit.

## Rollback

- Roll back code to the previous known-good tag/commit.
- Prefer disabling a feature flag before reverting schema.
- Roll back migrations only after confirming data retention needs.
- Version/history files are not automatically recoverable after deletion; use
  `photos:collapse-versions --force [--delete-files]` only as an explicit,
  reviewed operation.
- Keep `docs/file-versions.md` and this document updated with any change to the
  rollback procedure.
