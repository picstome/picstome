---
paths:
  - 'database/migrations/**'
---

# Migrations

## Verify migrations against MySQL before merging, never SQLite
CI and the test suite run on SQLite (phpunit.xml: DB_CONNECTION=sqlite, :memory:). SQLite accepts DDL that MySQL rejects, so a migration can pass every check and still fail on deploy.

- Any migration with a driver branch (`$driver === 'mysql'`) MUST be executed against a real MySQL/MariaDB before merging. `migrate --pretend` and the Pest suite only exercise the SQLite branch and prove nothing about MySQL.
- InnoDB index limit is 3072 bytes. With utf8mb4 that is 768 characters. `path` columns here are `string(2048)` = 8192 bytes, so they need a prefix index (`rawIndex("`path`(191)")`).
- Composite indexes need a prefix on EVERY long column, not just the first one. Missed this in #341: `index(['disk','path'])` was left without a prefix and failed with error 1071 (Specified key was too long).
- SQLite has no prefix syntax: `rawIndex('`path`(191)')` throws `no such function: path`, so the two drivers genuinely need different DDL.
- When validating, start a throwaway MariaDB and run `up()` and `down()` against the real column types, then check `SHOW INDEX` reports `sub_part = 191` and `EXPLAIN` reports `type: ref` instead of a full scan.
