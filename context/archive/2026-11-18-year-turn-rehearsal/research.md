---
date: 2026-11-18T11:08:15+01:00
researcher: Claude (claude-opus-5-5)
git_commit: 32d91e00e967ad9f0c8649bf4ae7b74e2389fb22
branch: development
repository: household-treasurer
topic: "year-turn-rehearsal: what the code does at the 2026→2027 turn on production data, and what a rehearsal needs"
tags: [research, codebase, budget-year, month-plan, collected, dashboard, reports, snapshot, clock, year-turn-rehearsal]
status: complete
last_updated: 2026-11-18
last_updated_by: Claude (claude-opus-5-5)
---

# Research: The 2026→2027 year turn and how to rehearse it

**Date**: 2026-11-18T11:08:15+01:00
**Researcher**: Claude (claude-opus-5-5)
**Git Commit**: 32d91e00e967ad9f0c8649bf4ae7b74e2389fb22 (only this change folder added)
**Branch**: development
**Repository**: household-treasurer

## Research Question

Row 1 of "Na już" in `context/foundation/development-directions.md:68`: rehearse the 2026→2027 turn on a copy of production before 31 December. The rehearsal must cover three things: moving the 2027 draft into execution, carrying the Collected into the new year, and getting the January plan from December. The research has to answer:

1. What does the code do at the year boundary? This covers Budget phases, the "current year", the Month Plan, the Collected, balances, the Dashboard and Reports.
2. Which steps and timings must the user get right on production, and what breaks if they don't?
3. What tooling exists for a rehearsal on a copy of production (snapshot, restore, clock control, control numbers), and what do the tests already prove?

Production state is taken from earlier research, not re-checked here: 2026 in execution, 2027 a draft, 39 and 38 Budget Lines (`context/archive/2026-11-12-legacy-budget-import/research.md:31`). Years 2019–2025 are sheet-loaded.

## Summary

- **Clock.** The server runs in UTC (`config/app.php:68`), so the year turns at 01:00 Warsaw time. The frontend uses the browser's local clock in about 15 places (e.g. `resources/js/components/BudgetYearSwitcher.vue:82`).
- **The "current year" depends on the page.** The Dashboard shows the Budget of the UTC calendar year (`app/Http/Controllers/DashboardController.php:35-37`). Budget, Month Plan, Reports and the shell use the session year first and fall back to the current year (`app/Support/SelectedBudgetYear.php:25-36`).
- **Three steps on production have a hard order or deadline:**
  1. **The December 2026 Month Plan must be approved before 2027-01-01 00:00 UTC.** After that, an unapproved December reads as `none`, because it is a past month (`app/Support/MonthPlanProposal.php:175-177`, `:206-209`). Approval then throws "A past month cannot be approved" (`app/Actions/ApproveMonthPlan.php:41-43`). January 2027 would then fall back to the annual share of the 2027 plan instead of copying December.
  2. **The 2027 Budget must be in execution before the first income or expense dated in 2027 is recorded or imported.** Both paths refuse a non-execution year: hand entry and import go through `OperationCategoryChoice` (`app/Support/CategoryList/OperationCategoryChoice.php:126-127`; `app/Support/CsvImport/FinancialOperationImportValidator.php:49-55`).
  3. **Starting execution has no date guard.** It only checks that the Budget is a draft (`app/Actions/StartBudgetExecution.php:14`). It freezes `planned` into `initial_plan` and sets `execution_minor_units` to 0 without recomputing from operations (`:21-25`).
- **These carry over by themselves, with nothing stored or reset on 1 January:**
  - The **Collected** chains across years from the rhythm entry's start (`app/Support/IrregularCollected.php:61-86`). The January 2027 rate is 0 while 2027 is a draft. The phase is read at read time, so starting execution later fills the rate in.
  - **Account balances, Spendable liquidity and Total funds** do not depend on the year (`SpendableLiquidity.php:13-16`, `TotalFunds.php:30-34`).
  - The only scheduled jobs are backups (`bootstrap/app.php:36-51`).
  - **Year Close and Reserve** are not in the code (grep scope in Detailed Findings §5).
- **What the user sees while 2027 is still a draft in early January:**
  - **Dashboard:** no categories, no To Spend, no pace and no Collected. No notice says that 2027 has not been started (`DashboardController.php:95`, `DashboardNotices.php:111-117`).
  - **Budget and Month Plan:** if the session still holds 2026, they keep showing 2026 with December read-only (`SelectedBudgetYear.php:31`, `BudgetViewedMonth.php:28-29`).
- **Rehearsal tooling exists but there is no clock override:**
  - `user:backup` and `user:restore` cover every table the turn touches (snapshot v13). `app:control-numbers` can prove stored history did not change.
  - No running-app "today" override exists. A rehearsal in the browser needs a new local-only clock hook, or libfaketime, plus the browser clock. Feature tests already cross the turn with `travelTo`.
- **Tests cover the pieces, not the whole path:**
  - **Covered:** the Collected across 31 December, "Zebrane startowe" at the turn, January copying December, year-over-year on 1 January.
  - **Not covered:** approving December after the turn, import into a draft year, January with [2026 execution, 2027 draft] and no session year, the Dashboard on 1–5 January with a draft, and the UTC/local mismatch.

## Detailed Findings

### 1. Clock and "current year"

- **Server timezone:** `config/app.php:68` sets `timezone` to UTC. `AppServiceProvider.php:43` only sets `Date::use(CarbonImmutable::class)`. Controllers call `CarbonImmutable::today('UTC')`, e.g. `SelectedBudgetYear.php:36`.
- **Dashboard:** reads the Budget of `today->year` and ignores the session (`DashboardController.php:35-37`; ADR-0031; tested `tests/Feature/DashboardTest.php:634`).
- **Budget, Month Plan, Reports and the shell:** use `SelectedBudgetYear::resolve` (`SelectedBudgetYear.php:12-50`). The order is:
  1. the session `budget_year`, if a Budget exists for it;
  2. otherwise the current UTC year;
  3. otherwise the newest year not after it;
  4. otherwise the oldest year (ADR-0075).
- **Writing the session year:** `BudgetController::selectYear` and `store` write `budget_year` (`BudgetController.php:121`, `:149-166`).
- **If the session holds 2026 on 2027-01-0x:**
  - Budget and Month Plan show 2026.
  - Month Plan opens on December (`app/Support/BudgetViewedMonth.php:28-29`). December is read-only because its timing is `past` (`MonthPlanController.php:168-172`, `:319-330`).
  - This was found by reading the code, not by running the app. No test covers [2026 execution, 2027 draft] in January; the nearest case is `tests/Feature/SelectedBudgetYearTest.php:43-68` ([2025, 2026] on 2027-01-05 → 2026).
- **Frontend clock:**
  - The menu marks the "current" year with the browser's local `new Date().getFullYear()` (`BudgetYearSwitcher.vue:82`).
  - The entry dialogs default the date with `toISOString()`, which is UTC (`ExpenseFormDialog.vue:439`, `IncomeFormDialog.vue:332`).
  - Between 00:00 and 01:00 Warsaw time on 1 January, the menu therefore flags 2027 while the server and the default dates still say 2026-12-31.
  - The UTC default-date issue was already known and called pre-existing in `context/archive/2026-11-05-operation-dialogs-atrament/reviews/impl-review.md:116`.

### 2. Budget phases at the turn

- **Creating the next year:** `CreateBudget.php:14-81` builds a draft from every active list Category.
  - Each line's plan is taken from the same Category's line in the newest earlier Budget (a draft included), or 0.
  - Rhythm is not copied (`:40`), and needs no copy (§3).
  - A Category created later joins every draft with plan 0 (`JoinDraftBudgets.php:17-41`). Once a Budget is in execution, a Category joins it at first use instead.
- **Starting execution** (`StartBudgetExecution.php:12-29`, `BudgetPolicy.php:30-32`):
  - Only the draft state is checked. 2027 can be started today, in November 2026.
  - It sets `initial_plan = planned` and `execution = 0` for every line, with no recompute.
  - A plan review already flagged that a year can be started early (`context/archive/2026-11-10-category-rhythm/reviews/plan-review.md:82`).
  - Two Budgets in execution at once are allowed: `StoreBudgetRequest` only checks a unique year between 2000 and 2100.
- **Operations in a draft year:**
  - The Budget Line is resolved by the year of `operation_date` (`OperationEntryValidation.php:43-48`, `ManageFinancialOperation.php:217-226`).
  - Income and expense are refused for a non-execution year with "…can only be recorded for a budget in execution. Create or start a budget for this year." (`OperationCategoryChoice.php:126-127`).
  - The CSV import validator calls the same check with the row's year (`FinancialOperationImportValidator.php:49-55`).
  - Tested for hand entry (`tests/Feature/ExpenseTest.php:217`, `IncomeTest.php:126`); not tested for import.
  - Consequence: if a bank statement spanning 28.12–03.01 is imported while 2027 is a draft, the January rows are refused.
  - **Not traced:** own transfers and capital movements in a draft year. No phase guard appeared in a grep of their actions, which is consistent with them not touching Budget Lines.
- **Can starting execution destroy execution amounts?** Only if some path wrote `execution_minor_units` on a draft line. `ManageFinancialOperation::lockLine` (`:236`) refuses a draft year only when the Budget Line is missing; the phase check sits in `OperationCategoryChoice`. No path that skips that check was found, but one was not ruled out either. This is worth one probe in the rehearsal: compare control numbers before and after starting.

### 3. The Month Plan across 31 December

- **The previous year:**
  - `MonthPlanProposal::forBudget` loads the previous Budget only when it is year − 1 and in execution (`MonthPlanProposal.php:61-66`). 2026 qualifies.
- **What a past month reads as** (the check is `[year, month] < [today.year, today.month]`, `:206-209`):
  - an approved plan reads as `approved`;
  - an unapproved one reads as `none` (`:175-177`).
- **Rules that follow for December 2026 after the turn:**
  - Approval is refused for `none` (`ApproveMonthPlan.php:41-43`).
  - An approved past month cannot be changed (`ChangeMonthPlan.php:38-39`).
  - The page turns off `can_approve` and `can_change` (`MonthPlanController.php:168-172`).
  - The month summary appears only for a past month with an approved plan (`MonthPlanController.php:209`).
  - No test approves December across the turn: `ApproveMonthPlanTest.php:144` uses a past month inside 2026.
- **The January 2027 Proposal** (`MonthPlanProposal.php:211-226`, `:244-273`):
  - **Source:** December of the 2026 Budget, matched by `category_id` (`:310-314`).
  - **If December is `none`:** January falls back to `AnnualPlanShare` of the 2027 plan (`:244-246`; `tests/Unit/MonthPlanProposalTest.php:240`).
  - **Fixed charges** read the actual December payment (`:266-273`; test `:340`).
  - **Categories without a matching line:**
    - with a 2027 line but no 2026 line: get the annual share;
    - with a 2026 line but no 2027 line: are not proposed and can only join as a join row (`MonthPlanJoinCandidates`, `ApproveMonthPlan.php:66`).
  - **Archived lines:** skipped from the month they were archived (`:183-186`).
  - **Set-aside:**
    - the User's default percent wins (`:188-196`);
    - otherwise January copies a positive December set-aside, but not a draw (`:203`; tests `:130`, `:145`).
- **Month guard:** `isBlockedByPreviousMonth` never blocks January (`:139-142`). So if 2027 is started in December, January 2027 can be approved before December 2026.
- **If execution starts after January has ended** (inferred from `:175-177` and `:213-216`, not tested): January reads `none`, and February falls back to the annual share.

### 4. The Collected, Even annual and balances

- **Collected arithmetic** (`CollectedCalculator.php:20-55`): end of month = `max(0, opening + rate − actual)`. The chain runs month by month with a floor at 0.
- **Rhythm storage:**
  - Rhythms are a history per list Category, not per year: `category_rhythms(category_id, start_year, start_month, rhythm)` (`database/migrations/2026_11_10_120000_create_category_rhythms_table.php:16-24`).
  - A month uses the entry with the latest start not after it (`CategoryRhythms.php:104-128`).
  - `opening_collected_minor_units` is the Collected at the start of that entry's start month. It is written only by `ChangeCategoryRhythm` (`:118`) and is not a per-year value.
- **The chain crosses the year:** `IrregularCollected::collectedAtStartOfMonth` chains through every later month and year (`IrregularCollected.php:61-86`; ADR-0066:28, ADR-0073:21). Tested at `tests/Feature/IrregularCollectedTest.php:122`.
- **The January 2027 rate** (`IrregularCollected.php:145-173`):
  - 0 while 2027 is a draft or the Category has no 2027 line;
  - after execution starts, the approved January item, or the annual share if January is not approved.
  - The draft case and its backfill are tested at `IrregularCollectedTest.php:136`.
  - An earlier plan listed this case only as a manual check: "budżet 2027 jeszcze szkicem 15 stycznia 2027 → stawka stycznia 0, zebrane z 31 grudnia stoi" (`context/archive/2026-11-09-rhythm-and-irregular-decisions/plan.md:264`).
- **"Zebrane startowe" on the Budget screen:**
  - It is computed, not stored (`BudgetCollectedAtYearStart.php:32-46`, ADR-0076).
  - It is marked "prognoza" until 2027-01-01 00:00 UTC (`tests/Feature/BudgetCollectedAtYearStartTest.php:146-181`).
  - The E2E spec `collected-year-start.spec.ts` covers it without depending on the date.
- **Even annual:** `AnnualPlanShare.php:13-22` gives `floor(plan / 12)` for January–November and the remainder in December. January 2027 uses the 2027 plan. "Zostało X na Y mies." gives `months_left` = 12 in January (`MonthPlanItemRow.php:113-123`).
- **Balances:** independent of the year.
  - Spendable liquidity is the sum of stored `current_balance_minor_units`, leaving out Sheet Accounts (`SpendableLiquidity.php:13-16`).
  - Total funds add the term deposits (`TotalFunds.php:30-34`).
  - "Uzgodnij saldo" reads opening balance plus flows up to the date (`AccountBalanceOnDate.php:17-29`).

### 5. Dashboard and Reports in early January; Year Close

- **Dashboard with 2027 a draft:**
  - The execution-only block is skipped (`DashboardController.php:95-124`; tested `DashboardTest.php:686`, `:1772`).
  - Month notices need a To Spend reading (`DashboardNotices.php:111-117`), so only account notices can appear.
  - Nothing prompts the user to start 2027.
- **Dashboard once 2027 is in execution:** the `month_plan_not_approved` notice appears from day 3 of the month (`DashboardNotices.php` around `:233`).
- **Division by zero is guarded** at the start of a year:
  - `MonthPaceCalculator.php:41-57`: plan ≤ 0 gives a null percent;
  - `TenthsPercent.php:17`;
  - `ReportYears.php:237`, `:310`.
- **Year-over-year (ADR-0074):** compares only against N − 1 in execution, cut at today minus one year (`ReportComparisonPeriod.php:31-55`). So 1–5 January 2027 is compared with 1–5 January 2026 (tested `ReportComparisonPeriodTest.php:59-75`).
- **Reports → "Lata":**
  - Only execution Budgets up to today's year are included. The current year and the same-day cut exist only when that year is in execution (`ReportYearsRange.php:34-50`; tested `ReportYearsRangeTest.php:60`).
  - Sheet-loaded operations are dated on the last day of their month (`app/Support/SheetLoad/SheetLoadPlanner.php:59`).
  - With the default same-day mode on 1–30 January, the January columns of 2019–2025 therefore read about 0. This was found by reading the code, not by running it.
  - The same effect already exists inside every month and is not specific to the turn; in January it covers the whole year-to-date.
- **Year Close and Reserve:** none found. The grep was `year.?close|year_close|reserve|rezerw|year.?result|wynik roku|close.?year|rollover|carry.?over` over `app routes database resources/js config bootstrap`. The only hits were unrelated: `preserveScroll`, `jobs.reserved_at` and sheet mappings in `database/sheet-load/mapping.php:57,161`. This matches `roadmap.md` § Parked.

### 6. Rehearsal tooling

- **User snapshot** (`app/Actions/BackupUserData.php:19`, version 13; JSON written to `storage/app/private/user-snapshots/<id>-<YmdHis>.json`, `:31-43`). It contains:
  - accounts (with `sheet_year`), balance reconciliations and term deposits;
  - budgets with `year` and `phase`;
  - category groups, and categories with `rhythms[]` including `opening_collected_minor_units`;
  - budget lines (initial, planned, execution);
  - approved month plans with their items;
  - operations, own transfers and capital movements (`BackupUserData.php:47-273`, `app/Support/UserSnapshot/LedgerRows.php:19-35`).

  Left out: the `users` row, credentials, and the session (and so the selected year). Proposals are computed, so only approved month plans are stored.
- **Restore** (`user:restore {user} {path} {--force}`, `RestoreUserDataCommand.php:13`):
  - It wipes the target user's domain data and inserts the snapshot with remapped ids, in one transaction (`RestoreUserData.php:60-71`; ADR-0008). It accepts versions 1–13.
  - A local target user is needed first (`user:create`).
  - For a separate database, set `DB_DATABASE` and run `config:clear`, then confirm with `db:show` (`README.md:275`). Use `php -S`, not `artisan serve`, which drops the override (user memory note).
- **Instance backup:** `backup:restore {zip}` runs `DROP SCHEMA public CASCADE` on the configured database (`RestoreApplicationBackupCommand.php:7`). The documented local trial is in `README.md:268-280`.
- **Getting the data from production:**
  - `deploy.sh` snapshots every user on each deploy (`scripts/lib/production-deploy.sh:74-79`).
  - Earlier transfers used `backup:run` on the VPS and `ssh … 'cat'` (`context/archive/2026-11-09-category-list-after-migration/migration-trial.md:9`).
  - No step is documented for copying a single `user:backup` JSON.
- **Clock control:**
  - No env var, config key, middleware or clock service overrides "today" in a running app.
  - About 31 files in `app/` call `now()`, `today()` or `CarbonImmutable::now()` directly. App SQL does not use `NOW()` or `CURRENT_DATE`.
  - `compose.yaml` uses the stock Sail 8.5 image, without libfaketime.
  - Options:
    - (a) a local-only provider gated on `app()->environment('local')` plus an env var, calling `Date::setTestNow()`;
    - (b) libfaketime in a preview container;
    - (c) in either case the browser clock (Playwright `page.clock`, or the OS clock).
  - **Unverified:** whether `Carbon::setTestNow` alone also moves `CarbonImmutable` in the installed Carbon version. `Date::setTestNow` is the facade-level call.
- **Control numbers** (`app:control-numbers --output/--compare`, `app/Support/ControlNumbers.php:49-78`):
  - It reads the whole database and is keyed by id: row counts, per-Budget and per-line plan, execution and operation sums, set-aside, transfers and balances.
  - It holds no Collected, To Spend or Dashboard readings, and does not compare the Budget phase.
  - New 2027 rows will show as differences.
  - `--allow-additions` is hardwired to the sheet-load rule (`ControlNumberAdditions.php:29-36`).
- **Seeders:**
  - `demo:seed-household` follows `now()->year` (`DemoHouseholdSeeder.php:30,77`).
  - `demo:seed-previous-year` builds N − 1 from the newest execution Budget (local and testing only).
- **Rehearsal template:** `context/archive/2026-11-10-category-rhythm/reviews/production-rehearsal.md` has a step table that can be reused. It is marked "DO UZUPEŁNIENIA".
- **E2E:**
  - 15 specs. `sidebar-year-refresh.spec.ts` creates, switches and deletes a year. None starts budget execution.
  - The webServer runs `scripts/e2e-server.sh` on database `household_treasurer_e2e`. Seeding is manual and one-time (`context/foundation/test-stack.md:14`), and there is no clock control.

## Code References

- `config/app.php:68` — application timezone UTC
- `app/Support/SelectedBudgetYear.php:12-50` — session year, then the current year
- `app/Http/Controllers/DashboardController.php:35-37`, `:95-124` — Dashboard year and execution-only block
- `app/Actions/StartBudgetExecution.php:14-25` — draft check only; resets execution to 0
- `app/Support/CategoryList/OperationCategoryChoice.php:86`, `:126-127` — refuses income and expense outside an execution year
- `app/Support/CsvImport/FinancialOperationImportValidator.php:49-55` — import goes through the same check
- `app/Support/MonthPlanProposal.php:61-66`, `:175-177`, `:206-226` — previous Budget, past month `none`, January source
- `app/Actions/ApproveMonthPlan.php:24-45` — execution-only; "A past month cannot be approved"
- `app/Support/IrregularCollected.php:61-86`, `:145-173` — Collected chain and January rate
- `app/Support/ReportYearsRange.php:34-50`, `app/Support/SheetLoad/SheetLoadPlanner.php:59` — "Lata" range; sheet dates on the last day of the month
- `app/Actions/BackupUserData.php:19`, `app/Console/Commands/RestoreUserDataCommand.php:13` — snapshot v13 and restore
- `app/Support/ControlNumbers.php:49-78` — control numbers
- `resources/js/components/BudgetYearSwitcher.vue:82` — browser-local current year

## Architecture Insights

- **No year-turn event exists.** There is no scheduled job or stored state change, and the code holds no Year Close. Everything at the turn is either a reading recomputed from the clock (Collected, Proposal, Dashboard, Reports), or a gate the user must open by hand (starting execution, approving the Month Plan). The risk therefore sits in the order of the user's actions and in the clock, not in a migration of data.
- **Two independent "current year" sources** sit on the server (Dashboard: calendar year; other pages: session first). A third sits in the browser (local time).
- **The phase is a read-time input.** Starting 2027 late fills in the Collected rate and Proposals after the fact. The exceptions are an unapproved December, which is lost for good after the turn, and income and expenses, which cannot be recorded in the meantime.

## Historical Context (from prior changes)

- `context/archive/2026-11-09-rhythm-and-irregular-decisions/plan.md:61` — "31 December needs no separate step": the chain runs on and the January rate comes from the new plan. **Supported by the code** (§4).
- `context/archive/2026-11-09-rhythm-and-irregular-decisions/plan.md:264` — 2027 still a draft on 15 January gives a January rate of 0, and the Collected from 31 December stands. **Supported** (`IrregularCollected.php:145-173`; test `IrregularCollectedTest.php:136`).
- `context/archive/2026-11-10-category-rhythm/reviews/plan-review.md:82` — a year can start execution early. **Still true** (`StartBudgetExecution.php:14`).
- `context/archive/2026-11-11-year-over-year-report/plan.md:238` — planned behaviour for January 2027 in the year-over-year report. **Matches** `ReportComparisonPeriod.php:31-55`.
- `context/archive/2026-11-14-report-redesign-decisions/plan.md:41` — the year range ends at the current UTC year, and drafts are skipped. **Matches** `ReportYearsRange.php:34-50`.
- `context/archive/2026-11-05-operation-dialogs-atrament/reviews/impl-review.md:116` — UTC default date in the entry dialogs, called pre-existing. **Still present** (`ExpenseFormDialog.vue:439`).
- `context/archive/2026-11-10-category-rhythm/reviews/production-rehearsal.md` — a rehearsal template, unfinished.

## Related Research

- `context/archive/2026-11-12-legacy-budget-import/research.md` — production Budget state (2026 in execution, 2027 draft).
- `context/archive/2026-11-17-testing-import-integrity/research.md` — Import validation paths used in §2.

## Open Questions

1. **Rehearsal method (plan decision):**
   - (a) A restored production snapshot plus a local-only clock hook, walked in a browser.
   - (b) The same scenarios as Feature tests with `travelTo` on factory data, without a browser.
   - (c) Both: run (a) once on real data, then keep (b) as regression tests.

   The hard rules call for failing tests from any defect found, which points to (c).
2. **Data source:** an instance backup zip (the tried route, but `DROP SCHEMA`), or a single `user:backup` JSON restored onto a local user (narrower, no documented copy step). For the user to choose; production access is theirs.
3. **Product gaps the rehearsal may confirm.** Whether to fix any of these is the user's decision, not a research one:
   - no Dashboard notice that the current-year Budget is still a draft;
   - an unapproved December becomes unapprovable at 01:00 Warsaw time with no warning beforehand;
   - the session keeps 2026 selected in January;
   - the UTC vs local mismatch around midnight;
   - the "Lata" same-day mode reading about 0 for sheet years in January.
4. **Timezone:** should the app turn the year at Warsaw midnight, i.e. `Europe/Warsaw` instead of UTC? This touches every date reading and needs its own decision. It is out of scope unless the rehearsal shows harm.
5. **Unverified paths:**
   - own transfers and capital movements dated in a draft year;
   - any path writing `execution_minor_units` on a draft line;
   - whether `Carbon::setTestNow` moves `CarbonImmutable`.
6. **Order on production (to confirm in the plan):**
   1. approve December 2026 in December;
   2. start 2027 execution after December is approved and before the first January operation;
   3. approve January 2027 from 1 January.

   Should 2027 start before 31 December? Early start is allowed and makes January approvable before December, so the order then rests on the user, not on the code.
