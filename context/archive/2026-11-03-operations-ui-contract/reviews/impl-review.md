<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Kontrakt UI widoku Operacje

- **Plan**: context/changes/operations-ui-contract/plan.md
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
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Success criteria re-run: `npm test` (135 passed), `npm run check`, `npm run check:ui`, `npm run types:check` pass; PHP tests (OperationsFilter, OperationsEmptyState, DevOperationsSink, OperationsPagination, FinancialOperationEditDelete, CapitalMovement, OwnTransfer) 84 passed on PostgreSQL via the Sail image; guard exits 1 on a planted `hover:bg-foreground/[0.03]`; `vendor/bin/sloppy ci` 0 findings. Manual items are ticked in Progress with commit SHAs and screenshots exist for every phase.

## Findings

### F1 — Busy state cleared by any visit's `finish`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/operations-list-visit.ts:30-40
- **Detail**: One shared `busy` boolean; the `finish` listener does not check which visit finished. A POST/DELETE from a dialog, or the cancelled earlier list visit (page 2 then page 3 quickly), clears `aria-busy`/dimming while the newest list GET is still in flight. The test has no overlapping-visit case.
- **Fix**: Count in-flight list visits (increment on matching `start`; on `finish` decrement only if `isOperationsListVisit(event.detail.visit, …)`), report busy while count > 0; add an overlapping-visit test.
- **Decision**: FIXED (counter of in-flight list visits; finish filtered by visit; 2 tests added)

### F2 — Tooltip trigger is a tab stop with `role="group"` and duplicated label

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/OperationDescriptionCell.vue:45-48
- **Detail**: Prescribed by the plan (tabindex="0" + role="group"). Every row with a bank description becomes a tab stop before its edit/delete actions, `group` is not an interactive role, and the `aria-label` embeds the raw statement while the tooltip also exposes it via `aria-describedby`, so screen readers can announce it twice. Rows without a bank description behave differently.
- **Fix A ⭐ Recommended**: Keep the tab stop but shorten `aria-label` to the primary line only (the open tooltip supplies the statement via `aria-describedby`).
  - Strength: Keeps keyboard access required by the plan; removes the duplicate announcement; one-line change.
  - Tradeoff: Closed-state screen reader users only hear the statement when the tooltip opens on focus.
  - Confidence: MED — reka-ui `aria-describedby` behaviour not verified in a browser.
  - Blind spot: No screen-reader test was run.
- **Fix B**: Make the trigger a real `button` (type="button") with the same classes.
  - Strength: Proper interactive semantics, no `role="group"` hack.
  - Tradeoff: Changes markup and focus ring recipe; goes beyond the plan.
  - Confidence: MED — truncation/layout inside a button not tried.
  - Blind spot: Whether the row grid is affected.
- **Decision**: FIXED via Fix A (aria-label = primary line only)

### F3 — `aria-disabled` on role-less span, also on the ellipsis item

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/PaginationLinks.vue:56-58
- **Detail**: Plan prescribed `span` + `aria-disabled="true"`. The "..." gap item gets the same attribute although it is not a disabled control, and `aria-disabled` on a span without a role is announced unreliably. Shared component (~9 pages); no test asserts this markup.
- **Fix**: Omit `aria-disabled` for the ellipsis label.
- **Decision**: FIXED (no aria-disabled on the '...' item)

### F4 — `.dashboard-holdings-row` sits inside the Operations block of app.css

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/css/app.css:496-500
- **Detail**: The class sits between `.operations-header-row` and `.operations-row`, splitting the pair under the Operations comment, though it belongs to the dashboard.
- **Fix**: Move it next to the other dashboard classes in app.css.
- **Decision**: FIXED (.dashboard-holdings-row moved to its own block before Operations)

### F5 — Capital movement button reuses the transfer icon

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/OperationsPageActions.vue:69,82
- **Detail**: Plan asked for icons consistent with `OperationTypeTile`. Both "Dodaj transfer" and "Dodaj ruch kapitału" use `ArrowLeftRight` (verified), so the buttons differ only by text. Not-a-defect-in-behaviour, weak drift from the plan's intent.
- **Fix**: Give the capital movement button the icon `OperationTypeTile` uses for capital movements (or a distinct lucide icon).
- **Decision**: FIXED (capital movement button uses Landmark; OperationTypeTile has no capital-movement variant)
