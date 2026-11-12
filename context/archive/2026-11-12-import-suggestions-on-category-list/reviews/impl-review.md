<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Podpowiedzi Importu na liście kategorii

- **Plan**: context/changes/import-suggestions-on-category-list/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-12
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Evidence for Success Criteria (2026-11-12, PostgreSQL in `sail-8.5/app`, database `testing_import_suggestions`):

- Change tests (`ImportCategorySuggesterTest`, `CsvImportTest`, `Nfr/NfrPerf03ImportPreviewTest`, `Unit/ImportCategorySuggestionModelTest`, `ImportSuggestionAccuracyCommandTest`): 135 passed, 1237 assertions.
- `vendor/bin/sloppy ci`: 100/100, 0 findings. `pint --test` on the changed PHP files: passed.
- `composer ci:check` (worktree variant from AGENTS.md, PostgreSQL): exit 0, 1468 passed, 2 skipped (11063 assertions).
- Phase 3 cleanup: no `suggestion_trial` database and no `suggestions-before` worktree; the measurement JSON files are ignored (`storage/app/private/.gitignore`) and none is tracked.
- Every planned change matches its Intent and Contract (plan drift check: all MATCH). The S-23 status in `roadmap.md:54` is `in-progress`, the usual workflow step, not scope creep.

## Findings

### F1 — Broken `--compare` file passes as "no drop"

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/CsvImport/ImportCategorySuggestionAccuracy.php:110
- **Detail**: `differences()` reads the summaries with `?? []` (`:110`) and `drops()` turns every missing `rows`/`suggested`/`correct` into 0 (`:139-144`). Both sides then compare as `0 < 0`, so there is never a drop. A "before" file without `periods` (or with renamed keys), but with the same `rows`, exits 0 with "No drop". `FORMAT` (`:17`) is written (`:74`) and never read back, so a file in another format version passes too. This command is the gate for the "nie gorzej" rule, and a silent pass goes against "Nigdy nie ukrywaj dowodów". The sibling `ControlNumbers::differences()` (`app/Support/ControlNumbers.php:92-104`) reports each missing path. The command test has no failure case for the compare file: no missing file, no invalid JSON and no missing keys. `ControlNumbersCommandTest.php:250` covers the first. Malformed rows (`:118`) fail loudly, with a TypeError or ErrorException, but not as a clean error. The phase 3 result still stands: both files came from the documented format.
- **Fix**: In `differences()`, return `error` when `format !== self::FORMAT` or when a `periods.{period}.{level}.{rows|suggested|correct}` key or a row's `model`/`suggestion` is missing. Add tests for a missing file and for a file with missing summary keys (both exit 1).
- **Decision**: FIXED

### F2 — The command writes its own user resolution instead of using `ResolvesConsoleUser`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Console/Commands/ImportSuggestionAccuracyCommand.php:130
- **Detail**: The private `resolveUser()` (`:130-135`) decides between id and email with `ctype_digit`, and its error is "User not found." without the identifier (`:24`). The project trait `App\Support\Console\ResolvesConsoleUser` decides by `@` and throws `ModelNotFoundException`. Seven commands use it (`ListAccountsCommand`, `CreateAccountCommand`, `BackupUserDataCommand`, `RestoreUserDataCommand`, `ResetUserDataCommand`, `SeedDemoHouseholdCommand`, `SeedPreviousYearCommand`), and they print `User not found: {id}`. The plan's "`{user}` to „ID or email address”, jak w `account:list`" points at exactly this pattern.
- **Fix**: `use ResolvesConsoleUser;` with `catch (ModelNotFoundException)` and the identifier in the message, as in `ListAccountsCommand`; drop the private method.
- **Decision**: FIXED

### F3 — `--output` equal to `--compare`, and a non-numeric `--year`, give misleading results

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Console/Commands/ImportSuggestionAccuracyCommand.php:29
- **Detail**: The output is written (`:48-53`) before the compare file is read (`:55-57`). When both options name the same file, the run compares with itself and always exits 0. `ControlNumbersCommand` has the same order. `--year=abc` becomes 0 through `(int)` (`:30`) and then fails with "no imported rows in 0".
- **Fix**: Reject equal resolved paths for `--output` and `--compare`, and validate `--year` with `ctype_digit`, with a clear error.
- **Decision**: FIXED

### F4 — `top3` is in the file but is not part of the drop check

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/CsvImport/ImportCategorySuggestionAccuracy.php:137
- **Detail**: `drops()` compares only "with a suggestion" and "correct among suggested". This matches the plan's rule 1, which does not name top 3. A top-3 drop exits 0 and shows up only in the list of differing rows, and the README and docblock do not say so.
- **Fix**: Add one sentence to the `differences()` docblock and to the README subsection: top 3 is informational and does not gate.
- **Decision**: FIXED

### F5 — `before.php` hides a failed write

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: context/changes/import-suggestions-on-category-list/measure/before.php:115
- **Detail**: `@mkdir` silences the error, and the return value of `file_put_contents` (`:116`) is not checked, so the script can print "Result written" after a failed write. This is a one-off script and has already run (the "before" file exists and `--compare` read it). It stays in the change folder as the record of the procedure.
- **Fix**: Remove the `@` and stop with an error message when `file_put_contents` returns `false`.
- **Decision**: FIXED

### F6 — File helpers copied from `ControlNumbersCommand`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Console/Commands/ImportSuggestionAccuracyCommand.php:137
- **Detail**: `resolvePath`, `writeJson` and `readJson` (`:137-169`) are identical to `ControlNumbersCommand.php:60-92`. Sloppy does not flag them (0 findings), and the plan named that command as the pattern. A third command with `--output`/`--compare` would copy them again.
- **Fix**: Move the three helpers into a trait in `app/Support/Console/`, next to `ResolvesConsoleUser`, and use it in both commands.
- **Decision**: FIXED

## Triage (2026-11-12)

The user asked for all findings to be fixed as recommended. The new tests went red first (6 failed, 3 of them exit 0 on a broken compare file) and are green after the fix.

| Finding | Decision | Change |
| --- | --- | --- |
| F1 | FIXED | `ImportCategorySuggestionAccuracy::differences()` returns `error` for another `format`, a missing `periods.*.*.{rows,suggested,correct}` count, missing `rows` or a row without `operation_id` or a flag (`fileError`, `summaryError`, `rowsError`); the `?? 0` defaults are gone. Tests: missing file, format 2, no `periods`, a row without `suggestion.correct`. |
| F2 | FIXED | The command uses `ResolvesConsoleUser` and prints `User not found: {id}`; the test checks the message. |
| F3 | FIXED | `optionError()` rejects a non-numeric `--year` and the same file in `--output` and `--compare` (equal path or equal `realpath`) before anything is measured or written. Tests for both. |
| F4 | FIXED | The `differences()` docblock and the README subsection say that top 3 is for reading and is not a drop. |
| F5 | FIXED | `measure/before.php` checks `mkdir` and `file_put_contents` and exits 1 with a message. |
| F6 | FIXED | New trait `App\Support\Console\ReadsAndWritesJsonFiles` (`resolvePath`, `writeJson`, `readJson`), used by `ImportSuggestionAccuracyCommand` and `ControlNumbersCommand`. |

Verification after the fixes: `ImportSuggestionAccuracyCommandTest` + `ControlNumbersCommandTest` on PostgreSQL 18 passed; Pint passed; PHPStan no errors; Sloppy 100/100, 0 findings; `composer ci:check` (PostgreSQL) exit 0, 1474 passed, 2 skipped (11080 assertions).
