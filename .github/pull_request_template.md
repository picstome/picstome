## Summary

<!-- What changed and why? Link the issue. -->

Closes #

## Checklist

- [ ] Feature/fix branch; no direct commits to `main`
- [ ] Issue linked and PR description explains the user-facing change
- [ ] Inline docs added for non-obvious code
- [ ] DRY reviewed / existing components and services reused
- [ ] Feature test and regression test added
- [ ] External services and storage faked in tests
- [ ] `vendor/bin/pint --dirty` passed
- [ ] `php artisan test --compact` passed
- [ ] `npm run build` passed if assets changed
- [ ] Migrations have a working `down()` and fresh + rollback verified
- [ ] Destructive data operation has dry-run + explicit force path
- [ ] `docs/` updated when operational, migration or rollback steps changed

See `docs/development-rules.md` for the full rules.
