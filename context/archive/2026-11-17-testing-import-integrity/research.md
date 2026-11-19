---
date: 2026-11-17T13:01:18+01:00
researcher: Claude (claude-opus-5-5)
git_commit: 2d6fe1093b33dbc4bcde1439389646f865c07301
branch: worktree-testing-import-integrity
repository: household-treasurer
topic: "testing-import-integrity: ground test-plan Phase 1 (risks #1 Import integrity, #4 suggestion accuracy)"
tags: [research, codebase, import, csv-import, duplicates, atomicity, suggestions, accuracy, test-plan, testing-import-integrity]
status: complete
last_updated: 2026-11-17
last_updated_by: Claude (claude-opus-5-5)
---

# Research: Import integrity and suggestion accuracy (test-plan Phase 1)

**Date**: 2026-11-17T13:01:18+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: 2d6fe1093b33dbc4bcde1439389646f865c07301 (`origin/development` 42ef64e plus this change folder only)
**Branch**: worktree-testing-import-integrity
**Repository**: household-treasurer

## Research Question

Ground rollout Phase 1 of `context/foundation/test-plan.md` ("Integralność Importu") before planning:

- **Risk #1**: approving an Import silently duplicates or loses operations (overlapping statement, changed format/preamble/column layout), and a partial failure does not roll back the whole set. Intended response: a PostgreSQL Feature test with fixtures from anonymised real statements, asserting DB state (operation count, account balances before/after), not only the HTTP response.
- **Risk #4**: the accuracy of Import category suggestions drops and nobody measures it. Intended response: a fixed reference set (statement description → expected category) whose accuracy is ≥ a recorded baseline, failing a gate on a drop. The user's correction to verify: M-2/S-16 was abandoned (PRD v3), suggestions run on a single category list, so the baseline is today's measured accuracy rather than "before S-16".

For each risk: the real failure path in code, whether the response guidance would catch it, existing tests, the cheapest useful layer, and what the §2 backport must say.

## Summary

- **Atomicity holds for every failure raised inside the commit action.** `ImportFinancialOperations::handle` writes every selected row inside one `DB::transaction` (`app/Actions/ImportFinancialOperations.php:78`). On the inspected path, row failures are `throw ValidationException`, and no `catch` sits between them and the transaction. The only try/catch in the controller wraps parsing in `preview` (`app/Http/Controllers/CsvImportController.php:62-79`). The existing rollback tests assert counts only, never balances, Execution or a rolled-back pair conversion. So "partial failure does not roll back" is protected in code, but its observable effect on money is not.
- **Duplicate protection is a warning, not a guard.** The identity is a SHA-256 of user, account, file date, signed amount and raw statement text (`app/Support/CsvImport/ImportIdentityDigest.php:239-245`). It sits behind plain, non-unique indexes on all three tables. The commit-time duplicate check compares the selected rows only with already committed records, runs before the transaction (`CsvImportController.php:321-338`), and one global `duplicate_warning_acknowledged` boolean lets every flagged row through. Three concrete silent-duplicate paths exist:
  1. A request repeating the same `rows.*.index` (no `distinct` rule, `app/Http/Requests/StoreCsvImportRequest.php:23`). The real UI cannot send this.
  2. Concurrent double submit. Inferred from code, not reproducible in a Feature test.
  3. The same statement re-imported with a different mapping or format, which gives a different digest (by design of ADR-0007's exact match).
- **Losing rows is mostly by design.** FR-IMP-08 (`context/foundation/prd-mvp.md:255`) requires rows whose mapped date or amount does not parse to be skipped, not shown as errors. `isOperationShapedRow` drops them before the preview (`CsvImportController.php:82-84,472-479`), and no count of dropped rows exists anywhere. So:
  - **Header drift fails loudly.** A renamed required column, a BNP title row or a first cover sheet raises a `file` error.
  - **Cell-format drift loses rows with no trace.** This covers a date like `09-01-2025`, an amount with `+`, a dot thousands separator or U+202F, a quoted description containing a newline, and an Other-format split column holding `0,00`.
  - A test cannot assert "no row lost" from the app's output today. It needs a hand-counted oracle per fixture.
- **A realistic row failure passes preview and fails the whole commit.** A row dated in a year without a Budget in execution, or in a sheet-loaded year, is not checked at preview (`app/Support/CsvImport/CsvImportPreviewValidator.php:18-50`). It is rejected at commit by `OperationCategoryChoice::check` (`app/Support/CategoryList/OperationCategoryChoice.php:42-46,72-80`). This is the most natural atomicity scenario: a statement spanning New Year.
- **Risk #4 reframing: partially confirmed.**
  - S-16 was never built: M-2 was abandoned 2026-11-08 (`context/foundation/roadmap.md:192`).
  - But the change the risk feared did ship under other slices: the single category list (ADR-0063) and category merge (S-21).
  - S-23 already measured a same-data before/after on the 2026-09-11 production copy. Result: 1009 rows, 0 differing rows, 76.9% suggested / 93.6% correct at suggestion level for the whole year (`context/archive/2026-11-12-import-suggestions-on-category-list/measurements.md:9-33`).
  - The gap is not "no measurement". It is "no repeatable gate". S-23 deliberately left a CI threshold out because bank data must not enter the repo (`…/plan.md:42`).
  - So "the baseline is today's accuracy" is right only if "today" means a fixed reference set committed to the repo. Live production readings change rows and labels, and `--compare` refuses different row sets (`app/Support/CsvImport/ImportCategorySuggestionAccuracy.php:110-118`).
- **The PostgreSQL gate is automatic.** CI sets `DB_CONNECTION=pgsql` at job level (`.github/workflows/tests.yml:37`). The `<env>` entries in `phpunit.xml:32-33` carry no `force`, so `composer ci:check` (`composer.json:67-83`) runs every Feature test on PostgreSQL 18. A new test in `tests/Feature` joins the required gate with no extra wiring.

## Detailed Findings

### 1. Preview → commit boundary (risk #1)

- **Routes**: `GET operations/import`, `POST operations/import/preview`, `GET operations/import/preview`, `POST operations/import` (`routes/web.php:105-108`).
- **State between preview and commit** is the server session, not the DB: `session(['csv_import' => {account_id, rows, user_id}])` (`app/Support/CsvImport/CsvImportSession.php:17-24`), with a user check in `get` (`:37`). The session driver is `database` (`.env.example:30`, `config/session.php:19`).
- **The file is parsed once**, in `preview` (`CsvImportController.php:62-79`). Rows are annotated with duplicate flags (`:107`), pair candidates (`:108`) and category suggestions (`:109`), then stored (`:111-114`).
- **`store` takes the data from the session** (date, description, counterparty, raw text, amount: `:236-247`, `:273-284`, `:309-318`). It takes only the classification from the client (`StoreCsvImportRequest.php:21-33`).
- **The client cannot add rows**: an unknown index or a row with `valid !== true` raises a `rows` error (`CsvImportController.php:171-177`).
- **The client can repeat an index.** `'rows.*.index' => ['required', 'integer', 'min:0']` has no `distinct` (`StoreCsvImportRequest.php:23`), and the loop at `CsvImportController.php:166-319` does not track used indexes, so two selections of index 0 give two records. The UI keys inputs by row index (`resources/js/components/ImportPreviewRow.vue:376`) and disables submit while processing (`resources/js/components/ImportPreviewSubmitBar.vue:21`). A crafted or replayed request is the only way to hit this.
- **The session is cleared only after a successful commit** (`CsvImportController.php:346`). A `ValidationException` leaves it, so the user can fix and retry. A second sequential POST after success finds no session and redirects to `create` (`:156-160`). No test covers this replay.

### 2. Row identity and duplicate detection (risk #1)

- **Digest**: `sha256(userId|accountId|fileDate|signedFileAmount|rawStatementDescription)` (`ImportIdentityDigest.php:239-245`). Columns:
  - `financial_operations.import_identity_*` (`database/migrations/2026_09_07_180003_create_financial_operations_table.php:26-29`)
  - `own_transfers.source_/destination_import_identity_*` (`2026_09_07_163856_create_own_transfers_table.php:24-31`)
  - `capital_movements.import_identity_*` (`2026_09_09_143326_create_capital_movements_table.php:24-27`)
- **No database-level guard.** The digest indexes are plain `->index([...])` (`…financial_operations_table.php:36`, `…own_transfers_table.php:35-36`, `…capital_movements_table.php:31`). Uniqueness is deliberately absent:
  - ADR-0007's set match lets an acknowledged re-import create a second record.
  - A Capital Movement and its interest Income share one digest (`ImportFinancialOperations.php:194-227`).
- **The commit-time check** (`CsvImportController.php:321-338` → `ImportDuplicateDetector::selectedImportDuplicates`, `app/Support/CsvImport/ImportDuplicateDetector.php:157-210`) has three limits:
  - It compares only with committed records, not the selected rows with each other.
  - It holds no lock.
  - It runs before `DB::transaction`.
- **One acknowledgement covers all flagged rows** (`duplicate_warning_acknowledged`, a single boolean, `:334`). Many existing tests send `true` by default (e.g. `tests/Feature/CsvImportTest.php:582`, `:651`), which keeps them from exercising the gate.
- **Two identical real rows in one file** (same date, amount, text) share a digest. Neither is flagged on the first import, both commit (intended), and both are flagged on re-import (`CsvImportTest.php:2857`, Inertia-only). No test asserts that the first import creates two records.
- **Formatting drift on an overlapping statement.** In the Other format the raw text is trimmed only at the ends (`app/Support/CsvImport/ImportRowFields.php:74-93`). With a counterparty column mapped it is `description + "\n" + counterparty`. The following change the digest:
  - mapping or not mapping the counterparty column
  - choosing another date column
  - importing the same bank's file as Other instead of mBank/BNP

  In each case the re-import is not flagged and creates a second record. This is the accepted exact-match limit of ADR-0007, not a defect to fix in a testing change.
- **Concurrency** (inferred, not reproduced): no session blocking (`->block()`) on the route was found. Two simultaneous `store` requests both load the session, both pass the pre-transaction check and both commit. A `RefreshDatabase` Feature test cannot reproduce this. Closing it needs a design decision (lock, in-transaction re-check or idempotency token), which is out of a test-only scope.

### 3. Transaction and balances (risk #1)

- **One outer transaction**: `DB::transaction` in `ImportFinancialOperations::handle` (`ImportFinancialOperations.php:78`). Row failures throw `ValidationException::withMessages(['rows' => …])` at `:150-154` (own transfer), `:171-175` (interest), `:209-213` (capital movement), `:236-240` (interest income) and `:274-278` (financial operation).
- **Inner writes are nested transactions (savepoints) inside the outer one**:
  - `ManageFinancialOperation::create` locks the Account and Budget Line (`lockForUpdate`) and may join the Budget at first use, writing a `budget_lines` row. It increments `current_balance_minor_units` and `execution_minor_units` (`app/Actions/ManageFinancialOperation.php:137-166`, `:222-253`, `:276-289`).
  - `ManageOwnTransfer::create` writes both balances without a lock (`app/Actions/ManageOwnTransfer.php:31-41`, `:89-101`).
  - `ConvertImportPairFinancialOperation` deletes the first-leg Financial Operation (reverting balance and Execution) and creates an Own Transfer (`app/Actions/ConvertImportPairFinancialOperation.php:43-72`).
- **No events, jobs, `afterCommit` hooks or reconcile calls** were found on this path.
- **Balances are stored columns**: `accounts.current_balance_minor_units`, `budget_lines.execution_minor_units`, `term_deposits.current_balance_minor_units`. A derived reading exists in `App\Support\AccountBalanceOnDate::for` (`app/Support/AccountBalanceOnDate.php:17-29`). It gives an independent oracle: after any commit or rollback the stored balance equals the derived balance for today.
- **Failures a test can force inside the action, after an earlier row was written.** Controller-level rejections at `CsvImportController.php:171-307` happen before any write and do not test atomicity.
  - A row in a year without a Budget in execution, or in a sheet-loaded year: `OperationCategoryChoice.php:42-46,72-80`. The preview validator does not check this (`CsvImportPreviewValidator.php:18-50`).
  - A category of the wrong type, another user's category, or one archived between preview and commit (`app/Support/CsvImport/FinancialOperationImportValidator.php:49-59,102-108`). The existing test at `CsvImportTest.php:632` uses this.
  - An own transfer dated before the other Account's opening date (existing test `:1041`).
  - A capital movement to a Term Deposit opened after the row date (`:1304`).
  - Invalid interest (`:1637`).
  - A stale pair candidate (`ConvertImportPairFinancialOperation.php:51-72`).
- **The highest-value rollback case.** Row 1 converts a first-leg Financial Operation, deleting it, reverting Execution and moving two Accounts. Then row 2 fails. The rollback must restore the operation, its Execution and both balances. No test covers it.
- **SQLite vs PostgreSQL on this path**:
  - There is no raw SQL. Date handling is in PHP.
  - SQLite does not exercise `lockForUpdate` or PostgreSQL's aborted-transaction semantics after an SQL error. The forced failures above are PHP exceptions, not SQL errors.
  - The PostgreSQL run still matters per `AGENTS.md`, and CI provides it.

### 4. Formats, preamble, column layout (risk #1)

- **Formats** (`app/Enums/ImportFormat.php`): `mbank` CSV, `bnp_paribas` XLSX, `other` CSV with manual mapping. The file type is checked per format (`app/Http/Requests/PreviewCsvImportRequest.php:38-42,75-89`).
- **mBank** (`app/Support/CsvImport/MbankCsvMappingResolver.php`):
  - It scans lines for the headers `data operacji`, `opis operacji`, `kwota` (`:9-13`, `:45-56`), cuts everything before the header line (`:59`) and maps columns by name (`:91-96`).
  - Otherwise it throws "The mBank export header was not found in the file." (`:70`), which becomes a `file` error (`PreviewCsvImportRequest.php:103-104`).
- **BNP** (`app/Support/CsvImport/BnpParibasXlsxParser.php`):
  - It reads `getSheet(0)` (`:38`) with the header fixed at row 1 (`:78`) and maps columns by name (`:79-93`). It requires `data transakcji`, `kwota`, `opis` and the counterparty column(s), or throws (`:95-96`, `:119`).
  - It never reads `Waluta`, so a numeric amount with `Waluta = EUR` imports as PLN without warning (an FR-IMP-10 gap, a wrong value rather than a lost row).
- **Other**: `has_header` skips only line 0 (`app/Support/CsvImport/CsvImportParser.php:51`). Preamble lines become junk rows and are dropped.
- **Parsing limits**:
  - The file is split on newlines before `str_getcsv` (`CsvImportParser.php:95-100`, `:60`), so a quoted multi-line field breaks.
  - Only the UTF-8 BOM is stripped. There is no `iconv`/`mb_convert_encoding` in `app/`, so CP1250 is not converted.
  - Amounts must match `^-?\d+(\.\d{0,2})?$` after removing spaces/NBSP, a `PLN`/`zł` suffix and converting `,` → `.` (`CsvImportParser.php:240-251`).
  - Dates accept `Y-m-d`, `d.m.Y`, `d/m/Y` (`:124-141`), plus Excel serials in BNP (`BnpParibasXlsxParser.php:176-179`).
  - More than 5000 rows fails loudly (`CsvImportParser.php:82-86`).
- **Dropped vs shown**:
  - **Dropped without a count**: rows with a null date, or a null amount that is not foreign currency (`CsvImportController.php:472-479`), as FR-IMP-08 requires.
  - **Shown as invalid with a reason** (`CsvImportPreviewValidator.php:18-50`): future date, before account opening, missing description, foreign currency, zero amount.
  - **Unreachable through the controller**: `ImportRowInvalidReason::MissingDate` and `MissingAmount`. Only `tests/Unit/CsvImportPreviewValidatorTest.php:33,38` exercises them.
  - **Total loss is visible**: an empty preview shows "Brak wierszy do podglądu…" (`resources/js/pages/OperationsImportPreview.vue:212-215`).
- **Variant probe** (worker run of the real parser classes in memory, host PHP 8.4, no repo writes; not re-run by the parent):

  | Variant | Result |
  | --- | --- |
  | mBank renamed `#Kwota`; UTF-16 file | loud (`file` error) |
  | mBank reordered or extra columns, no `#`, no preamble, other preamble length | all rows kept |
  | mBank description in CP1250 | row kept, description `null` → fallback; `json_encode` of the row fails (end-to-end effect not run) |
  | quoted description with a newline | row lost, no trace |
  | amount `+12,34`, `-1.234,56`, `-12,345`, U+202F thousands | row lost, no trace |
  | date `09-01-2025`, `09.01.25`, BNP string `2025-01-09 10:15:00` | row lost, no trace |
  | trailing `#Saldo końcowe`, BNP sum row, blank row | dropped (correct, also silent) |
  | Other split columns with `0,00` in the empty one | row lost (read from code `CsvImportParser.php:178-183`, not run) |
  | BNP title row, cover sheet first, renamed required header, header with trailing NBSP | loud |
  | BNP extra sheet after data, columns reordered/added | all rows kept |

- **Existing fixtures** (the only real-layout evidence in the repo):
  - `tests/Fixtures/mbank-basic.csv`, added in 7eb929f on 2026-09-08. UTF-8 with BOM, a 25-line preamble, header on line 26, 7 data rows, no trailing summary, hand-anonymised.
  - `tests/Fixtures/bnp-paribas-basic.xlsx`, added in 23002e7 on 2026-09-08. One sheet, 13 columns, 7 data rows, Excel serial dates, file properties showing a real export edited in LibreOffice.
  - They are used at `CsvImportTest.php:1885`, `:2192` and `:1959`.
  - The other inline heredoc CSVs in `CsvImportTest.php` (114 counted by the worker) are synthetic, mostly `other` format.
- **Real statements**: no bank export was found in the main checkout outside `vendor`, `node_modules`, `.git` and worktrees. `storage/app/private/` holds budget workbooks and user snapshots, not bank exports. New anonymised fixtures must come from the user's own downloads.

### 5. Suggestions and accuracy (risk #4)

- **How suggestions work now** (`app/Support/CsvImport/ImportCategorySuggester.php`, `ImportCategorySuggestionModel.php`, `ImportCategorySuggestionTargets.php`):
  - **History**: only the user's Import-born operations (`whereNotNull raw_statement_description`, description ≠ fallback, `ImportCategorySuggester.php:79-105`). Text is `description + ' ' + counterparty` (`:61-64`).
  - **Tokens**: upper-cased, Polish letters folded, `[A-Z]{3,}` (`ImportCategorySuggestionModel.php:99-116`).
  - **Weights and votes**: a token weighs 1/k² over k (type, category id) pairs (`:77-80`), with a weight floor of 0.05 (`:174-182`). Similarity is weighted Jaccard; 5 neighbours vote with `MIN_SIMILARITY` 0.25 (`:14`, `:126`, `:242-260`), and a vote tie gives null (`:156-158`).
  - **Keying and targets**: votes are keyed by `type."\0".category_id` (`:61`). The winner is returned only if `CategoryChoiceOptions` offers it for the row's year (`ImportCategorySuggestionTargets.php:22-45`). An archived winner gives null with no fall-through (`tests/Feature/ImportCategorySuggesterTest.php:89`).
  - **Determinism**: no randomness or `now()` in suggester, model or accuracy class. Neighbours are ordered by similarity (rounded to 1e-9), then date, then id (`ImportCategorySuggestionModel.php:231-236`).
  - **No DB-specific SQL** in this path (no ILIKE, unaccent, pg_trgm, JSON or regex queries). `operation_date` is normalised with `substr(…,0,10)` (`ImportCategorySuggester.php:102`).
- **The "join between years" mechanism no longer exists as name joining.** Years link through the shared list Category id (`.ai/rules/categories.md`; ADR-0034 superseded in part by ADR-0063).
- **Live data actions that still move accuracy without any code change**:
  - merge (re-points history: `ImportCategorySuggesterTest.php:102`)
  - archive (`app/Support/CategoryList/CategoryChoiceOptions.php:56,120`)
  - a draft or missing Budget in the row's year (`ImportCategorySuggesterTest.php:204`)
  - new imports

  Rename, groups and rhythm do not affect suggestions. The roadmap has no open milestone after M-4 closed on 2026-11-15 (`context/foundation/roadmap.md:194`).
- **Existing measurement** (`app/Support/CsvImport/ImportCategorySuggestionAccuracy.php`, `app/Console/Commands/ImportSuggestionAccuracyCommand.php`):
  - It is a chronological rolling-origin replay over the user's own import history: each row is scored by a model built from earlier rows only (`ImportCategorySuggestionAccuracy.php:62-86`).
  - It reports at two levels (model, suggestion) for two periods (`year`, `aug_nov`, `:20-23`).
  - Targets and labels come from today's state (`:67`). `--compare` refuses files with different operation-id sets (`:110-118`) and flags any exact-fraction drop (`:210-229`).
  - Output JSON holds ids and flags only, no descriptions.
- **Tests**:
  - `tests/Feature/ImportSuggestionAccuracyCommandTest.php` has 14 tests on factory toy data, covering mechanics and `--compare` exit codes.
  - `tests/Feature/ImportCategorySuggesterTest.php` has 14 tests and `tests/Unit/ImportCategorySuggestionModelTest.php` has 13.
  - None asserts an accuracy threshold on a realistic set.
- **Fixed-set sources in the repo**: none realistic.
  - `DemoHouseholdSeeder` writes no raw statement text.
  - `FinancialOperationFactory` uses `fake()->sentence()` (`database/factories/FinancialOperationFactory.php:31`).
  - The two parser fixtures have 7 rows each.
  - The set must be authored. One route the worker proposed, inferred from `ImportCategorySuggestionModel.php:99-116` and not tested: the model sees only normalised token identity. So an injective renaming of normalised tokens to other `[A-Z]{3,}` tokens, with synthetic category labels and preserved dates and order, should reproduce real-data accuracy without bank text.

## Code References

- `app/Actions/ImportFinancialOperations.php:78` - the single transaction around the whole commit
- `app/Http/Controllers/CsvImportController.php:62-114` - parse once, annotate, store in session
- `app/Http/Controllers/CsvImportController.php:156-177` - session read, unknown/invalid index rejection
- `app/Http/Controllers/CsvImportController.php:321-346` - pre-transaction duplicate check, single acknowledgement, session forget
- `app/Http/Controllers/CsvImportController.php:472-479` - `isOperationShapedRow`, the silent drop (FR-IMP-08)
- `app/Http/Requests/StoreCsvImportRequest.php:23` - `rows.*.index` without `distinct`
- `app/Support/CsvImport/ImportIdentityDigest.php:239-245` - identity digest fields
- `app/Support/CsvImport/ImportDuplicateDetector.php:157-210` - committed-digest lookup
- `app/Support/CategoryList/OperationCategoryChoice.php:42-46,72-80` - sheet-year and Budget-in-execution checks at commit
- `app/Support/CsvImport/CsvImportPreviewValidator.php:18-50` - what preview marks invalid
- `app/Support/CsvImport/CsvImportParser.php:95-100,124-141,240-251` - line split, dates, amounts
- `app/Support/CsvImport/MbankCsvMappingResolver.php:45-70` - mBank header search
- `app/Support/CsvImport/BnpParibasXlsxParser.php:38,78-96` - BNP sheet and header
- `app/Support/AccountBalanceOnDate.php:17-29` - derived balance oracle
- `app/Support/CsvImport/ImportCategorySuggestionAccuracy.php:62-86,110-118` - accuracy replay and same-data compare
- `app/Support/CsvImport/ImportCategorySuggestionModel.php:61,99-116,126,231-236` - keying, tokens, threshold, ordering
- `.github/workflows/tests.yml:22,37` and `phpunit.xml:32-33` - CI runs the suite on PostgreSQL 18
- `tests/Feature/CsvImportTest.php:554,632,735,992,1041,2500,2857,3109` - existing atomicity, duplicate and pair tests (counts or Inertia only for the rollback/duplicate ones)

## Architecture Insights

- **Data and classification travel separately.** Preview is the single parse, and the session is the single source of row data. The client contributes only classification per index. This keeps the client from injecting rows, but makes index uniqueness the controller's job, and that is unenforced.
- **Exact-match identity, warning not constraint** (ADR-0007). Duplicate safety rests on the user seeing the flag and not acknowledging it. Tests must assert both halves:
  - without acknowledgement, nothing is written;
  - acknowledgement is the only path to a second record.
- **"Skip junk silently" is a product rule** (FR-IMP-08, commit e8fd800). It is what turns cell-format drift into silent loss. A test-only change can pin the current per-fixture row counts by hand oracle. Making the loss visible (a "skipped N rows" count) changes FR-IMP-08 and starts with the PRD/ADR, per `AGENTS.md`.
- **Stored balances with a derived reading available.** "Stored == derived" is a cheap, independent oracle that avoids the "assert against the same code path" anti-pattern.

## Historical Context (from prior changes)

- `context/archive/2026-11-04-import-category-suggestions/change.md:21,24` - origin baseline (77.1% suggested / 93.6% correct / 95.2% top-3 for the year; 79.3/96.5/99.3 Aug–Nov). Top-3 is defined differently from S-23, so it is not comparable to S-23's 73.5%. Supported for its own definition.
- `context/archive/2026-11-12-import-suggestions-on-category-list/measurements.md:9-33` - S-23 same-data before/after: identical, `--compare` exit 0, 0 differing rows. Supported.
- `context/archive/2026-11-12-import-suggestions-on-category-list/measurements.md:37-54` - production reading on 2026-11-12 (1268 rows): 77.7% / 92.9% for the year, 80.1% / 92.9% Aug–Nov, explicitly "informacyjnie, bez progu". No later re-reading after the "Jedzenie poza domem" → "Restauracje" merge was found.
- `context/archive/2026-11-12-import-suggestions-on-category-list/plan.md:42` (and 2026-11-04 `plan.md:43`) - "Asercji progu trafności w CI. Dane bankowe nie trafiają do repo…" listed as out of scope. This is a prior decision that Phase 1's #4 gate must reconcile.
- `context/foundation/prd-v3.md:209,238,297` - FR-020 "trafność nie gorsza niż przed zmianą". "Nie gorzej" is settled by the 2026-09-11 copy measured with old and new code; post-merge readings carry no threshold.
- `context/foundation/roadmap.md:192` - M-2 (S-11…S-17) abandoned 2026-11-08, replaced by PRD v3.
- `context/foundation/adr/0002-named-import-formats.md:3`, `context/foundation/prd-mvp.md:249-257,354,531` - promised formats. `prd-mvp.md:531` names the format-drift risk and relies on Other plus the preview to limit it.

### Backport to test-plan.md (verdict on the reframing)

The user's reframing is **partially confirmed**:

- **Confirmed**: S-16 is gone, and suggestions vote by list Category id. A "before S-16" baseline cannot be taken.
- **Corrected**: the cross-year change happened (ADR-0063, S-18, S-21) and was measured (S-23: no drop on the same data). What is missing is a repeatable gate. "Today's accuracy" must be the result on a fixed reference set committed to the repo, not a live production reading.

Suggested §2/§3 text for `/10x-test-plan` to carry over:

- **§2 risk #4 row** (`test-plan.md:50`):
  - New wording: "Podpowiedzi kategorii w Imporcie trafiają gorzej po zmianie kodu podpowiadacza albo listy kategorii (scalenie, archiwum), a spadku nie łapie żadna bramka".
  - Sources: `prd-v3.md` FR-020 and the Guardrails line; S-23 `measurements.md`.
  - Drop: `prd-v2.md` FR-013 and "roadmap S-16 Risk".
- **§2 Risk Response Guidance #4** (`test-plan.md:63`):
  - "Context to ground" becomes: vote by list Category id plus the `CategoryChoiceOptions` offer; a reference set without bank text (synthetic or token-pseudonymised); the baseline recorded in the repo with the set.
  - Anti-pattern becomes: "próg obniżony do bieżącego wyniku po spadku albo poziom bazowy spoza repo".
- **§3 Phase 1 goal** (`test-plan.md:77`): "…i zapisać poziom bazowy trafności podpowiedzi na stałym zestawie referencyjnym".
- **Ordering note** (`test-plan.md:83`): drop "poziom „przed” dla #4 musi powstać przed S-16".
- **Risk #1 Response Guidance** (`test-plan.md:60`): add "wiersz pomijany po cichu (FR-IMP-08) przy zmianie formatu komórki" to "Must challenge". Also add a hand-counted row count per fixture as the oracle for "komplet wierszy".
- **Outside this phase** (noted, not researched): risks #5/#6 and Phase 3 still cite S-14/S-16 and "snapshot v6", but snapshot fixtures already reach v9 (`tests/Fixtures/snapshots/v9-category-list.json`).

## Related Research

- `context/archive/2026-11-12-import-suggestions-on-category-list/measurements.md` (measurement record, no research.md in that folder)
- `context/archive/2026-11-04-import-category-suggestions/` (origin of the suggester and the accuracy command)

## Open Questions

Decisions for `/10x-plan`. Each changes scope:

1. **Fixtures from real statements.** The two existing fixtures are hand-anonymised real exports, 7 rows each. New ones need the user's own current mBank and BNP downloads, at least one full month each, anonymised by hand. The real mBank encoding (UTF-8 BOM vs CP1250) is unknown, and the current fixture may have been re-saved. Which statements does the user provide, and is a hand-counted row total per file acceptable as the oracle?
2. **Red tests for confirmed defects.** Several gaps are confirmed in code:
   - repeated `index`
   - silent loss on `+` amounts, dot thousands, quoted newlines, date drift
   - BNP `Waluta` ignored

   Should Phase 1 add them as failing tests that a fix follows (a production change in this testing change), as `todo`/skipped tests, or record them as findings for separate fix changes? A visible "skipped rows" count would change FR-IMP-08 and needs a PRD/ADR first.
3. **Accuracy reference set.** S-23 ruled out bank text in the repo and a CI threshold. Options:
   - (a) a token-pseudonymised export of the user's real history, generated once by a script from the user snapshot, with no bank text committed;
   - (b) a hand-authored synthetic set;
   - (c) keep the measurement out of CI and only record a procedure.

   Option (a) gives a realistic baseline, but the pseudonymisation claim is untested. Its first proof would be pseudonymised accuracy equal to the real accuracy on the same data.
4. **Threshold semantics.** S-23's rule is "no drop on any period/level, row diff listed" (`--compare`). A fixed set could reuse it exactly (zero tolerance on the same data) or use a margin. Which does the user want?
5. **Concurrency of double submit.** It is not testable at the Feature layer, and closing it is a design change. Is it in scope, or is it recorded as accepted risk in §7?
