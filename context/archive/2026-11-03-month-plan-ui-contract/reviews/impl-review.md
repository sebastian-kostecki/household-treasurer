<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Kontrakt UI widoku Plan miesiąca

- **Plan**: context/changes/month-plan-ui-contract/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-03
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Verification run (2026-11-03)

- PostgreSQL (Sail image): DevMonthPlanSinkTest, MonthPlanPageTest, CategoryMonthItemKindTest, MonthPlanHardcodedUiTest: 59 passed (836 assertions)
- `npm test`: 89 passed (10 files); `npm run check`: no warnings; `npm run types:check`: clean
- `vendor/bin/sloppy ci`: 0 findings
- Not re-run: `composer ci:check` (full gate)

## After triage (2026-11-03)

All five findings fixed. Re-verified: `npm run check`, `npm test` (89), `npm run types:check`, Pint and `vendor/bin/sloppy ci` clean; MonthPlanHardcodedUiTest + MonthPlanPageTest + DevMonthPlanSinkTest + CategoryMonthItemKindTest 59 passed (838 assertions) on PostgreSQL in a private database (the shared `testing` DB gave flaky "users does not exist" failures while another process used it). `npm run build` OK and the preview was checked.

## Findings

### F1 — Screenshots "proposal" are byte-identical to "approved"; the proposal/Zatwierdź state was never captured

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: context/changes/month-plan-ui-contract/screenshots/phase-1-month-plan-*, phase-3-month-plan-*, phase-4-month-plan-proposal-*
- **Detail**: md5 of `phase-{1,3}-month-plan-proposal-{390,1280}.png` equals the matching `-approved-` file. `phase-4-month-plan-proposal-390.png` shows "Zatwierdzone" + "Zmień plan". The demo seed has an approved plan, so the proposal state (sticky bar with "Do wydania" + "Zatwierdź", amount error under a field, "Niepoprawne kwoty: N") was never photographed. Manual 3.4 ("Zatwierdź widoczne u dołu przy 390") and 4.7/4.8 are ticked with no visual evidence outside the kitchen sink.
- **Fix**: Create a proposal month in the preview DB, retake `/month-plan` proposal at 390 and 1280 (also with one invalid amount), replace the duplicates, delete the misleading files.
  - Strength: Closes the only unverified path (edit bar in approve mode on a real page, sticky behaviour inside `<Form>`).
  - Tradeoff: Needs the preview rebuilt (a few minutes of build).
  - Confidence: HIGH — the checksums are identical.
  - Blind spot: Sticky bar was not seen in a real proposal; it may still be fine.
- **Decision**: FIXED via Fix now. Retook the proposal state (current-month plan removed in the throwaway preview DB, restored afterwards): `phase-5-month-plan-proposal-{390,1280}.png` plus `phase-5-month-plan-proposal-invalid-{390,1280}.png` (field "12,3,4" + Tab). Verified at 390: the bar stays at the bottom mid-scroll, field has red border and "Wpisz kwotę, np. 1200,50.", bar says "Niepoprawne kwoty: 1" with "Zatwierdź" disabled. The duplicate `phase-{1,3}-month-plan-proposal-*` files were removed and `phase-4-month-plan-proposal-*` renamed to `-approved-`. Note: the full-page 390 capture draws the sticky bar mid-page (a Playwright full-page artifact); the `-invalid-` viewport shots are the evidence.

### F2 — Progress ticks without evidence: 5.5 has no final screenshots; 4.6 and 4.7 carry no SHA

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/month-plan-ui-contract/plan.md (Progress 4.6, 4.7, 5.5)
- **Detail**: 5.5 ("Zrzuty końcowe ... porównane z fazą 1") is `[x]` but no `phase-5-*` files exist; phase 5 changed no view code, so the phase-4 shots are de facto final. 4.6 (kind save error under the row) and 4.7 (12,3,4 + Tab) are `[x]` without a commit SHA, unlike every other step; 4.6 only has the kitchen sink to back it (no real failing PATCH).
- **Fix**: Note in Progress that 5.5 was satisfied by the phase-4 shots (or retake after F1), and add the SHA or a "kitchen sink only" remark to 4.6/4.7.
- **Decision**: FIXED via Fix now. Progress 4.6 now says "tylko kitchen sink", 4.7 points to `phase-5-month-plan-proposal-invalid-*`, 5.5 names the phase-4 and phase-5 shots (no commit SHA added: this fix lands in the review-fix commit itself).

### F3 — Kind switch markup duplicated in MonthPlanItemRow

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/MonthPlanItemRow.vue (mobile block under the name and `sm+` column block)
- **Detail**: The `can_change_kind` block (SegmentedControl + Spinner + InputError) appears twice, for `<sm` under the name and `sm+` in the column, differing only in wrapper. Both instances exist in the DOM, so there are two `aria-label` groups and two error nodes (one `display:none`). Harmless today, but a change to one copy can miss the other.
- **Fix**: Extract a small `MonthPlanKindCell` used by both placements.
- **Decision**: FIXED via Fix now. Extracted `MonthPlanKindCell.vue` (switch, spinner, error, read-only label); `MonthPlanItemRow.vue` uses it for the `<sm` and `sm+` placements. Retook `phase-5-month-plan-changing-{390,1280}.png`: 6 accessible kind groups, one per expense row.

### F4 — ColumnHeaderHint is outside the hard-coded-value scan

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: tests/Unit/MonthPlanHardcodedUiTest.php:7-16
- **Detail**: `MonthPlan.vue` imports `ColumnHeaderHint.vue`, which is not in `MONTH_PLAN_VIEW_FILES` (it has no arbitrary values today). The list follows the plan literally, so a future literal there would pass CI.
- **Fix**: Add `ColumnHeaderHint.vue` to the list.
- **Decision**: FIXED via Fix now. Added `ColumnHeaderHint.vue` (and the new `MonthPlanKindCell.vue`) to `MONTH_PLAN_VIEW_FILES`.

### F5 — Progress steps 2.6 / 3.6 lost the "Rodzaj" column check after the user decision

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/changes/month-plan-ui-contract/plan.md (Progress 2.6, 3.6)
- **Detail**: The phase blocks were updated for the "Rodzaj" column (`.month-plan-grid-kind`, header cell hidden below `sm`), but Progress 2.6 and 3.6 still list only the old three columns. The code matches the updated phase text; only the Progress wording lags.
- **Fix**: Sync the Progress text with the phase blocks.
- **Decision**: FIXED via Fix now. Progress 2.6 and 3.6 now describe the `.month-plan-grid-kind` column and the "Rodzaj" header.
