---
paths:
    - 'database/migrations/**/*.php'
    - 'tests/Feature/Migrations/**'
---

# Migrations

## A data migration uses the query builder and is tested on PostgreSQL

A migration that moves or rewrites rows uses `DB::table()` and plain arrays, never Eloquent models or `app/Actions`: models change with the code, the migration must not. `2026_09_16_091233_backfill_import_born_statement_text_omit_payment_instruments.php` calls an Action and is an exception from before this rule. The only exception is a frozen conversion class of plain arrays without models or Actions (`App\Support\CategoryList\LegacyCategoryConversion`, shared by the Category list migration and the restore of snapshots v1–v5): its docblock says that changing it changes the meaning of a migration that has already run, so it is not edited after release. A test in `tests/Feature/Migrations/` seeds the old shape, runs the migration and asserts the invariants. For the Category list migration these are the ones of ADR-0063, step (e): every Budget Line keeps its amounts, flags and `archived_at`, and Financial Operations and Month Plan items point at the same list Category in the same Budget, every amount to the grosz. Run the test on PostgreSQL (AGENTS.md → Commands); SQLite proves nothing here.

The model to copy is `tests/Feature/Migrations/CategoryListMigrationTest.php`: `RefreshDatabase` puts every migration in batch 1, so the test runs `migrate:rollback --path=<the change's files> --realpath` (not a plain rollback, which would empty the database), writes the old shape with `DB::table()`, runs the migrations forward, compares every amount to the grosz, and finally runs `down()` and checks the old shape again. It skips itself when the driver is not `pgsql`. `tests/Feature/ControlNumbersCommandTest.php` reuses the pattern to prove that `app:control-numbers` gives the same numbers on both shapes.

## Expand, then backfill, then drop

Add new tables and columns in one migration, fill them in a second, and drop the old columns in a later phase, only when no code reads them. Example: moving from `is_paced` / `month_item_kind` to rhythm (F-05 / S-24) adds the rhythm next to the flags, switches readers one area per phase with tests green, and drops the flags in the last phase. A calculator pair (PHP and TS) switches in one commit (`.ai/rules/month-calculators.md`).

## A rollback after deployment is named in the plan, and `down()` never pretends

`scripts/deploy.sh` makes a full backup before every `migrate --force` (ADR-0064). It covers a failure during or right after a deployment (README → „Wdrożenie i przywracanie”) but loses every operation added after it. A change with a data migration therefore names in its plan how to roll back after deployment without losing new operations (PRD v3: a reversible migration, or a restore of the `user:backup` snapshot made before the migration) and which control numbers it compares before and after (`php artisan app:control-numbers`, run by `scripts/deploy.sh` before and after `migrate`; a difference stops the deployment and leaves the application in maintenance mode). `down()` either really reverses the migration and has a test, or throws a `RuntimeException` that points to the backup and its instructions; it never pretends to reverse. „Merge” does not describe the Category list migration: ADR-0063 does not merge Budget Lines, and merging Categories is S-21.
