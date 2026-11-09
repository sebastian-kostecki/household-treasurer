<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Dashboard według strony design systemu (ADR-0045)

- **Plan**: context/changes/dashboard-design-system-page/plan.md
- **Scope**: Phase 3 of 5
- **Reviewed phases**: 3
- **Date**: 2026-11-06
- **Verdict**: APPROVED
- **Findings**: 0 critical 0 warnings 3 observations

Reviewed as of commit d555f82 (`git show d555f82:<path>`); the working tree holds Phase 4 work in progress and was not judged. The automated commands below ran on the working tree and were green, so no Phase 4 file interfered.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Success criteria (automated)

| Check | Result |
|-------|--------|
| `npm run test` | PASS, 30 files, 289 tests |
| `npm run types:check` | PASS |
| `npm run check:ui` | PASS |
| `grep -rn "yearElapsedCaption\|notice-title\|notice-text" resources` | PASS, no hits |
| `tests/Unit/DashboardHardcodedUiTest.php` (Docker, PostgreSQL) | PASS, 3 tests, 14 assertions |

Manual items 3.5-3.7 are `[ ]` in Progress, so they are pending (not ticked without evidence); nothing to flag.

## Checklist from the brief

- Colour from `severity` only: OK. `DashboardNotices.vue` picks `text-destructive` for `exceeded` and `text-warning` otherwise from `notice.severity`; the icon tile uses `dashboard-notice-icon-${notice.severity}`. `noticePresentation` returns no colour and no kind-based tone.
- No colour, px or palette literals in the touched templates/CSS: OK. The new `.dashboard-notice*` rules use `@apply` with tokens (`text-foreground`, `text-sm`, `font-medium`, `shrink-0`, `whitespace-nowrap`). `check:ui` and the Dashboard guard pass.
- `.dashboard-notice*` in `@layer components`: OK (`app.css:1176-1212`, inside the layer). `.dashboard-notice-title` and `-text` are removed.
- Value has `white-space: nowrap`: OK (`.dashboard-notice-value`, `whitespace-nowrap`, plus `shrink-0`).
- No slot when value is null: OK. `v-if="noticeValue(notice) !== null"` on the whole span; `month_plan_not_approved` returns `value: null` from `noticePresentation`.
- Link, Spinner, `aria-busy`, pills, six-card limit, "Pokaż wszystkie": OK. The diff touches none of `:href`, `:data`, `:as`, `:disabled`, `aria-busy`, the `@start/@error/@finish` handlers, the Spinner/icon branches, `VISIBLE_NOTICE_LIMIT`, the Badge pills or the expander button.
- Removed props gone on both sides: OK. `yearElapsedAsOf` and `yearElapsedPercent` are removed from `defineProps` in `DashboardStatPanel.vue` and from the call site in `Dashboard.vue`; `formatDate` import dropped; the `year-elapsed` metric and `yearElapsedCaption` are gone. These were presentational props, not Inertia props, so there is no PHP side and no `assertInertia` obligation.
- `metaLine` still shows `year_elapsed_as_of`: OK (`Dashboard.vue` `metaLine` computed, `formatLongDate(props.year_elapsed_as_of)`, unchanged by the commit).
- Fixture sign claim: VERIFIED. In `app/Support/DashboardNotices.php`, `month_item_over_plan` sends `plan_minor_units - actual_minor_units`, and `MonthItemDeviation::isOverPlan` only accepts expenses with `actual > plan`, so the value is always negative. `one_off_over_limit` sends `CategoryExecutionMetrics::remainingMinorUnits(...)` and skips `>= 0`, so it is always negative. `month_to_spend_negative` is `< 0` and negative balances are negative by definition. The old code hid this with `Math.abs`; the fixtures at `dashboard-fixtures.ts:476` and `:486` previously held positive amounts that the real payload never produces. The change makes the sink match the payload, and `noticePresentation` keeps the sign (ADR-0050: "keeps its sign").
- `1.5fr` decision: sensible, see F2.

## Findings

### F1 — Fixture edit is outside the plan text

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/pages/dev/dashboard-fixtures.ts:476
- **Detail**: Phase 3 "Changes Required" does not list `dashboard-fixtures.ts`, and Phase 5 says "Fiksury danych bez zmian". The commit flips `month_item_over_plan` (-15000) and `one_off_over_limit` (-80000) amounts. The edit is correct and necessary (the value is now rendered with its sign, so a positive fixture would show a wrong "+150,00 zł" in `all-notice-kinds`), and it is stated in the commit message. The plan simply does not know about it.
- **Fix**: Add a one-line addendum to Phase 3 (or the Phase 5 fixtures sentence) noting the sign correction, so later reviews do not read it as drift.
- **Decision**: ACCEPTED — documented in the d555f82 commit body and in this report; plan phase blocks stay read-only during implementation

### F2 — `1.5fr` first column kept without visual evidence

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: resources/css/app.css:1232-1237
- **Detail**: The plan keeps the `.dashboard-stat-panel .stat-panel { grid-template-columns: minmax(0, 1.5fr) }` rule only if the sink shows the "Do wydania · miesiąc" label plus the "Wstępny" badge still needs it. The commit keeps the rule and its comment unchanged, but manual check 3.6 (three measures, layout not lopsided) is still open, so there is no recorded evidence either way. The default is reasonable: the first metric uses `kpi-lg`, carries the badge, the day bar and the "Plan miesiąca →" link, and with `grid-auto-columns: minmax(0, 1fr)` only column 1 is widened (about 1.5 : 1 : 1 with three measures, 1.5 : 1 with two). The comment is still accurate. Risk is only cosmetic.
- **Fix**: Decide at manual check 3.6: keep (comment stays true) or delete the rule and its comment if three equal columns look right.
  - Strength: Keeps the label and badge on one line, which is the original reason for the rule.
  - Tradeoff: With two measures (no month plan) the 1.5 : 1 split may look tilted.
  - Confidence: MED — reasoning from the CSS, not from a screenshot.
  - Blind spot: No screenshot of `default` / `all-notice-kinds` at 1280 px taken yet.
- **Decision**: ACCEPTED — decide at manual check 3.6 (user)

### F3 — Severity-to-colour mapping lives in a template ternary

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/DashboardNotices.vue:289-293
- **Detail**: The icon tile gets its colour from `.dashboard-notice-icon-exceeded` / `-warning` classes in `app.css`, while the value gets it from an inline ternary `severity === 'exceeded' ? 'text-destructive' : 'text-warning'`. Both are severity-only and token-based, and the plan prescribes this wording, so it is not a defect. It does mean a third severity would need two edits in two styles.
- **Fix**: Optionally add `.dashboard-notice-value-exceeded` / `-warning` in `@layer components` and use `` :class="`dashboard-notice-value-${notice.severity}`" ``, matching the icon classes.
- **Decision**: ACCEPTED — the plan prescribes the severity ternary; no new classes
