<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Stan roku w menu „Rok budżetu” (S-38)

- **Plan**: context/changes/budget-year-menu-state/plan.md
- **Scope**: Full plan (all three phases)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-14
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 5 observations

## Verification run

- `ls context/foundation/adr | grep -c '^0097-'` → 1; `grep -l ADR-0097` returns both `.ai/rules/js-sidebar.md` and `context/foundation/design-system.md`.
- `npm test` → 59 files, 677 tests pass; `budget-year-menu.test.ts` 10 pass.
- `npm run check` (format + lint), `npm run check:ui`, `npm run types:check` → pass.
- PostgreSQL in the worktree: `BudgetTest`, `AppShellTest`, `LoadBudgetSheetTest` → 90 passed (1161 assertions).
- Full gate `composer ci:check` in the worktree container → exit 0, 1707 passed, 2 skipped.
- `vendor/bin/sloppy ci` → passes; 1 medium finding (SL113 in `app/Support/SheetLoad/MonthSheetReader.php`, a file this change does not touch).
- NOT re-run: Playwright E2E (3.4, `sidebar-year-refresh.spec.ts`, `command-palette.spec.ts`) — needs the running app and credentials; relies on the checkmark from commit 3b449d6.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Findings

### F1 — „Dodaj rok N” is set in mono

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/css/app.css:656-658, resources/js/components/BudgetYearSwitcher.vue:279
- **Detail**: `.sidebar-year-menu-item` carries `font-mono tabular-nums` and is applied to both the year radio rows and the "Dodaj rok N" `DropdownMenuItem`. The plan asks for mono only on the year (button and year rows); "Dodaj rok 2028" is a sentence, and the Atrament card sets only the year in mono. Reported independently by both review agents; confirmed in the code.
- **Fix**: Move `font-mono tabular-nums` from `.sidebar-year-menu-item` to the year text in the radio rows (a small `.sidebar-year-menu-year` class, or `font-mono` on the year span), so the "Dodaj rok" item keeps the shared highlight and ring only.
- **Decision**: FIXED — mono moved to a new `.sidebar-year-menu-row` class on the year rows only; "Dodaj rok N" keeps the shared item class.

### F2 — Manual checks still open while `change.md` says `implemented`

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: plan.md Progress rows 1.3, 2.5, 3.5, 3.6, 3.7, 3.8
- **Detail**: All automated rows are `[x]` with commit SHAs and the checks above agree. Six manual rows are `[ ]`: ADR vs card, Dashboard unchanged, all kitchen-sink states in both themes, accessibility tree (name "Rok budżetu 2026" described as "bieżący", "2027" described as "szkic"), phone sheet, landing pill. One concrete thing to look at in 3.5: the checked row and the keyboard-focused row share `bg-sidebar-accent` (`.sidebar-year-menu-item`), so with focus on another row the two look alike and only the ring and the check mark differ.
- **Fix**: Do the six manual checks in `/dev/ui/sidebar` and `/dev/ui/welcome` (both themes, keyboard focus on a non-selected row, ≤768 px) before archiving; tick the rows; if the checked/focused rows are indistinguishable, give the focus state its own treatment in `.sidebar-year-menu-item`.
  - Strength: These are the only criteria that prove the visual match with the Atrament card and the accessible-name contract.
  - Tradeoff: Needs a human with a browser; cannot be done from tests.
  - Confidence: HIGH — the plan marks them as required before closing.
  - Blind spot: Nobody has looked at the rendered menu in this review.
- **Decision**: RESOLVED — the six manual checks were done by the user on the preview (http://localhost:8091) and ticked in Progress.

### F3 — The accessibility contract has no automated guard

- **Severity**: 👁 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/BudgetYearSwitcher.vue (ids, `aria-describedby`, headings)
- **Detail**: The pure logic is well tested, and `sidebar-year-refresh.spec.ts` keeps the accessible names ("2026", "Dodaj budżet na rok N"). Nothing asserts that each `aria-describedby` id points at an existing element, that the headings "Ostatnie"/"Starsze" appear only from 4 years, or that the caption is described. A rename of an id breaks the descriptions silently; 3.6 is checked by hand only.
- **Fix**: Add one E2E assertion (or a small component test) on `aria-describedby` for the button, one draft row and "Dodaj rok", plus the headings from 4 years.
  - Strength: Locks the ADR-0097 contract; the E2E helper and kitchen sink already exist.
  - Tradeoff: One more browser-level test to maintain.
  - Confidence: MED — depends on whether the E2E data has a draft and 4+ years.
  - Blind spot: Have not checked what the existing spec seeds.
- **Decision**: PENDING — the repo has no component-test setup; needs an E2E assertion that cannot be verified without the running app and credentials.

### F4 — `roadmap.md` status bump is not committed

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: context/foundation/roadmap.md
- **Detail**: Working tree has a 2-line change (S-38 `ready` → `in-progress`, table and `Status:`), not in any of the five commits, including the epilogue 7b1fc9e. It belongs to this change.
- **Fix**: Commit it with the change (or set S-38 to the final status when closing).
- **Decision**: FIXED — roadmap.md status bump committed with the review.

### F5 — Kitchen sink frames `expanded-*` follow the real clock

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/dev/SidebarKitchenSink.vue (`frames`, around the `expanded-years` frame)
- **Detail**: The `years-*` frames pin `current-year` to 2026, but the frames that render the whole `AppSidebar` have no such prop, so their "bieżący" mark moves with the clock (from 2027 it leaves 2026). No open-menu frame shows a row that is both current and draft; that combination exists only as a closed button (`years-current-draft`).
- **Fix**: Either pass the year through `AppSidebar` for the sink or accept it; optionally add a current+draft row to the open-menu frame.
- **Decision**: SKIPPED — cosmetic; the years-* frames are pinned, the full-sidebar frames follow the clock.

### F6 — Duplicate years with different phases are not pinned by a test

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/budget-year-menu.ts:40-43, resources/js/lib/budget-year-menu.test.ts
- **Detail**: With duplicates the last entry's phase wins (`Map.set`). The DB has `unique(user_id, year)`, so it cannot happen, and the duplicate test uses only `execution`.
- **Fix**: Add one case with conflicting phases that states which wins, or leave as is.
- **Decision**: FIXED — test pins that the last entry wins on a duplicate year.

### F7 — Two budget-year queries per request (accepted in the plan)

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: app/Support/ShellState.php:28-29, app/Support/SelectedBudgetYear.php:20,73
- **Detail**: `forRequest` calls `budgetYearEntriesForUser` and then `resolve`, which calls `budgetYearsForUser`: two queries on the same table. Before the change it was also two, so there is no regression; the plan explicitly keeps `resolve` untouched. `DashboardController` calls `forRequest` again, so a Dashboard request does four.
- **Fix**: Later, let `resolve` take the loaded entries. Not for this change.
- **Decision**: SKIPPED — the plan keeps resolve untouched; not a regression.
