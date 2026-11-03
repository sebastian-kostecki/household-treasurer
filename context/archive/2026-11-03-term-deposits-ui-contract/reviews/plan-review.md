<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Term Deposits UI contract pass

- **Plan**: `context/changes/term-deposits-ui-contract/plan.md`
- **Mode**: Deep
- **Date**: 2026-11-03
- **Verdict**: SOUND
- **Findings**: 0 critical, 1 warning, 2 observations (all fixed in plan)

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING (fixed) |
| Plan Completeness | WARNING (fixed) |

## Grounding

13/13 paths ✓ (`ConfirmActionDialog.vue` and `types/accounts.ts` do not exist yet; per the plan, `accounts-ui-contract` delivers them), 5/5 symbols ✓, brief↔plan ✓, Progress 34/34 ✓.

Verified in code:
- Wayfinder merges a user `query` with `_method` on PATCH/DELETE (`resources/js/actions/App/Http/Controllers/TermDepositController.ts:241-250,319-337`), and `store.form({ query })` takes no route args (`:121-124`).
- `$request->boolean()` reads the query string on POST/PATCH, and the FormRequests do not reject `show_archived` (`app/Concerns/TermDepositValidationRules.php`).
- The 5 existing exact `assertRedirect(route('term-deposits.index'))` checks still pass, because a request without the query gets the same redirect.
- `ui-contract.test.ts:67-89` fails when a file on the exception list no longer contains the dead binding.
- `DashboardKitchenSink.vue:9,110` sets the precedent for embedding a whole page.

Note: `t.term_deposits.total` is also used in `DashboardHoldings.vue:243`. The plan does not change it.

## Findings

### F1 — Wyjątek FR-TD-08 w js.md opisany warunkowo i w złym miejscu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 4 — #2 Reguły dla agentów
- **Detail**: Phase 4 told the implementer to add the exception only if `js.md` had a rule about archived amount colour. It has none; grep finds only the token list at `:10`. The exception would therefore never reach the agent rules. A later agent would then read the `amountToneClass` rule (`js.md:34-38`) and "fix" the conditional tone.
- **Fix**: A concrete sentence next to the `amountToneClass` rule in `js.md`.
- **Decision**: FIXED (Fix in plan)

### F2 — Przełącznik w kitchen sinku opuszcza sink

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 — #3 Strona kitchen sinka
- **Detail**: The sink embeds the whole `TermDeposits.vue`. The toggle, and after phase 3 the "Pokaż zarchiwizowane" button, call `router.get`, so a click navigates to the real `/term-deposits`. The plan warned only that form actions do nothing there.
- **Fix**: The sink header says so, and the screenshot script does not click those controls in the sink.
- **Decision**: FIXED (Fix in plan)

### F3 — Ton stopki przy ujemnej sumie zarchiwizowanych nieustalony

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 — #2 Stopka kafelka sumy
- **Detail**: Take one archived deposit at −300 zł. Its row is red (FR-TD-08 exception), but the footer showed the same amount in grey.
- **Fix**: The footer follows the archived-row rule: `amountToneClass` only when the amount is `< 0`.
- **Decision**: FIXED (Fix in plan)
