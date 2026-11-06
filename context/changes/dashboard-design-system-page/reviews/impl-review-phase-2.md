<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Dashboard design system page

- **Plan**: context/changes/dashboard-design-system-page/plan.md
- **Scope**: Phase 2 of 5
- **Reviewed phases**: 2
- **Date**: 2026-11-06
- **Verdict**: APPROVED
- **Findings**: 0 critical 0 warnings 3 observations

Reviewed as committed in 4a2ddff (Phase 3 files in the working tree were not judged).

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Evidence

- `npm run test`: 30 files, 289 tests passed (includes the new tests and the untouched dashboard-fixtures test).
- `npm run types:check`: clean.
- `npm run check`: fails only on `DashboardNotices.vue` and `DashboardStatPanel.vue` in the working tree (uncommitted Phase 3 edits, e.g. double-quoted import). No Phase 2 file is flagged; the committed `DashboardNotices.vue` diff uses single quotes.
- Storage helper: matches the contract (`false` only for stored `'false'`, null/undefined storage/garbage/throw give `true`, default `window.localStorage` with SSR guard). Both catch blocks call `console.warn(message, error)`; no empty catch. Tests assert `warn` called once and that its args contain the thrown error, for read and for write (write also asserts no throw).
- Notice presentation: one test per kind (7 of 7), each with `toEqual` on labelKey, identity and value. `name` is used for accounts/deposits, `category_name` for over-plan/over-limit (tests use differing `name` and `category_name`, so a mix-up would fail). Negative amounts stay negative (-45000, -10000, -12300, -5000); `month_plan_not_approved` gives `value: null`; pace gives payload `percent` 80 and `daysPercent` 50 from 15/30 via the existing `monthElapsedPercent`.
- Type move: `DashboardNotice` (and `DashboardNoticeSeverity`) now live in `resources/js/types/dashboard.ts`; the circular `import ... from '@/components/DashboardNotices.vue'` in that file was removed. `grep` shows the only imports of the type are from `@/types/dashboard` (component, lib, test). No `.vue` import remains.
- `lang/pl/ui.php`: all nine new keys and values match the plan text exactly; `execution_panel_title` changed to `Budżet :year`. No other source or test references "Realizacja budżetu". Orphan cleanup is correctly left to Phase 5.
- Progress boxes 2.1-2.4 are ticked; the manual item remains open as the plan requires.

## Findings

### F1 — Red-first cannot be seen from the history

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: commit 4a2ddff
- **Detail**: Tests and implementation landed in one commit, so criterion 2.1 ("tests were red first") is not verifiable from git. The test content itself is behaviour-level and would fail without the implementation.
- **Fix**: Accept; optionally note in the review that red was observed, or split test and implementation commits in later phases.
- **Decision**: ACCEPTED — the red run (module not found, 2 files failed) is recorded in the implementing agent report; the break-check at commit time turned both guarding tests red

### F2 — No assertion for the default-storage path or a negative `one_off_over_limit` amount

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: resources/js/lib/year-elapsed-mark-storage.test.ts, resources/js/lib/dashboard-notice-presentation.test.ts:75
- **Detail**: The `storage === undefined` branch (window.localStorage / SSR guard) is untested, and the one-off over-limit case uses a positive amount, so sign retention is proven by the other four money kinds only. Both are outside the plan's listed cases.
- **Fix**: Optionally add one test stubbing `window.localStorage` via `vi.stubGlobal`, and a negative amount in the over-limit case.
- **Decision**: FIXED (partly) — one_off_over_limit test now uses -7000 like the payload (DashboardNotices.php sends the negative remainder); the sink fixture amounts for month_item_over_plan and one_off_over_limit were made negative in d555f82. Default-storage branch left untested (window guard is trivial; covered by manual check 4.9)

### F3 — `npm run check` is red in the working tree because of Phase 3 edits

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: resources/js/components/DashboardNotices.vue, resources/js/components/DashboardStatPanel.vue
- **Detail**: The formatter flags these two uncommitted files; Phase 2 as committed is clean. Not a Phase 2 defect, but the gate must be green before the Phase 3 commit.
- **Fix**: Run `npm run check:fix` when Phase 3 editing finishes.
- **Decision**: FIXED — vp check --fix before the Phase 3 commit d555f82
