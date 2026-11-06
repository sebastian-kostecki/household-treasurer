<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Dashboard według strony design systemu (ADR-0045) — plan implementacji

- **Plan**: context/changes/dashboard-design-system-page/plan.md
- **Scope**: Phase 1 of 5
- **Reviewed phases**: 1
- **Date**: 2026-11-06
- **Verdict**: APPROVED
- **Findings**: 0 critical 0 warnings 3 observations

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

Commit 8bd1946 changes: ADR-0050 (new), ADR-0045 (one return sentence), `design-system.md`, `.ai/rules/js.md`, plus change.md, plan.md and plan-brief.md (change documents, not code). No PHP, Vue or TS file was touched, which matches "Phase 1 = documents only".

Automated criteria (run in the worktree):
- 1.1 `ls context/foundation/adr | grep 0050` returns exactly one file, `0050-dashboard-notice-labels-and-budget-block.md`: PASS.
- 1.2 `php artisan boost:index-rules` finished ("Regenerated .ai/rules/index.md"); `git status` afterwards shows only the pre-existing `M plan.md`, so the index has no unintended diff: PASS.
- 1.3 `grep -rn "severity only in the icon tile" .ai/rules` returns no hits (rc=1): PASS.

Claims in the docs checked against code:
- `planSummary` exists in the draft phase: TRUE. `DashboardController.php` assigns `$planSummary = BudgetPlanSummary::forBudget(...)` inside `if ($budget !== null)` and before the `isExecution()` branch, and returns `'planSummary' => $planSummary`. `Dashboard.vue:259-260` renders `BudgetPlanSummaryStrip` only for `budget.phase === 'draft'`, so ADR-0050's "plan measures in both phases" is feasible without a backend change.
- Notice payload fields match `DashboardNotices.php` and the `DashboardNotice` type in `DashboardNotices.vue`: `negative_account` / `negative_term_deposit` (`name`, `amount_minor_units`), `month_to_spend_negative` (`month`, `amount_minor_units`), `month_item_over_plan` / `one_off_over_limit` (`category_name`, `amount_minor_units`; both negative, so "keeps its sign" holds), `month_plan_not_approved` (`month` only, no value, so the empty slot is correct), `month_pace_ahead` (`percent`, `elapsed_day`, `days_in_month`). Seven kinds, as the ADR table says.
- `monthElapsedPercent(elapsedDay, daysInMonth)` exists in `resources/js/lib/month-pace-calculator.ts:71` and is already used for the pace notice, as the ADR describes.
- ADR-0037 does state that there is no "close to the limit" state because the glossary has no threshold: TRUE. The "przekroczono" word, the footer texts and the phone grid (Plan and Realizacja hidden below `md`) exist as described (`lang/pl/ui.php`, `app.css` `.category-meter-*`).
- `@/lib/dashboard-notice-presentation` does not exist yet; this is planned for Phase 2 (see F2).
- The statements about `Notice/README.md` and `StatPanel/README.md` (claude.ai artifact) could not be verified from the repository; they rest on `research.md`.

Contradiction scan of `design-system.md` and `.ai/rules/js.md` against ADR-0050: no remaining statement of tabs, four measures or the two-line card as current rules. The remaining hits are all negations ("nie ma zakładek", "no tabs", "Dwulinijkowa karta ... już nie obowiązuje"), the Operations tabs (`segmented-control-tabs`), or the ADR-0043 entry in the intro list, which carries its supersession note. Two small wording mismatches are in F1.

Manual Progress items 1.4 and 1.5 are correctly still `[ ]` (no tick without evidence). The ADR content covers every item in the 1.4 list (table of seven kinds, sign and identity rule, block layout, draft without Switch, phone layout, storage rule, artifact contradictions, rejected options).

## Findings

### F1 — "Panel" wording in js.md and the intro list contradicts ADR-0050's "one zone, not one frame"

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: .ai/rules/js.md:39
- **Detail**: `js.md:39` (not edited in Phase 1) still says 'the panel "Budżet <year>" holds two `ListSurface` tables'. "Panel" is the reading ADR-0050 rejects (it reads "one panel" as one zone with sibling plan measures and tables). `design-system.md:3` also still lists ADR-0043 as "jeden panel realizacji z zakładkami" (historical, with the supersession noted at the end of the same sentence, so only a nuisance).
- **Fix**: In `js.md:39` replace "the panel "Budżet <year>" holds" with "the block "Budżet <year>" (title row, plan measures and) holds", matching ADR-0050 and `design-system.md:90`.
- **Decision**: FIXED — js.md:39 now names the block (title row, plan measures, panels side by side, ADR-0050)

### F2 — Docs describe code that does not exist yet

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: context/foundation/design-system.md:77
- **Detail**: `design-system.md:77`, `:90`, `:98` and `js.md:47-48` already state the target behaviour as fact (labels and values on the card, `@/lib/dashboard-notice-presentation`, the `dashboard-year-elapsed` helper, the Switch in the block). Today `DashboardNotices.vue` still renders a title and a sentence with `Math.abs` amounts, `DashboardStatPanel` still has four measures and the helper does not exist. The plan chooses this deliberately (docs describe the code that will exist, and research found the docs ahead of the code already), but `AGENTS.md` warns "Docs describe more than the code". Until Phases 2-5 land, an agent reading the rules gets the new state while the code shows the old one.
- **Fix**: Accept while the change is in progress (Phases 2-5 are on the same branch); do not merge Phase 1 alone, and re-check these passages in the final full review.
  - Strength: Matches the plan's order (decision before code) and the precedent of `accounts-design-system-page`.
  - Tradeoff: A window in which docs lead the code, risky only if the branch stalls.
  - Confidence: HIGH — the same pattern was used in the previous change.
  - Blind spot: Whether the branch will be merged in pieces.
- **Decision**: ACCEPTED — docs lead code by design (AGENTS.md: decision before code); Phases 2–5 land on the same branch, re-check in the final review

### F3 — Progress stamps and manual confirmation not committed

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/dashboard-design-system-page/plan.md:405
- **Detail**: Steps 1.1-1.3 are ticked and stamped `— 8bd1946` in the working tree only (`M plan.md`), because the SHA exists only after the commit. Steps 1.4 and 1.5 await the human confirmation the plan's Implementation Note requires before Phase 2. `plan-brief.md` is in the same commit although Phase 1 of the plan does not list it (harmless extra).
- **Fix**: After the manual confirmation of 1.4 and 1.5, commit the Progress stamps (for example `docs(dashboard-design-system-page): Progress p1`) before starting Phase 2.
- **Decision**: ACCEPTED — SHA stamps are committed with the next phase commit per 10x-implement ritual; manual 1.4/1.5 left for the user
