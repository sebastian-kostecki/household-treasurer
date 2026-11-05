<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Kontrakt modali według Atramentu — CategoryFormDialog

- **Plan**: context/changes/category-dialog-atrament/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-05
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Automated criteria re-run: 92 tests (DesignTokens, DialogsKitchenSink, Budget, CategoryAppearance, PacedFlag, MonthItemKind) pass; `npm run check`, `check:ui`, `types:check`, `npm test` (204), `sloppy ci`, grep for the old focus ring: all green. Phase 5 `ci:check` on PostgreSQL passed earlier (879 tests).

## Findings

### F1 — Group fields do not link errors and hints to the control

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/CategoryFormDialog.vue:317-363, 416-432
- **Detail**: The `group` FormFields (type, colour, icon, item kind) use only `labelId` from the slot. `describedBy` and `invalid` are dropped, so `errors.type/color/icon/month_item_kind` and the item-kind hint are not tied to the group.
- **Fix**: Let `SegmentedControl`, `CategoryColorPicker` and `CategoryIconPicker` accept `ariaDescribedby` / `ariaInvalid` and forward them to the group element.
  - Strength: Uses what `FormField.vue:21-28` already computes.
  - Tradeoff: Three small prop additions.
  - Confidence: HIGH — slot already supplies the values.
  - Blind spot: Not tested with a real screen reader.
- **Decision**: FIXED via Fix (aria-describedby/aria-invalid forwarded; uncommitted)

### F2 — SegmentedControl can render without an accessible name

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/SegmentedControl.vue:7-8
- **Detail**: `ariaLabel` became optional next to `ariaLabelledby`; nothing requires one of them.
- **Fix**: Type the props as a union requiring one of the two.
- **Decision**: FIXED via Fix (union type; call sites use camelCase props; FormField `invalid` typed `'true'`; uncommitted)

### F3 — Field errors are not announced

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/InputError.vue:6-17
- **Detail**: The error `<p>` has no `role="alert"`; server errors that appear after submit are not read out. It is hidden with `v-show` on a wrapper.
- **Fix**: Render the `<p>` with `role="alert"` only when `message` is set.
- **Decision**: FIXED via Fix (single `<p v-if role="alert">`; fallthrough class now lands on the `<p>`; uncommitted)

### F4 — CreateBudgetDialog "Anuluj" keeps old errors

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/CreateBudgetDialog.vue:589-590
- **Detail**: Unlike `CategoryFormDialog`, cancel does not `reset()`/`clearErrors()`, so server errors reappear on reopen. Also `:aria-invalid="invalid ? 'true' : undefined"` at :633 is redundant.
- **Fix**: Align cancel with `CategoryFormDialog`; pass `invalid` directly.
- **Decision**: FIXED (cancel calls `clearErrors()`; redundant aria-invalid ternary removed; `reset()` skipped because `year` is a v-model ref; uncommitted)

### F5 — AccountFormDialog still uses hand-written fields

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/AccountFormDialog.vue:149-206
- **Detail**: The new rule says dialog fields are `FormField`, but this sibling was out of scope (plan "NOT doing"). It is the inconsistent one now.
- **Fix**: Track migration as a follow-up change.
- **Decision**: SKIPPED (separate change: migrate AccountFormDialog and other form dialogs to FormField)

### F6 — Sink-only props on production dialogs

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architecture
- **Location**: resources/js/components/CategoryFormDialog.vue:146-149, CreateBudgetDialog.vue:491
- **Detail**: `form` and `initialPlannedAmountInput` exist only for the kitchen sink and widen the public API; `initialPlannedAmountInput` overrides the real amount in edit mode. They are documented in JSDoc and match the plan's intent.
- **Fix**: Keep, since documented; drop `form: undefined` from `withDefaults` if unneeded.
- **Decision**: SKIPPED (documented, matches plan)

### F7 — Pickers use toggle-button semantics, not radio

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/CategoryColorPicker.vue:34-42, CategoryIconPicker.vue
- **Detail**: Single-choice pickers are `aria-pressed` buttons in `role="group"`. Colour labels are "Kolor N" (as the plan requires). Icon buttons may lack names.
- **Fix**: Check icon button labels; consider a radiogroup pattern later.
- **Decision**: SKIPPED (separate change: radiogroup pattern for pickers)

### F8 — Dev routes registered unconditionally

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: routes/web.php:28-29
- **Detail**: Matches the sibling `dev/ui/*` routes (controller `abort_unless(app()->isLocal(), 404)`, tested), so not exploitable. `dev/kitchen-sink/budget` uses an `if (app()->isLocal())` wrapper instead. The `validate` POST sleeps 1.5 s.
- **Fix**: Leave as is for consistency with `dev/ui/*`.
- **Decision**: SKIPPED (consistent with sibling dev/ui/* routes; controller guard + tests)
