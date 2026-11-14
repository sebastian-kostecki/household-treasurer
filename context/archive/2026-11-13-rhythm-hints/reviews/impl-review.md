<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: rhythm-hints — Podpowiedzi przy wyborze rytmu kategorii

- **Plan**: context/changes/rhythm-hints/plan.md
- **Scope**: Phase 1 of 1 / Full plan
- **Reviewed phases**: 1
- **Date**: 2026-11-13
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warning, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Verification run (2026-11-13)

- 1.1 `npm run check` — pass (385 files formatted, no lint errors)
- 1.2 `npm run check:ui` — pass
- 1.3 `npm run types:check` — pass
- 1.4 `CategoriesHardcodedUiTest` — pass (3 tests, 17 assertions; Sail)
- 1.7 `composer ci:check` on PostgreSQL (Sail) — pass, 1662 passed, 2 skipped. `BalanceReconciliation` filter: 9 passed. The commit message called BalanceReconciliationTest red; it is green now.
- 1.5 irregular-collected E2E — NOT run: stale `public/hot` (nothing listens on :5173) makes `scripts/e2e-server.sh` refuse to start.
- 1.6 collected-year-start E2E — not re-run; ticked in plan with commit 841d459.

## Findings

### F1 — E2E assertion added by the change has never run green

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/irregular-collected.spec.ts:87-89, context/changes/rhythm-hints/plan.md (Progress 1.5, 1.7)
- **Detail**: Commit 841d459 says the spec was red on the `openCurrentMonthPlan` step before the new assertion, so the assertion on „Co miesiąc stawka trafia do „zebrane” i pokrywa rachunek.” was never exercised. 1.5 stays `[ ]`. 1.7 also stays `[ ]`, but `composer ci:check` is green on PostgreSQL today, so that row is stale. The review could not run E2E (stale `public/hot`).
- **Fix**: Tick 1.7 in Progress with the ci:check evidence; remove the stale `public/hot`, run `npx playwright test tests/e2e/irregular-collected.spec.ts`, and either tick 1.5 or record the unrelated `openCurrentMonthPlan` failure as its own issue.
- **Decision**: FIXED via recommended fix — 1.7 ticked; the new assertion ran green in the E2E (spec then fails on the stale year button in `openCurrentMonthPlan`, not caused by this change, queued in follow-ups/review-fixes.md); 1.5 stays open

### F2 — Kitchen sink description of the dialog hint is out of date

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/pages/dev/CategoriesKitchenSink.vue:51
- **Detail**: The `where` text says the Rhythm field has the hint „Zmiana działa od listopada 2026”. The hint now starts with the description of the selected Rhythm. The plan did not list this file.
- **Fix**: Change the phrase to „z opisem wybranego rytmu i podpowiedzią «Zmiana działa od listopada 2026»”.
- **Decision**: FIXED via recommended fix

### F3 — „Niewydane nie przechodzi” reads awkwardly

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: lang/pl/ui.php:670 (`rhythm_description_even_annual`)
- **Detail**: Neuter-plural „Niewydane” with singular „nie przechodzi”. The text is exactly the one approved in the plan, so the code matches it; the Polish copy is the issue.
- **Fix**: „To, co niewydane, nie przechodzi na kolejny miesiąc.” (also change the plan text).
- **Decision**: FIXED via recommended fix
