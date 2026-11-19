# Year Turn Notices Implementation Plan

## Overview

Roadmap slice S-41 (MS-03). Before the year turn the user should see what is coming. The month-plan notice „Plan niezatwierdzony” names its deadline (the last day of the month, 23:59 Polish time). A new `warning` notice says that the Budget of a year that Import must accept is a draft or does not exist. Once next year's Budget is in execution, the year menu and the Dashboard say that it waits for 1 January.

## Current State Analysis

- **The audit finding MPL-1 is already closed.** Commit `1659a87` (`fix(december-approval-signal)`, no change folder) shows `errors.items` in every Month Plan state (`resources/js/pages/MonthPlan.vue:669`). It also reports `App\Support\PastMonthPlanRefused` from `ApproveMonthPlan.php:54-57` and `ChangeMonthPlan.php:40`. The roadmap's Backlog Handoff note "obejmuje MPL-1" (`context/foundation/roadmap.md:231`) is out of date. Only the reminders are left.
- **Dashboard notices.**
  - `app/Support/DashboardNotices.php:72-267` builds a flat list. The order of appending is the contract (`.ai/rules/support.md`): exceeded kinds come first, then warning kinds (`month_plan_not_approved` :234, `month_pace_ahead` :244, `collected_not_covered` :255).
  - `month_plan_not_approved` appears from day 3 of the current month while the plan is a Proposal. Its value slot is empty (ADR-0050 label table).
  - The prop `dashboardNotices` goes out at `DashboardController.php:148`. Its TS union lives in `resources/js/types/dashboard.ts:8-68`.
  - Copy goes through `resources/js/lib/dashboard-notice-presentation.ts:39-119` with strings in `lang/pl/ui.php:213-228`. Cards are rendered by `resources/js/components/DashboardNotices.vue`: 6 visible (:22), a `satisfies never` check (:212), and `noticeKey` uses `id` or `month` (:215-221).
- **The Dashboard knows only the current year's Budget** (`DashboardController.php:35-37`, `findBudgetForYear` :178-185). It never looks at year+1.
- **The deadline is real every month, not only in December.** `MonthPlanProposal::isPast` (`app/Support/MonthPlanProposal.php:206-208`) turns an unapproved past month into `'none'` (:175-176). The next month's Proposal then loses its source (`sourceFor` :210-224) and falls back to the annual share. ADR-0103:15 names the December deadline (31.12 23:59:59 Polish time).
- **A draft or missing Budget year refuses Import rows.** Income, expense and refund rows dated in that year are refused (`app/Support/CsvImport/FinancialOperationImportValidator.php:91-111`, ADR-0106). The preview links a draft year to its Budget (`import_budget_year_draft_link`, `lang/pl/ui.php:1039`) and opens the create-Budget dialog for a missing year (`useCreateBudgetDialog().openForYear`, used in `resources/js/components/ImportPreviewRow.vue`).
- **The draft Dashboard** shows the „Szkic” badge, the status line „budżet :year w szkicu” (`Dashboard.vue:61-78`, `lang/pl/ui.php:200-201`) and the card „Budżet jest w szkicu” (`DashboardBudgetBlock.vue:121-126`). It has no notice in „Wymaga uwagi”, and `YearTurnTest.php:224` pins `dashboardNotices` to `[]` for the forgotten start.
- **The year menu.**
  - `BudgetYearSwitcher.vue` and `resources/js/lib/budget-year-menu.ts:33-61` mark „bieżący” (the App Day year from `shell.today`) and „szkic” (`phase === 'draft'`) per ADR-0098.
  - ADR-0098 states that "a year in execution that is not the current one has no mark".
  - `shell.budgetYears` is `list<{year, phase}>` (`app/Support/ShellState.php:29`, `resources/js/types/shell.ts:9-12`).
- **`StartBudgetExecution` has no date guard** (`app/Actions/StartBudgetExecution.php:14`). The README „Przełom roku” starts N+1 on 30–31 December. It also warns that January can then be approved while it is still December.
- **Kitchen sinks.**
  - `/dev/ui/dashboard` renders the real `Dashboard.vue` from `resources/js/pages/dev/dashboard-fixtures.ts`. `dashboard-fixtures.test.ts:52-56` asserts exactly 8 notice kinds.
  - `/dev/ui/sidebar` (`resources/js/pages/dev/SidebarKitchenSink.vue`) passes `budgetYears` per frame (`years-*`, `frameCurrentYear = 2026` :169).
  - The Dashboard sink renders inside the real layout, so anything the Dashboard derives from `shell` shows the live data, not the scenario.

## Desired End State

- On 27.11.2026 with November in Proposal, the card reads „Plan niezatwierdzony · Listopad · zatwierdź do 30.11, 23:59”. On 29.12.2026 it reads „… · Grudzień · zatwierdź do 31.12, 23:59”.
- From 1.12.2026 (Polish day), while the 2026 Budget exists and the 2027 Budget is a draft, the last warning card reads „Budżet w szkicu · Budżet 2027 · Import nie przyjmie wierszy z 2027” and opens the Budget screen of 2027. If the 2027 Budget does not exist, the card reads „Brak budżetu · Budżet 2027 · Import nie przyjmie wierszy z 2027” and opens the „Dodaj rok” dialog for 2027. The card disappears once 2027 is in execution.
- On 2.01.2027 with the 2027 Budget still a draft (forgotten start), the same card shows for 2027 (current year, draft). A missing current-year Budget keeps its existing empty state and gets no card.
- On 30.12.2026, after „Rozpocznij realizację” of 2027, the year menu shows 2027 with an „od 1.01” mark. The Dashboard line reads „środa, 30 grudnia 2026 · budżet 2026 w realizacji · budżet 2027 od 1 stycznia” (`formatLongDate` includes the weekday, `resources/js/lib/format.ts:140-150`). On 1.01.2027 (Polish day) both marks are gone and 2027 is „bieżący”.
- The glossary, ADR-0108, design-system.md and the rule files describe all of this before the code lands. `composer ci:check`, Vitest, `npm run check:ui`, `npm run types:check` and Sloppy are green on PostgreSQL.

Verify with the Feature tests at the Polish midnight boundaries, the kitchen sinks and a browser check with `app:rehearsal-clock`.

### Key Discoveries:

- New warning kind needs its term and threshold in `context/foundation/glossary.md` before code (`.ai/rules/support.md`, `design-system.md:87`, ADR-0032).
- Next free ADR number is 0108 (highest in use: `0107-image-tags-and-rollback-without-build.md`).
- Precedent for a notice change: `context/archive/2026-11-13-collected-coverage/plan.md`. Its Phase 1 was docs only (glossary, ADR, "Amended by" lines in 0032/0050, rule and design-system lists), then a stop for confirmation. Then red tests, then the server, then the front end and the kitchen sink.
- Year select destinations are `budget | month-plan | reports | reports-trend | reports-years` (`app/Http/Requests/SelectBudgetYearRequest.php:27`). `budget` is the screen with „Rozpocznij realizację”.
- Freeze time at 22:59:59 / 23:00:00 UTC on 30 November and 31 December, never at 00:00 UTC (`.ai/rules/app-day.md`).

## What We're NOT Doing

- No change to the past-month rule. An unapproved month still cannot be approved after its last day (decision 1). Approving a past December after midnight is not built.
- No block, hint or notice on approving January while it is still December (decision 6). The README warning stays. The candidate goes to `development-directions.md`.
- No notice for "next year's Budget waits for 1 January" in „Wymaga uwagi”. That state is shown in the year menu and the Dashboard status line only (decision 4).
- No new notice severity (no `info`), no new colour token.
- No card for a missing Budget of the current year (the Dashboard empty state covers it), and no N+1 card while the current year has no Budget at all.
- No change to MPL-2…MPL-10 or to S-40 (session year), S-43 (Years report).
- No change to `StartBudgetExecution` (no date guard).

## Implementation Approach

The work follows the precedent of collected-coverage. Docs come first and end with a stop for the user's confirmation. Then the notices slice ships end to end: server, prop types on both sides in one commit with `assertInertia`, presentation, kitchen sink. The "waits for 1 January" slice comes last.

The domain decisions stay on the server:
- `DashboardNotices` decides both notices.
- `DashboardController` reads the next year's Budget with one query and passes it in.
- The Dashboard line gets a server prop rather than deriving from `shell`, so its kitchen sink can show it.

The year menu derives its mark in `budgetYearMenu` from `shell.budgetYears` and `shell.today`, the same way ADR-0098 already does for „bieżący” and „szkic”.

## Critical Implementation Details

- **Notice order.** `budget_not_in_execution` is appended after `collected_not_covered`, the last of all kinds (decision 7). With two cards (a draft current year in December and a draft or missing next year), they follow year order. This kind does not depend on `$monthReading`, so append it outside the `if ($monthReading !== null)` block. A draft current-year Budget has no month reading at all.
- **Deadline wording.** "23:59" is the last minute of the Polish App Day of `approve_by`. The server sends only the date. The client formats it with the time from the copy string, so the browser never computes a deadline from a clock.

## Phase 1: Glossary and ADR-0108

### Overview

Write the rules down before any code (AGENTS.md hard rule; `.ai/rules/support.md`). Docs only, then stop for the user's confirmation.

### Changes Required:

#### 1. Glossary

**File**: `context/foundation/glossary.md`

**Intent**: Record the deadline, the new notice and the "waits for its year" state where the terms live. The glossary keeps describing only what the code will have after this change.

**Contract**:
- **Month Plan** (:101): after the day-3 sentence, add that the notice names the deadline. The deadline is the last day of the month at 23:59 Polish time (App Day, ADR-0103). After it the month is past, has no Proposal and cannot be approved.
- **Budget** (:31-33): two additions.
  - A `warning` Dashboard notice (`budget_not_in_execution`) is raised when the Budget of the current year is a draft. It is also raised in December, while the current year has a Budget, when the Budget of the next year is missing or a draft. The notice says that Import and hand entry refuse income, expense and refund dated in that year (ADR-0106).
  - A Budget in execution of a year later than the current calendar year waits for its year. The year menu marks it „od 1.01”, and the Dashboard status line names the next year's.
- Polish UI copy for both: „Budżet w szkicu” / „Brak budżetu”, „Import nie przyjmie wierszy z :year”, „od 1.01”, „budżet :year od 1 stycznia”.

#### 2. ADR-0108

**File**: `context/foundation/adr/0108-year-turn-notices.md` (new)

**Intent**: One decision record for the three reminders, in the style of ADR-0090 (rule, examples on dates, "We rejected").

**Contract**:
- **Rule.** `month_plan_not_approved` carries `approve_by` (the last day of its month) in every month. `budget_not_in_execution` (`warning`, `{year, state: 'draft'|'missing'}`) uses the conditions above and is last in the list. „od 1.01” is the year-menu mark for a Budget in execution with a year later than the App Day year.
- **Examples.** 27.11 (November in Proposal), 1.12 00:00 PL (the N+1 card appears), 30.12 after starting 2027 (no card, the „od 1.01” mark and the status line), 1.01 00:00 PL (the marks are gone), 2.01 with 2027 a draft (the card for the current year), and a first Budget in draft in March (the same card for the current year: the rule is not limited to the year turn, decision 5).
- **We rejected:**
  - approving a past month after midnight;
  - a separate deadline card;
  - a deadline only in December;
  - a window from 15.12 or 28.12;
  - a warning card for "waits for 1 January";
  - the new card first among warnings;
  - blocking January approval in December.
- **Amends** ADR-0032 (a new warning kind, last in the order), ADR-0050 (label table: new row, and the value slot of `month_plan_not_approved`) and ADR-0098 (a new mark; "a year in execution that is not the current one has no mark" now holds only for past years).

#### 3. Amended ADRs

**Files**: `context/foundation/adr/0032-*.md`, `0050-*.md`, `0098-*.md`

**Intent**: Add the "Amended by ADR-0108: …" line under the title, as earlier ADRs do for 0090 and 0103.

**Contract**: one sentence each. In 0050, also update the label table: the `month_plan_not_approved` value becomes „zatwierdź do DD.MM, 23:59”, and a new row is added: `budget_not_in_execution` | Budżet w szkicu / Brak budżetu | „Budżet :year” | „Import nie przyjmie wierszy z :year”.

#### 4. Design system and rules

**Files**: `context/foundation/design-system.md` (:80-87 alerts, :150 year menu), `.ai/rules/support.md`, `.ai/rules/js-sidebar.md`

**Intent**: Keep the lists of warning kinds and of year-menu marks true.

**Contract**:
- `support.md` adds `budget_not_in_execution` to the warning kinds.
- design-system adds the kind to the alert list, and „od 1.01” to the year menu with the outline style of „bieżący” (`sidebar-border` outline, `sidebar-foreground` text), with no new token.
- `js-sidebar.md` updates its ADR-0098 sentence to include the mark and ADR-0108.
- Run `php artisan boost:index-rules` if the rule frontmatter changes (it should not).

#### 5. Roadmap and directions

**Files**: `context/foundation/roadmap.md`, `context/foundation/development-directions.md`

**Intent**:
- Correct the S-41 handoff note: MPL-1 was closed by `1659a87`.
- Record the two S-41 unknowns as answered (warning only, no rule change).
- Add the candidate "approving January while it is still December reads incomplete fixed charges" to the directions list.

**Contract**: only the S-41 Unknowns line and its Backlog Handoff cell in the roadmap, plus one row or line in development-directions. Status sync is done by `/10x-plan` and `/10x-implement`, not here.

### Success Criteria:

#### Automated Verification:

- ADR file exists and is the only 0108: `ls context/foundation/adr/0108-*.md | wc -l` prints 1
- Glossary names the new kind and the deadline: `grep -c "budget_not_in_execution" context/foundation/glossary.md` ≥ 1 and `grep -c "23:59" context/foundation/glossary.md` ≥ 1
- Amended ADRs cite 0108: `grep -l "ADR-0108" context/foundation/adr/0032-*.md context/foundation/adr/0050-*.md context/foundation/adr/0098-*.md | wc -l` prints 3
- Rule files mention the kind and the mark: `grep -c "budget_not_in_execution" .ai/rules/support.md` ≥ 1 and `grep -c "0108" .ai/rules/js-sidebar.md` ≥ 1

#### Manual Verification:

- The user reads the glossary entries and ADR-0108 and confirms the wording before Phase 2 starts

**Implementation Note**: After this phase, stop for the user's confirmation of the docs before writing any code.

---

## Phase 2: Notices in „Wymaga uwagi”

### Overview

The deadline in the month-plan card and the new `budget_not_in_execution` card, end to end, with tests at the Polish midnight boundaries. Write the Feature tests first (red), then the server, then the front end.

### Changes Required:

#### 1. Notice list on the server

**File**: `app/Support/DashboardNotices.php`

**Intent**:
- Add `approve_by` to `month_plan_not_approved`.
- Append `budget_not_in_execution` last: one item for a draft current-year Budget, and in December one item for a missing or draft next-year Budget while the current year has a Budget.

**Contract**:
- `forDashboard(?Budget $budget, ?Budget $nextYearBudget, …)`: the new parameter goes right after `$budget` and is required, with no default, so "not passed" can never read as "no Budget next year". The controller is the only caller.
- The new kind is built in a private helper `budgetNotInExecutionNotices(?Budget $budget, ?Budget $nextYearBudget, CarbonImmutable $today): list<array{…}>`, merged at the end of `forDashboard`. `forDashboard` is already ~196 lines in the Sloppy baseline (SL101, `.sloppy-baseline.json:51-54`), so do not grow it inline.
- The docblock shape gains `approve_by: string` ('YYYY-MM-DD', the last day of `$today`'s month) on `month_plan_not_approved`, and a new member `{kind: 'budget_not_in_execution', severity: 'warning', year: int, state: 'draft'|'missing'}`.
- The order contract stays: severity first, then kind. The new kind comes after `collected_not_covered`, and two items follow year order.

#### 2. Controller

**File**: `app/Http/Controllers/DashboardController.php`

**Intent**: Load the next year's Budget (one query, the same ownership scope as `findBudgetForYear`) and pass it to `forDashboard`. Phase 3 reuses the same variable.

**Contract**: no new prop in this phase. `dashboardNotices` items change shape as above.

#### 3. Types, presentation and card

**Files**:
- `resources/js/types/dashboard.ts`
- `resources/js/lib/dashboard-notice-presentation.ts`
- `resources/js/components/DashboardNotices.vue`
- `lang/pl/ui.php`
- `tests/Unit/UiTranslationKeysTest.php` (:48-55, the hand-written list of runtime-composed `notice_label_*` keys)
- `app/Enums/DashboardNoticeSeverity.php` (:8, the docblock that lists the warning kinds)

**Intent**: The TS union mirrors the PHP shape in the same commit (AGENTS.md). The presentation gets new label keys, a `year` identity and two value types, and the card gets a link for the new kind.

**Contract**:
- `NoticeLabelKey` adds `budget_draft` and `budget_missing`.
- `NoticeIdentity` adds `{type: 'year', year}`, shown as „Budżet :year”.
- `NoticeValue` adds `{type: 'approve_by', date}` (copy „zatwierdź do :date, 23:59”, date as `DD.MM`) and `{type: 'import_refused', year}` (copy „Import nie przyjmie wierszy z :year”).
- In `DashboardNotices.vue`:
  - `noticeKey` (:215-221) handles `year` with its own branch (`kind-year-state`). Today it reads `notice.month` for every kind without `id`.
  - `noticeIdentity` (:104-110) maps the new kind to `{type: 'year', year}`. `isMonthNotice` (:78-94) is a hard-coded union and does not gain the new kind.
  - The link for `state: 'draft'` is the `budget.select-year` POST with `destination: 'budget'` and `year`, with the pending and failure handling of the month notices (:38-50, :168-170).
  - `state: 'missing'` is a button calling `useCreateBudgetDialog().openForYear(year)`. Every card is a `<Link>` today (:256), so the template gets a `<button>` branch with the same `.dashboard-notice` markup. `select-year` cannot serve this state: `SelectBudgetYearRequest.php:22-26` requires an existing Budget (422). The pattern to copy is `ImportPreviewRow.vue` (draft → `selectYear` Link :217-229, missing → `openForYear` :209).
  - The `satisfies never` check stays exhaustive.
- New keys in `lang/pl/ui.php` under `dashboard`: `notice_label_budget_draft`, `notice_label_budget_missing`, `notice_identity_budget_year`, `notice_value_approve_by`, `notice_value_import_refused`.

#### 4. Kitchen sink

**Files**: `resources/js/pages/dev/dashboard-fixtures.ts`, `resources/js/pages/dev/dashboard-fixtures.test.ts`, `resources/js/pages/dev/DashboardKitchenSink.vue`

**Intent**: Every new state is a scenario of the real `Dashboard.vue` (AGENTS.md, design system).

**Contract**:
- `allNoticeKindsProps` adds `budget_not_in_execution`, and the kind count becomes 9 (`dashboard-fixtures.test.ts:51-56`).
- All three `month_plan_not_approved` fixtures (`dashboard-fixtures.ts:150`, `:500`, `:513`) carry `approve_by`.
- `forgotten-start` sets its own `dashboardNotices`. `draftProps` (:326-330) inherits an empty list from `noBudgetProps`.
- The state-matrix copy in `DashboardKitchenSink.vue:27` („7 rodzajów”, already out of date) names the real count.
- New scenarios:
  - `december-before-turn`: deadline card plus 2027 draft card;
  - `next-year-missing`;
  - `forgotten-start`: draft current year with the card.
- Each new scenario is listed in the required keys of the fixture test.

#### 5. Tests

**Files**: `tests/Feature/DashboardTest.php`, `tests/Feature/YearTurnTest.php`, `resources/js/lib/dashboard-notice-presentation.test.ts`

**Intent**: Pin the thresholds at the Polish midnight boundaries and the order contract. Update the forgotten-start expectation, which is a changed requirement (decision 5), not a fitted assertion.

**Contract**:
- `DashboardTest` (`assertInertia` on `dashboardNotices`):
  - `approve_by` equals `2026-11-30` on 27.11, and `2026-12-31` on 31.12 at 22:59:59 UTC;
  - no N+1 card at `2026-11-30 22:59:59` UTC; a `{year: 2027, state: 'draft'}` card at `2026-11-30 23:00:00` UTC;
  - a `state: 'missing'` card when 2027 does not exist;
  - no card when 2027 is in execution;
  - no N+1 card when the user has no 2026 Budget;
  - `test_dashboard_orders_notices_by_severity_then_kind` extended so that the new kind is last.
  - `test_dashboard_has_no_month_notices_without_a_budget_in_execution` (:1312-1356, draft 2026 on 2026-09-23, today `has('dashboardNotices', 1)`) is a changed requirement too (decision 5): it now expects the existing notice plus `{kind: 'budget_not_in_execution', year: 2026, state: 'draft'}` last. It still asserts that no month notice appears.
- `YearTurnTest::test_forgotten_start_…` (:203-225): on 2027-01-02 `dashboardNotices` is one `{kind: 'budget_not_in_execution', year: 2027, state: 'draft'}`. After the start, no card.
- Vitest: presentation of both new value types and the `year` identity.

### Success Criteria:

#### Automated Verification:

- Dashboard and year-turn Feature tests pass on PostgreSQL: `docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing_year_turn_notices -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Feature/DashboardTest.php tests/Feature/YearTurnTest.php`
- Vitest passes for the presentation and the fixtures: `npm run test -- dashboard-notice-presentation dashboard-fixtures`
- Types and UI guards pass: `npm run build && npm run types:check && npm run check:ui`
- PHP style is clean: `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- `/dev/ui/dashboard` scenarios `all-notice-kinds`, `december-before-turn`, `next-year-missing` and `forgotten-start` look right in light and dark themes; the new card is last and uses the `warning` styling
- In `next-year-missing`, the card opens the „Dodaj rok” dialog for 2027; in `december-before-turn`, the 2027 card leads to the Budget screen of 2027

**Implementation Note**: After this phase and its automated checks, pause for the user's manual check of the kitchen sink before Phase 3.

---

## Phase 3: „Od 1 stycznia” in the year menu and on the Dashboard

### Overview

Show that a Budget in execution of a later year waits for its calendar year, then run the full gate and a browser check with the rehearsal clock.

### Changes Required:

#### 1. Year menu logic and switcher

**Files**: `resources/js/lib/budget-year-menu.ts`, `resources/js/lib/budget-year-menu.test.ts`, `resources/js/components/BudgetYearSwitcher.vue`, `lang/pl/ui.php`

**Intent**: Add the third independent mark to the year menu, derived from the same inputs as „bieżący” and „szkic” (ADR-0098, ADR-0108).

**Contract**:
- `BudgetYearMenuEntry` gains `waiting: boolean` = `phase === 'execution' && year > currentYear`.
- The switcher shows the pill „od 1.01” (`year_waiting` key) on the trigger and in the row, with a `.sidebar-year-state-waiting` class in `resources/css/app.css` using the outline style of `.sidebar-year-state-current`, and no new token.
- The mark is a description through `aria-describedby`, never part of the accessible name. `hasState` (`BudgetYearSwitcher.vue:107-109`, today `current || draft`) includes `waiting`, otherwise the trigger gets no `aria-describedby` and no pill container.
- The pill uses its own class and contains no four-digit number: `tests/e2e/support/budget-year.ts:29-56` reads the row's year with `/\d{4}/` and skips rows by `.sidebar-year-state-draft`.
- The existing `toEqual` cases in `budget-year-menu.test.ts` (:15-35, :81-95) gain `waiting: false` or `true`.
- Vitest covers:
  - 2026 current with 2027 in execution: 2027 waiting;
  - 2027 draft: not waiting;
  - 2025 in execution: not waiting;
  - current year 2027: not waiting.

#### 2. Sidebar kitchen sink

**File**: `resources/js/pages/dev/SidebarKitchenSink.vue`

**Intent**: A frame for the new state.

**Contract**: frame `years-next-waiting` (2025, 2026 in execution with 2026 current, 2027 in execution), shown closed and open.

#### 3. Dashboard status line

**Files**:
- `app/Http/Controllers/DashboardController.php`
- `resources/js/types/dashboard.ts`
- `resources/js/pages/Dashboard.vue`
- `lang/pl/ui.php`
- `resources/js/pages/dev/dashboard-fixtures.ts` and its test
- `tests/Feature/DashboardTest.php`

**Intent**: Name the waiting next-year Budget in the status line. The value is a server prop, so the kitchen sink can show it (the Dashboard sink renders with the live `shell`).

**Contract**:
- New prop `waiting_budget_year: int|null`: the next year when `$nextYearBudget` is in execution, else `null`. It is added on both sides in one commit, with `assertInertia`.
- `metaLine` appends ` · ` + `meta_budget_waiting` („budżet :year od 1 stycznia”) when the prop is not null.
- Kitchen sink scenario `next-year-waiting`.
- `DashboardTest`:
  - `waiting_budget_year` is 2027 at `2026-12-31 22:59:59` UTC after the start of 2027;
  - `waiting_budget_year` is `null` at `2026-12-31 23:00:00` UTC (the Dashboard is on 2027);
  - `waiting_budget_year` is `null` when 2027 is a draft.

### Success Criteria:

#### Automated Verification:

- Vitest passes: `npm run test`
- Full gate passes on PostgreSQL: `docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing_year_turn_notices -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint composer sail-8.5/app ci:check`
- Sloppy reports no new finding: `vendor/bin/sloppy ci`
- Types and UI guards pass: `npm run build && npm run types:check && npm run check:ui`
- App Day guard stays green: `tests/Unit/AppDayClockGuardTest.php` is part of the gate (no `today()`, `now()->year` or `new Date()` added)

#### Manual Verification:

- `/dev/ui/sidebar` frame `years-next-waiting` and `/dev/ui/dashboard` scenario `next-year-waiting` look right in light and dark themes; the „od 1.01” pill reads as a state, not as a warning
- Browser check with `app:rehearsal-clock` on a local copy:
  - at 2026-12-30 10:00 UTC with 2027 started: the menu shows „od 1.01” and the Dashboard line names 2027;
  - at 2026-12-31 23:30 UTC (00:30 PL): the mark is gone and 2027 is „bieżący”;
  - then `app:rehearsal-clock --off`

**Implementation Note**: After this phase, pause for the user's browser check before closing the change.

---

## Testing Strategy

### Unit Tests:

- Vitest: `dashboard-notice-presentation` (new label keys, `year` identity, `approve_by` and `import_refused` values), `budget-year-menu` (`waiting` truth table), `dashboard-fixtures` (9 kinds, new scenario keys).

### Integration Tests:

- Feature (PostgreSQL), with `travelTo` only at 22:59:59 and 23:00:00 UTC:
  - `DashboardTest`: the deadline per month, the N+1 card window and its states, no card without a current-year Budget, the order contract, `waiting_budget_year`;
  - `YearTurnTest`: forgotten start shows the card, and the start removes it.

### Manual Testing Steps:

1. Open `/dev/ui/dashboard?scenario=december-before-turn` and `forgotten-start`; check card order, copy and links in both themes.
2. Open `/dev/ui/sidebar` frame `years-next-waiting`; check the pill and the open menu.
3. Run the rehearsal clock steps from Phase 3 on a local copy and turn the clock off.

## Performance Considerations

One extra indexed query per Dashboard render (the Budget of year+1 by `user_id`, `year`). No query in a loop (Sloppy).

## Migration Notes

None. No schema change, and the prop changes are additive except the new `approve_by` field, which ships with its TS type in the same commit.

## References

- Roadmap slice: `context/foundation/roadmap.md` S-41 (:145-156)
- Year-turn rehearsal: `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md` (gap 1, T1, T5)
- Observability audit: `context/audits/observability/2026-11-19_import-operations-year-turn-backups.md` (MPL-1, closed by `1659a87`)
- Similar implementation: `context/archive/2026-11-13-collected-coverage/plan.md`; `app/Support/DashboardNotices.php:255` (`collected_not_covered`)
- ADRs: 0032, 0050, 0090, 0098, 0103, 0106
- Rules: `.ai/rules/support.md`, `.ai/rules/app-day.md`, `.ai/rules/js-sidebar.md`, `.ai/rules/inertia-props.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Glossary and ADR-0108

#### Automated

- [x] 1.1 ADR file exists and is the only 0108 — cc08f0ca
- [x] 1.2 Glossary names the new kind and the deadline — cc08f0ca
- [x] 1.3 Amended ADRs cite 0108 — cc08f0ca
- [x] 1.4 Rule files mention the kind and the mark — cc08f0ca

#### Manual

- [ ] 1.5 The user reads the glossary entries and ADR-0108 and confirms the wording before Phase 2 starts

### Phase 2: Notices in „Wymaga uwagi”

#### Automated

- [x] 2.1 Dashboard and year-turn Feature tests pass on PostgreSQL — c60e1625
- [x] 2.2 Vitest passes for the presentation and the fixtures — c60e1625
- [x] 2.3 Types and UI guards pass — c60e1625
- [x] 2.4 PHP style is clean — c60e1625

#### Manual

- [ ] 2.5 Kitchen sink scenarios look right in light and dark themes, the new card is last with warning styling
- [ ] 2.6 The missing-year card opens the „Dodaj rok” dialog and the draft-year card leads to the Budget screen

### Phase 3: „Od 1 stycznia” in the year menu and on the Dashboard

#### Automated

- [x] 3.1 Vitest passes
- [x] 3.2 Full gate passes on PostgreSQL
- [x] 3.3 Sloppy reports no new finding
- [x] 3.4 Types and UI guards pass
- [x] 3.5 App Day guard stays green

#### Manual

- [ ] 3.6 Sidebar frame and Dashboard scenario for the waiting year look right in light and dark themes
- [ ] 3.7 Browser check with the rehearsal clock around the Polish midnight
