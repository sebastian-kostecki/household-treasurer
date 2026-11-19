---
date: 2026-11-19T06:22:18+01:00
researcher: Claude (claude-opus-5-5)
git_commit: 2141188bf96468a8edf2b32201b70a822739eb40
branch: worktree-test-plan-refresh
repository: household-treasurer
topic: "test-plan-refresh: ground the refresh of context/foundation/test-plan.md (roadmap F-09, MS-06)"
tags: [research, test-plan, import, year-turn, app-day, e2e, playwright, ci, snapshot, reports, calculators, test-plan-refresh]
status: complete
last_updated: 2026-11-19
last_updated_by: Claude (claude-opus-5-5)
---

# Research: grounding the test-plan refresh (F-09)

**Date**: 2026-11-19T06:22:18+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: 2141188bf96468a8edf2b32201b70a822739eb40 (worktree branch; code identical to local `development` at e50f8f6 plus this change folder)
**Branch**: worktree-test-plan-refresh
**Repository**: household-treasurer

## Research Question

Ground `refresh-brief.md` (output of `/10x-test-plan --refresh`, 2026-11-19) before `/10x-plan` rewrites `context/foundation/test-plan.md` §1–§8 (§7 unchanged, P5):

1. Risk #1 extended ("a saved operation is a real statement transaction"): what `ImportIntegrityTest` proves today, how #271 and #272 show in data, the cheapest layer.
2. New year-turn risk: the "today" sources, how PHP and Vitest freeze time, the F-08 decision, the `_known_gap` tests.
3. Risk #7 changed (E2E only local): which of the 15 specs can gate CI (S-44), what CI lacks.
4. Risks #2, #3, #5, #6, #8: their evidence after the abandoned M-2, and the stale §3/§4/§5/§8 facts.

Sources outside this worktree: the M-5 roadmap is uncommitted in the main checkout (`/home/sebastian/my-projects/household-treasurer/context/foundation/roadmap.md`, read-only; the worktree copy is still M-4). The F-08 decision lives on the unmerged local branch `worktree-app-day-decision` (read via `git show`).

## Summary

- **Stale facts confirmed, two brief numbers corrected.** Phase 1 is archived (`context/archive/2026-11-17-testing-import-integrity/change.md:4`, `status: archived`). Its Progress has **28 of 28** rows `[x]`; the brief's "29/29" counted the convention line `plan.md:588`. Larastan is **v3.15.0** in `composer.lock`; "3.9" is the `composer.json:24` constraint `^3.9`. The other counts hold: 175 `*Test.php` (132 under `tests/Feature`, 110 of them at top level, 43 under `tests/Unit`), 63 `*.test.ts`, 15 `*.spec.ts`, 44 PHP test files with `travelTo(`, `SNAPSHOT_VERSION = 13`.
- **#1 extended. Half of the intended proof contradicts the PRD as written.** Persisted (date, signed amount, account) equal to hand-copied file values is cheap and not asserted today: the hand tuples are checked only on the preview session (`ImportIntegrityTest.php:497-502`). But two parts need a PRD change before a test can assert them:
  - "an unreadable row is visible in the preview" contradicts FR-IMP-08 (`prd-mvp.md:255`: such rows "są pomijane i nie pojawiają się w podglądzie jako błędy");
  - "currency equals the file" contradicts FR-IMP-10 (`prd-mvp.md:257`: "Osobna kolumna waluty nie wchodzi do mapowania"). Operations also have no currency column at all.
  - #271 and #272 are therefore spec gaps, not only code bugs. Both are OPEN and unpinned by any test.
- **Year turn. F-08 is decided, but not merged.** ADR-0103 ("One App Day in Europe/Warsaw") exists only on branch `worktree-app-day-decision` (4 commits, `5ff2ff1`…`38f09df`). It makes the server source `AppDay::today()` and gives the browser `shell.today`, with "no exception" to `new Date()`. After S-39 the year-turn test is therefore server-side `travelTo` at the Polish-midnight boundaries plus `assertInertia` on `shell.today`, and a guard against `new Date()` in domain JS. It is no longer "freeze both clocks".
  - Before S-39 three clocks exist (verified anchors below). No PHP test covers 22:59:59/23:00 UTC; all boundary tests sit at UTC midnight. No Vitest test fakes time (0 files), and TZ is not pinned.
- **#7 changed. No spec commits an Import, and CI retries would mask flakiness.**
  - Nothing in `tests/e2e/` reaches the Import commit step (grep for `Zatwierdź|Importuj|commit` finds no file). The gate flow "upload → preview → commit" needs a new spec.
  - `playwright.config.ts:19` sets `retries: process.env.CI ? 2 : 0`, the brief's named anti-pattern for a gate.
  - CI cannot start the E2E server as is: `scripts/e2e-server.sh:17,26` requires the Sail Docker network.
  - Gate candidates: `seed.spec.ts`, `import-other-format-without-header.spec.ts`, `month-plan-groups.spec.ts`, `irregular-collected.spec.ts`, plus four light specs. They are listed below with the reason per spec.
- **#2, #3, #5, #6, #8 have current evidence from M-3/M-4, without M-2.**
  - #2 and #5 keep their strength: 5 PHP/TS calculator pairs are kept in sync by convention only; 8 snapshot bumps v6→v13 happened with no completeness guard.
  - #3 is weakened: report = Execution / Month Result tests exist, but they compare reads with reads.
  - #6 is weakened: frozen v5/v6/v8/v9 fixtures and per-version restore tests exist.
  - #8 is mostly covered per route, but there is no sweep and no `arch()`.

## Detailed Findings

### 1. Import fidelity (risk #1 extended, #271, #272)

**What `tests/Feature/ImportIntegrityTest.php` proves today**
- Hand tuples `[operation_date, amount_minor_units]` are compared with `session(...rows)` by the helper `importIntegrityPreviewTuples()` (`:497-502`). This happens only before commit (uses at `:579`, `:597`, `:760`). The constants are `IMPORT_INTEGRITY_MBANK_TUPLES` (`:547-555`) and `IMPORT_INTEGRITY_BNP_TUPLES` (`:559-567`).
- After commit the tests assert:
  - row counts;
  - stored account and term-deposit balances, and that the stored balance equals `AccountBalanceOnDate`;
  - line execution;
  - digest presence and equality (`:186-191`, `:218`, `:350-355`).
- No test asserts the date, amount or account **of each persisted operation**.
- The end-to-end cases `:628` (mBank reordered) and `:665` (BNP basic) commit the same files whose tuples are hand-copied. Asserting persisted tuples there is one extra `expect` per case.
- Persisted fields:
  - `ImportFinancialOperations.php:249-253` stores `abs` for income and expense and `-abs` for refund in `amount_minor_units`;
  - `:263-269` stores the **signed file amount** in `import_identity_amount_minor_units`;
  - `account_id` is the session account (`:261`).
  - So the persisted oracle is `[operation_date, import_identity_amount_minor_units, account_id]`, or `[operation_date, type, amount_minor_units, account_id]`.
- The private fresh-export cases (`:744`, `:794`) skip when `storage/app/private/import-fixtures/*` is missing (`:706-715`), so they do not run in CI.

**No currency on operations**
- `app/Models/FinancialOperation.php:13-47` has no currency attribute. A grep of `currency` over `database/migrations` and `app/Models` returns nothing.
- Currency exists only as the parse-time flag `amount_has_foreign_currency`:
  - set from the amount cell text (`CsvImportParser.php:227-238`, `BnpParibasXlsxParser.php:249-260`);
  - turned into invalid reason `foreign_currency` (`CsvImportPreviewValidator.php:39-40`).
- This matches FR-IMP-10 (`prd-mvp.md:257`).

**#271 (row lost after a cell format change)**
- State: OPEN, `needs-triage`, no test.
- Mechanism: the parser returns a null date or amount, and the controller drops the row before indexing (`CsvImportController.php:82-85`, filter `isOperationShapedRow`). The preview shows no count of skipped rows.
- Triggers, quoted from the archived research `context/archive/2026-11-17-testing-import-integrity/research.md:108-150` (worker probe, not re-run here):
  - the amount regex (`CsvImportParser.php:251`, `BnpParibasXlsxParser.php:269`) rejects `+12,34`, `-1.234,56` and U+202F;
  - date formats are limited;
  - the file is split on `\R` before `str_getcsv`, which breaks quoted multi-line fields.
- This is the behaviour FR-IMP-08 prescribes (`prd-mvp.md:255`).

**#272 (non-PLN amount saved as PLN)**
- State: OPEN, `needs-triage`, no test.
- Mechanism: a numeric BNP `Kwota` cell yields `amount_has_foreign_currency => false`, and the `Waluta` column is never mapped (`BnpParibasXlsxParser.php:74-120`). An EUR amount becomes PLN minor units: a wrong value, not a lost row.
- Current test coverage: the only currency test (`CsvImportTest.php:348-379`) covers `10.00 EUR` inside the amount cell, preview only. The BNP test sheets carry `Waluta = 'PLN'` (`CsvImportTest.php:4306-4327`).

**Atomicity**
- Atomicity holds: `DB::transaction` at `ImportFinancialOperations.php:78`.
- The draft-year `_known_gap` (`CsvImportTest.php:685`) shows a different gap:
  - the preview marks the 2027-01-02 row `valid` (`:693-700`);
  - the commit refuses the whole set (`:702-712`).
- The preview → commit contract is broken, not atomicity. Roadmap S-42 (`roadmap.md:160-168`, main checkout) owns it, and its Risk notes that a partial save would weaken the phase 1 "no half sets" rule.

**Risk #4**
- `ImportSuggestionAccuracyBenchmarkTest.php` is present.
- It has no group; `phpunit.xml` excludes only `nfr`, so it runs inside `composer ci:check` (`composer.json:67-83`).
- It refuses a baseline write under `CI` (`:126-129`).
- That CI keeps `pgsql` over the `phpunit.xml` SQLite env is inferred from `tests.yml:37`, not proven by a run.

**Cheapest layer**
- Persisted fidelity: Feature on PostgreSQL, inside the existing `:628`/`:665` cases, reusing the hand tuples. This needs no product decision.
- #272 pin: Feature preview through a new `importIntegrityBnpVariant` arm with `Waluta = EUR` (helper `:508-528`).
- #271 pin: Feature preview through `importIntegrityPreview` with an inline CSV, asserting the hand row count.
- Both pins assert behaviour that FR-IMP-08 and FR-IMP-10 currently forbid or permit, so their expected values need the PRD and ADR change first (AGENTS.md hard rule on glossary and ADR).

### 2. Year turn (new risk)

**Rehearsal gaps** (`context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md:52-60`) **and their owners** (from the main-checkout roadmap `:30-34`, `:120-182`)

| Gap | Symptom | Pinned by | Owner |
|---|---|---|---|
| 1 | A draft current year gets no "Wymaga uwagi" notice and no Import hint (the badge exists) | — | S-41 |
| 2 | December cannot be approved after UTC midnight | `YearTurnTest.php:126`, `:138` (asserts the current UTC rule, not `known_gap`) | S-41 (+ S-39 moves the boundary) |
| 3 | The session year stays 2026 in January | `SelectedBudgetYearTest.php:82` `…_known_gap` | S-40 (`roadmap.md:134`) |
| 4 | Year menu, server and dialogs disagree 00:00–01:00 PL | — | S-39 |
| 5 | "Lata" with the same-day cut compares one day on 1 January | — | S-43 |
| (phase 1 finding) | Import with a draft new year refuses the whole statement | `CsvImportTest.php:685` `…_known_gap` | S-42 (`roadmap.md:160`) |

**Three "today" sources (verified lines)**
- Server: `config/app.php:68` `'timezone' => 'UTC'`, and `SelectedBudgetYear.php:36` `CarbonImmutable::today('UTC')`.
  - The full inventory has about 30 call sites: controllers, readers, actions and 15 `before_or_equal:today` rules.
  - It matches "Spis miejsc w kodzie" in `git show worktree-app-day-decision:context/changes/app-day-decision/plan.md` (lines 29-50).
- Year menu (browser local clock): `BudgetYearSwitcher.vue:82` `props.currentYear ?? new Date().getFullYear()`. Also `composables/useCommandPalette.ts:132` and `CreateBudgetDialog.vue:55`.
- Dialogs (browser UTC date via `toISOString().slice(0, 10)`): **seven**, not five. `IncomeFormDialog.vue:332`, `ExpenseFormDialog.vue:439`, `OwnTransferFormDialog.vue:182`, `CapitalMovementFormDialog.vue:222`, `TermDepositFormDialog.vue:118`, `AccountFormDialog.vue:105`, `BalanceReconciliationDialog.vue:48`.
- The Dashboard date comes from the server (`Dashboard.vue:77` ← `year_elapsed_as_of`).

**F-08 decision (ADR-0103, branch `worktree-app-day-decision` only; `git branch -a --contains` lists no other branch)**
- The App Day is the Europe/Warsaw date. The server reads it from `App\Support\AppDay::today()`; `config/app.timezone` stays UTC.
- The browser reads `shell.today` ('YYYY-MM-DD'). "The rule has no exception" for `new Date()` in dialogs, the year menu, the palette, presets, day labels and guest pages.
- It supersedes in part ADR-0074, 0075, 0076, 0077, 0081, 0097 and 0098. The roadmap Baseline (`roadmap.md:82`) lists only five of these.
- Named examples: 2026-12-31 23:30 UTC → App Day 2027-01-01; 22:59:59 UTC → 2026-12-31; summer 30 June 22:30 UTC → 1 July. DST days have 23 or 25 hours.
- "The tests pinned to UTC midnight change together with the code (S-39)". This is a requirement change, so editing `YearTurnTest.php:126/:138` and `BudgetCollectedAtYearStartTest.php:172,176` in S-39 is legitimate under "fix the cause, not the test".
- The rehearsal clock (`app:rehearsal-clock`, UTC input) stays, and the browser follows it through `shell.today` without `page.clock`.

**How tests freeze time today**
- PHP: `travelTo(` in 44 files (`grep -rlE "travelTo\(" tests | wc -l`). `setTestNow(` appears in 1 file, only as a reset (`RehearsalClockTest.php:28-29`).
- No test sets Europe/Warsaw, and no test uses 22:59:59/23:00 UTC. The boundary tests use UTC midnight: `YearTurnTest.php:95,130,145`, `BudgetCollectedAtYearStartTest.php:172,176`.
- `phpunit.xml` sets no timezone. CI (`tests.yml`, ubuntu-24.04) sets no `TZ`.
- Vitest: 0 of 63 `*.test.ts` use `vi.useFakeTimers` or `vi.setSystemTime`. The `vite.config.ts:44-46` test block has only `include`, and there are no setup files.
  - The existing pattern passes "today" as an argument: `budgetYearMenu(entries, selected, 2026)` in `budget-year-menu.test.ts:15`, `formatDayLabel(date, today)` in `format.test.ts:149-156`, `authYearProgress(today)`.
  - A test that keeps `new Date()` depends on the host TZ.
- Playwright: no spec uses `page.clock` or `timezoneId`. Four specs read the real year (`categories.spec.ts:16`, `month-plan-groups.spec.ts:59`, `irregular-collected.spec.ts:58`, `import-other-format-without-header.spec.ts:16`), and `reports.spec.ts:524-548` computes a 12-month window from `new Date()`.
- `RehearsalClock::apply()` (`AppServiceProvider.php:45`) calls `Date::setTestNow` only when `isLocal()` and the `rehearsal-clock` file exists (`RehearsalClock.php:24-35`).
  - A Feature test can reuse it (`RehearsalClockTest.php:35-45`), but `travelTo` is simpler.
  - E2E reuse would share the clock file with the dev server of the same checkout.

**Years report near 1 January**
- ADR-0097 sets the cut day to "today in UTC". The cut tests use 2026-11-14, 2027-01-10 (draft 2027, no cut) and 2027-02-01 (`ReportYearsRangeTest.php:18,35,48,60`, `ReportYearsTest.php:112,478,624`).
- No test has today = 1 or 2 January with the current year in execution. Gap 5 is untested.

**Implication for the risk response**
- The brief's anti-pattern "freezing time only in PHP while the bug lives in the browser clock" holds until S-39.
- After S-39 (ADR-0103) the browser has no own clock in domain code. The cheapest proof is then:
  - (a) PHP Feature with `travelTo` at 22:59:59 / 23:00:00 UTC (winter) and 21:59:59 / 22:00:00 UTC (summer), asserting the props (`shell.today`, Dashboard year, `budget_year`) with `assertInertia`;
  - (b) Vitest feeding the same 'YYYY-MM-DD' to the functions that take today;
  - (c) a static guard against `new Date()` / `getFullYear()` / `toISOString().slice(0,10)` in domain JS. Precedents: `tests/Unit/MonthPlanHardcodedUiTest.php`, `npm run check:ui`.
- E2E adds nothing that (a)–(c) miss, except the rehearsal's visual check.

### 3. E2E in CI (risk #7 changed)

**Files**
- `tests/e2e/` holds 15 specs, `auth.setup.ts` and `support/{budget-year,categories}.ts`.
- `playwright.config.ts` settings:
  - `retries: CI ? 2 : 0` (`:19`);
  - `workers: 1` (`:25`), because one storageState shares one DB session, the session driver has no locking, and specs delete accounts others pick (comment `:20-24`);
  - `trace: 'on-first-retry'`, `webServer: bash scripts/e2e-server.sh`, with `reuseExistingServer: !CI`.
- Credentials: `E2E_USERNAME`/`E2E_PASSWORD`, read in `auth.setup.ts:6-7` and empty in `.env.example:80-81`.

**What CI lacks** (`.github/workflows/tests.yml`: one job `ci`, `postgres:18-alpine`, DB `household_treasurer`, steps up to `composer ci:check`, no Playwright)
- A server start without Docker: `e2e-server.sh:17,26` requires the network `household-treasurer_sail` and runs `sail-8.5/app` (`:39`, `:49`).
- An E2E database plus seed. `test-stack.md` describes a manual `user:create` and `demo:seed-household`. The reports specs also need `demo:seed-previous-year` (`reports-category-panel.spec.ts:7-8`), which `test-stack.md` does not mention.
- Credentials in the job, and `npx playwright install chromium`.
- `APP_ENV=local` for the `/dev/ui/...` kitchen-sink spec. `.env.example:2` already provides it.

**The seed depends on the day it runs**
- `DemoHouseholdSeeder.php:30` sets `$year = now()->year`.
- `pastDateInYear` (`:327-332`) falls back to `YYYY-01-15` when the computed date falls in the previous year. A database seeded between 1 and 14 January therefore contains operations dated after "today" (inference from code, not run).
- Locally the E2E database is seeded once by hand. After a year turn, specs that pick `new Date().getFullYear()` look for a budget the database may not have.
- A CI job that seeds per run avoids the drift, but not the 1–14 January window.

**Coverage of the three critical flows**
- Import: only `import-other-format-without-header.spec.ts` (upload → preview, no writes). **No commit spec.**
- Operation dialog: `seed.spec.ts` (add → list → reload → delete, `pageerror` check `:12`, `:53`).
- Month plan: `month-plan-groups.spec.ts` (with `test.setTimeout(120_000)` `:88`) and `irregular-collected.spec.ts`. Both self-clean and use the current year.

**Gate candidates** (evidence-based judgement, nothing was run)

| Spec | Gate? | Reason |
|---|---|---|
| `seed.spec.ts` | yes | self-cleaning, asserts effect and JS errors, depends on the category "Żywność" only |
| `import-other-format-without-header.spec.ts` | yes | no writes; picks the first account not named "E2E …" |
| `month-plan-groups.spec.ts`, `irregular-collected.spec.ts` | yes, month-plan flow | self-cleaning, current year; long multi-step specs |
| `account-edit-keeps-bank.spec.ts`, `shell-mobile-menu.spec.ts`, `sidebar-current-section.spec.ts` | optional | no data or own unique data |
| `reports-view.spec.ts` | optional | fixed fixtures (`resources/js/lib/reports-fixtures.ts`); needs `APP_ENV=local` |
| `categories.spec.ts` | local | drag-and-drop geometry already failed once (`51790d9`, v1-4-3-fixes); `waitForTimeout(300)` |
| `reports.spec.ts`, `reports-category-panel.spec.ts` | local | previous-year seed, a clock-derived window, `test.skip` when data is missing (skips hide gaps), `waitForTimeout(500)` |
| `collected-year-start.spec.ts`, `sidebar-year-refresh.spec.ts` | local | create the next Budget year, a global state other specs read; order-dependent under `workers: 1` |
| `command-palette.spec.ts`, `global-shortcuts.spec.ts` | local / later | hydration race handled by `toPass` retries |

**History**
- 57 commits touch `tests/e2e` or `playwright.config.ts`.
- Spec fixes came from shared state, selector moves after UI changes, viewport geometry and the hydration race: `4df4eb1` (one worker), `4055727` (v1-4-0-fixes, unique names and moved row actions), `51790d9` (drag below the viewport), `cdf98b8` (year menu moved).
- `gh issue list --search e2e|playwright` returns only #223 (closed, a performance issue). No issue reports a flaky spec.

### 4. Risks #2, #3, #5, #6, #8 without M-2

- **#2 To Spend PHP↔TS. Current evidence, strength unchanged.**
  - Five pairs (`.ai/rules/month-calculators.md:28-34`): MonthRemaining, ToSpend, MonthItemDeviation, MonthPace, Collected.
  - "Change both copies and both tests together" (`:26`) is a convention; no shared fixture or cross-language test exists.
  - The TS Month Remaining copy is the only one that runs in production (`:38`).
  - New source: M-3 S-26 changed both copies (`context/archive/2026-11-11-irregular-expense-collected/plan.md:71,265-269,478`; commit `8f154b4`). Irregular-rule tests: `tests/Unit/ToSpendCalculatorTest.php:133-148`, `resources/js/lib/to-spend-calculator.test.ts:222-252`.
  - Glossary: `glossary.md:89,113,129,133`. Replace the S-14 citation with S-26 and the rule file.
- **#3 Report sums. Current evidence, partly weakened.**
  - Report = Month Result / Execution tests exist: `PeriodReportTest.php:92,116,152,377,424,570,636`, `ReportTrendTest.php:159-163,249`, `ReportYearsTest.php:147,447`, `ReportCategoryPanelTest.php:95`, `ReportToOperationsTest.php:128,139`.
  - They compare one read with another (the plan's anti-pattern) rather than a hand oracle. Capital movements appear in the Trend and Years tests, not in `PeriodReportTest.php`.
  - New sources: S-28…S-32 (M-3), S-34/S-35 (M-4), S-43 (the cut in January). Replace S-11/S-12.
- **#5 Backup completeness. Unchanged and open.**
  - No completeness guard: grep for `getTables|getTableListing|information_schema|getColumnListing` over `tests/` returns nothing.
  - `BackupUserDataCommand.php:20-31` returns failure only for a missing user (`UserSnapshotCommandTest.php:462`).
  - New source: eight version bumps v6→v13 in M-3 and later (`.ai/rules/snapshot.md:15`). Replace S-14/S-16.
- **#6 Restore of older versions. Weakened.**
  - `SNAPSHOT_VERSION = 13` (`BackupUserData.php:19`); restore accepts 1–13 (`RestoreUserData.php:131`).
  - Frozen fixtures `tests/Fixtures/snapshots/v{5,6,8,9}-category-list.json` with restore tests (`UserSnapshotCommandTest.php:907,953,1213,1240,1258,1486`).
  - v7 and v10–v13 are built in-test (`:1127,1400,1632,1720,1832`). The rule "a restore test for every older version" is in `.ai/rules/snapshot.md:11-13`.
  - The §2 text "wersje 1–5 i v6" is stale.
- **#8 Another user's data. Mostly covered, likelihood Low unchanged.**
  - Per-route tests exist, e.g. `CategoryMergeHttpTest.php:47-59`, `OperationsFilterTest.php:510,536,749,1048`, `ReportsPageTest.php:173-198,750-756`, `ReportCategoryPanelTest.php:439`.
  - There is no route sweep, no `arch(` (grep), and PHPStan does not analyse `tests/` (`phpstan.neon` paths).
  - Not verified beyond name grep: an other-user case for S-31 report→operations and for `category-groups.*`.
  - New sources: S-21, S-22, S-31. Replace S-11/S-13.

### 5. Stale stack and gate facts (§4, §5, §8)

- **Versions** (`composer.lock`, `package-lock.json`): Pest v5.3.5, PHPUnit 13.4.5, Larastan v3.15.0, Laravel v13.42.0, vite-plus 1.6.0 (`package.json:43`), Vitest 5.1.0, @playwright/test 1.65.0.
- **§4 e2e row:** Playwright 1.65, local only (`context/foundation/test-stack.md`), not "none yet". The "kandydaci: Pest Browser plugin…" note is obsolete.
- **§5 e2e gate:** "required after Phase 5 (planned in Phase 4)" → after the S-44 gate phase. `retries` policy to be decided.
- **`composer ci:check`** (`composer.json:67-74`): `vp check`, `check:ui`, `types:check`, `vp test`, Pint test, PHPStan, `php artisan test`.
- **§1 hot-spot line and §8 dates:** new figures come from the brief (373 commits from 2026-10-20 to 2026-11-19). They were not recomputed here.

## Code References

- `tests/Feature/ImportIntegrityTest.php:497-502` – preview-only tuple helper; `:628`, `:665` – end-to-end commits, where the persisted assertion fits
- `app/Actions/ImportFinancialOperations.php:78,249-269` – transaction, signed vs absolute amount, identity
- `app/Http/Controllers/CsvImportController.php:82-85` – silent drop of non-operation-shaped rows (FR-IMP-08)
- `app/Support/CsvImport/BnpParibasXlsxParser.php:74-120` – `Waluta` not mapped (#272)
- `context/foundation/prd-mvp.md:255,257` – FR-IMP-08, FR-IMP-10
- `tests/Feature/CsvImportTest.php:685`, `tests/Feature/SelectedBudgetYearTest.php:82` – the two `_known_gap` tests
- `tests/Feature/YearTurnTest.php:126,138` – December approval pinned at UTC midnight
- `config/app.php:68`, `app/Support/SelectedBudgetYear.php:36`, `resources/js/components/BudgetYearSwitcher.vue:82`, `resources/js/components/IncomeFormDialog.vue:332` (+6 dialogs) – the three clocks
- `app/Support/RehearsalClock.php:24-35,61-67` – the rehearsal clock
- `playwright.config.ts:19,25` – retries in CI, one worker
- `scripts/e2e-server.sh:17,26,39,49` – Sail network dependency
- `database/seeders/DemoHouseholdSeeder.php:30,327-332` – seed depends on its run date
- `.ai/rules/month-calculators.md:26-38`, `.ai/rules/snapshot.md:11-15`
- `app/Actions/BackupUserData.php:19`, `app/Actions/RestoreUserData.php:131`

## Architecture Insights

- Time is passed as an argument in both stacks: `$today` into PHP calculations and readers, and today/year into TS lib functions. Clocks are read at the edges (controllers, components). ADR-0103 keeps this shape and swaps the edge source. A year-turn test therefore needs to freeze only the edge: `travelTo` on the server and `shell.today` in props. It does not need fake timers.
- Static "hard-coded value" guards already exist as a pattern (`MonthPlanHardcodedUiTest`, `ReportsHardcodedUiTest`, `check:ui`). A "no clock in domain JS" guard fits the same pattern.
- Import keeps parsed rows in the session and takes values from the session, not the request (`CsvImportController.php:236-247`). The preview tuples and the persisted values therefore come from one source, and asserting both catches only mapping errors between them (sign, type, account). The file-level oracle stays the hand count and the hand tuples.

## Historical Context (from prior changes)

- `context/archive/2026-11-17-testing-import-integrity/` – phase 1. Gap A was fixed (`distinct`); gaps B and C were filed as #271/#272. The research there (`research.md:108-150`) is the source of the #271 variant list.
- `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md` – gaps 1–5 and the T1–T5 observations.
- `worktree-app-day-decision` branch (`context/changes/app-day-decision/plan.md`, ADR-0103) – F-08 decided, not merged.
- `context/archive/2026-11-11-irregular-expense-collected/plan.md` – S-26 changed both calculator copies.
- `context/archive/2026-11-13-v1-4-0-fixes/`, `…v1-4-3-fixes/` – E2E selector and geometry fixes.

## Related Research

- `context/archive/2026-11-17-testing-import-integrity/research.md`

## Open Questions (for `/10x-plan` and the user)

1. **#1 fidelity scope.** The research recommends rewording the intent to what holds under the current PRD: persisted (date, signed amount, account) equal the hand tuples. #271/#272 stay issues that need a PRD/ADR decision on FR-IMP-08/FR-IMP-10 (owner: user). Open: a separate §3 phase, or a sub-step of another phase?
2. **Year turn: a §3 phase or only a §6 pattern plus a §2 risk?** The tests ride with S-39…S-43, and ADR-0103 already names the S-39 test moments. A §3 phase opened through `/10x-new` would duplicate those slices. Recommendation: §2 risk, a §6 pattern, and a §3 row that points to S-39…S-43 instead of opening its own change. The plan or user decides.
3. **ADR-0103 is unmerged.** The §6 year-turn pattern should cite the App Day rule only once F-08 lands on `development`, or say "per F-08 (pending merge)".
4. **E2E gate set and policy for S-44:**
   - confirm the gate list above;
   - add an Import commit spec, in S-44 or in a test-plan phase;
   - `retries` 0 for gates, or retries with flaky runs reported;
   - CI seeding and credentials, which the roadmap S-44 Unknown already owns.
   - The 1–14 January seed window matters for "don't block year-turn fixes in December/January".
5. **Likelihood re-scoring.** #3 and #6 are weakened by existing tests (Medium?); #2 and #5 are unchanged. This is for the plan to set with the user.
