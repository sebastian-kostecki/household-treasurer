<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Poprawki po 1.4.3: ciemny motyw, pickery i Plan miesiąca

- **Plan**: context/changes/v1-4-3-fixes/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-14
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 7 observations
- **Triage**: 2026-11-14 — fixed F1, F2, F4, F6, F7, F8, F9; accepted F5; F3 deferred to the user

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Success criteria re-run on 2026-11-14 at 1480e06:
- `composer ci:check` in `sail-8.5/app` on PostgreSQL (`testing_v1_4_3_fixes`): exit 0, 1672 passed, 2 skipped.
- `vendor/bin/sloppy ci`: passed. Its one medium finding (SL113, `app/Support/SheetLoad/MonthSheetReader.php:204`) is in a file this change does not touch.
- `npm test`: 671/671. `npm run types:check` and `npm run check:ui`: clean.
- E2E was not re-run; Progress 5.4 records it green at 51790d9.

Not findings, but worth knowing:
- Every query in `OperationsFilterOptions` and in the term deposit payloads is scoped to the user.
- There is no N+1.
- `month-plan-deviation-tone.ts` matches the ADR-0092 tables case by case.
- `minorUnitsToGroupedMoneyInput` round-trips through the parser.
- The E2E spec edits replace stale locators and add a scroll. No assertion was weakened.

## Findings

### F1 — Contrast test passes over a missing tint token with a value the browser never uses

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Unit/CategoryTileContrastTest.php:100
- **Detail**: When a token is absent, `categoryTileShare()` falls back to 0.15 / 1.0 (:112-113). The browser does something else:
  - A token missing only in `.dark` inherits the `:root` value.
  - A token missing everywhere makes `var()` invalid, so `color-mix()` is dropped.

  Either way the test checks a pair that never renders. Also, `(float) rtrim(...)` turns a non-percentage value such as `calc()` into 0. The test guards D4 only while both tokens stay literal percentages in both blocks.
- **Fix**: Assert that both tints exist in both blocks and match `/^\d+(\.\d+)?%$/`, and drop the fallback.
- **Decision**: FIXED — `categoryTileShare()` asserts each tint exists in both blocks and is a plain percentage; fallback removed. Proven red with the dark icon tint deleted and with `calc(60%)`, green on the real CSS.

### F2 — Planned 390 px frame class for the Month Plan sink was not built and the departure is not recorded

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/dev/MonthPlanSink.vue:301 (plan: Phase 4 §2, "ramka 390 px … wzór `.report-phone-frame`")
- **Detail**: `app.css` has no Month Plan frame class. The sink only says "przy oknie 390 px". The plan item was flawed: the 2×2 layout switches on the viewport `sm` media query, so a 390 px frame inside a wide window could never show it. Manual step 4.5 was checked against a real 390 px window. Neither the plan nor ADR-0092 records why the frame was dropped.
- **Fix**: Add a one-line addendum to plan Phase 4 §2 (and a sentence in ADR-0092): the 2×2 layout is viewport-driven, so the sink is checked in a 390 px window, not in a frame.
- **Decision**: FIXED — addendum under plan Phase 4 §2 and a sentence in ADR-0092.

### F3 — Category filter section headers look the same as group headers

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/CategorySelect.vue (sectionLabels rendering)
- **Detail**: The "Dochody" / "Wydatki" sections render as a `SelectGroup` + `SelectLabel` styled exactly like group labels. A section header followed by a group header ("Wydatki", then "Dom") reads as two headings at the same level. Manual step 3.6 accepted it, and the design system does not define a section level.
- **Fix**: Give section labels a distinct named class in `app.css` (e.g. uppercase/caption token, or a separator above), shown in `/dev/operations`.
  - Strength: makes the two levels readable without new components; `components/ui/*` stays untouched.
  - Tradeoff: a new visual meaning that belongs on the design-system page.
  - Confidence: MED — based on code reading and the plan screenshots, not a usability check.
  - Blind spot: whether the user found the current look confusing in practice.
- **Decision**: DEFERRED to the user — a new visual level for section headers is a design decision (a new meaning on the design-system page) and needs options and a mockup before choosing. Left unchanged.

### F4 — Term Deposit filter uses the Accounts translation key for its archived suffix

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/OperationsFilters.vue:489
- **Detail**: The filter uses `t.accounts.archived_badge`, but `term_deposits.archived_badge` exists (`lang/pl/ui.php:787`). The text is the same today, so nothing breaks yet; the key is just the wrong one.
- **Fix**: Use `t.term_deposits.archived_badge` at :489.
- **Decision**: FIXED

### F5 — Required term deposit in the Capital Movement dialog is now checked only by the server

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/AccountSelect.vue:128, resources/js/components/CapitalMovementFormDialog.vue:373
- **Detail**: Browsers skip validation of `type="hidden"` inputs, so `:required` has no effect there. The plan expected this (Phase 2 §2), and the server rejects a missing deposit: `CapitalMovementValidationRules`, covered by the new test. The cost is a round-trip instead of an instant prompt, and the `required` attribute on the hidden input misleads readers.
- **Fix**: Keep the server check, and disable submit while `termDepositId === ''` (or leave as is, since the plan accepted it).
- **Decision**: ACCEPTED — no code change. The default picks the first Term Deposit, so the field is empty only when there are no deposits. The Account field in the same dialog relies on the same hidden input and server check. A disabled "Zapisz" with no visible reason would break the "a disabled action shows its reason" rule.

### F6 — Contrast is guarded only on `--card`; the tile's hover now replaces its tint

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/CategoryIconPicker.vue (tile on `bg-background`), resources/css/app.css (`.category-tile`, components layer)
- **Detail**: The plan scoped the test to `card` on purpose ("What We're NOT Doing"). The icon picker, though, places tiles on `--background`. Also, the tint used to be an inline style; it is now a class in the components layer. As a result `hover:bg-secondary` (utilities layer) replaces the tint on hover. That behaviour change is not in ADR-0095.
- **Fix**: Record both points in ADR-0095, or add `--background` as a second backdrop in the test.
- **Decision**: FIXED — recorded in ADR-0095: tiles on `background` are checked by eye, and the hover replacing the tint is intended.

### F7 — With no year selected, "newest line" means "newest line with operations"

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Support/OperationsFilterOptions.php:47
- **Detail**: The lines query is limited by `whereHas('financialOperations')`. Example: a 2025 line with operations and a 2026 line that is archived but has no operations. With no year selected, the filter shows that category as active. This agrees with the filter's purpose (it lists only categories with operations), but P1 says "newest line".
- **Fix**: State the "newest line with operations" reading in the method docblock and in ADR-0093.
- **Decision**: FIXED

### F8 — Operations sink matrix says "empty lists"; the filters are actually hidden

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/dev/OperationsSink.vue (matrix "empty" cell); resources/js/components/OperationsFilters.vue:431, :477
- **Detail**: Both filters render under `v-if="… length > 0"`, so with no options they disappear. The matrix note "puste listy obu filtrów" says something else.
- **Fix**: Reword the cell to "filtr ukryty, gdy brak kategorii / lokat".
- **Decision**: FIXED — both notes in `OperationsSink.vue` now say the filters are hidden, not empty.

### F9 — Dialog fixtures miss the Revolut deposit; new category fixture colours are outside the palette

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/lib/operation-dialogs-fixtures.ts, resources/js/lib/operations-fixtures.ts
- **Detail**:
  - Plan Phase 2 §4 lists Facto, Revolut and ING for every fixture, but the dialogs fixture has only Facto and ING. The capital-movement sink therefore never shows the logo case that the dark plate exists for.
  - The new filter category fixtures use colours such as `#e03131`, which are not in `CategoryColors`, so the sink can show tiles that the contrast test never checks.
- **Fix**: Add a Revolut deposit to the dialogs fixture and switch the fixture colours to `CategoryColors` values.
- **Decision**: FIXED — Revolut deposit added to `dialogTermDeposits`; the four new colours switched to #ef4444, #f59e0b, #3b82f6, #8b5cf6. The older `#0d9488`, which this change did not add, is left as is.
