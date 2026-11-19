<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: E2E w CI — plan implementacji

- **Plan**: context/changes/e2e-in-ci/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-19
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 5 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Success criteria (re-run 2026-11-19, HEAD 4e7a7fb)

- 1.1 Red before the fix: the base-commit seeder (863a422) was bind-mounted in the Sail image, and `SeedDemoHouseholdCommandTest` failed twice with `'2027-01-15' > '2027-01-05'`. PASS.
- 1.2/1.3 `SeedDemoHouseholdCommandTest`, `DatabaseSeederTest` and `SeedPreviousYearCommandTest` on PostgreSQL (`testing_e2e_in_ci`): 19 passed, 326 assertions. PASS.
- 2.4 `grep "new Date().getFullYear()"` over the five gate specs returns nothing. PASS.
- 2.5/3.4/4.5 `npx vp check` passes (413 files), and `npm run types:check` passes. PASS.
- 4.1 `--list --project=gate` lists `setup` and exactly 5 gates. PASS.
- 4.2 `--list` has 75 tests in 17 files, and no gate spec appears in `chromium`. PASS.
- 4.3/4.4/4.6 were not re-run (the 36-minute stability run and the Sail smoke). The evidence is in `change.md` Notes and in the GitHub runs below.
- 5.1 `actionlint` reports nothing. PASS.
- 5.2 All 9 `uses:` are pinned to a 40-character SHA. PASS.
- 5.3 `composer ci:check` on `testing_e2e_in_ci` (Sail image): exit 0, 1929 passed, 4 skipped. `vendor/bin/sloppy ci`: exit 0, nothing at high or above (3 medium findings in files outside the change: `UnloadBudgetSheet`, `SheetLoad/*`). PASS.
- 5.4 PR #275 at `aa246c4`: e2e success. PR #275 at `4e7a7fb`: e2e success. PASS. The evidence is a `pull_request` run, not a push to `development` (see F6).
- 5.5 PR #276 is a closed draft, branch `tmp/e2e-in-ci-break-check`: e2e failure, ci success. PASS.
- 5.6 It holds for one of the two runs (see F1).

## Findings

### F1 — Kryterium 5.6 odhaczone na jednym przebiegu; przebieg na HEAD je łamie

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: .github/workflows/tests.yml:188-189 (`npx playwright install --with-deps chromium`)
- **Detail**: Item 5.6 cites "e2e 166 s, ci 215 s", which is run 44517260906 at `aa246c4`. The later run on the change's HEAD (44518851819, `4e7a7fb`) took 397 s for e2e and 249 s for ci. The result arrived 148 s after ci, above the 120 s threshold and above the "about 5 min" target. The whole difference is the "Install Playwright browser" step: 288 s versus 46 s. Its log shows the `apt-get` part of `--with-deps` downloading 32.1 MB at 147 kB/s (3 min 39 s), against 13 s in the fast run. Downloading Chromium took about 8 s both times. A cache of `~/.cache/ms-playwright`, which the plan deferred, would not have helped here.
- **Fix A ⭐ Recommended**: Record it honestly in 5.6 and `change.md` (two samples, 166 s and 397 s, apt mirror variance). Leave the job as it is and come back to it if slow runs recur.
  - Strength: The plan explicitly deferred optimising the job ("cache wróci, jeśli czas joba zacznie przeszkadzać"), and one slow apt mirror is noise, not a trend.
  - Tradeoff: Some e2e runs can wait about 2.5 min longer than ci.
  - Confidence: MED — two samples only.
  - Blind spot: How often the runner's apt mirror is slow.
- **Fix B**: Remove the apt step: `npx playwright install chromium --only-shell` without `--with-deps`, relying on the system libraries of the `ubuntu-24.04` image.
  - Strength: It removes the step that was slow (the apt mirror), and the job no longer depends on the mirror.
  - Tradeoff: If the image lacks a library, the job fails at Chromium launch. That needs a separate run on GitHub (a step like 5.4).
  - Confidence: LOW — I have not checked that the image has every Chromium dependency.
  - Blind spot: Future changes to the runner image.
- **Decision**: FIXED (Fix A): both measurements are recorded in plan.md (Progress 5.6 and the Addendum) and in change.md; the job is unchanged. UPDATE after the fixes: on `335b205` the first attempt of e2e went red with no code involved (`503` from `security.ubuntu.com` in `--with-deps`, exit 100); after a re-run of the job it was green (114 s). Fix B (no apt) or a retry of the install step is now a strong candidate for a follow-up

### F2 — Sprzątanie w `finally` może przykryć pierwotny błąd speca

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/import-approve.spec.ts:93-115 (also irregular-collected.spec.ts:126-152, month-plan-groups.spec.ts:171-217)
- **Detail**: In JS, an error thrown in `finally` replaces the error from `try`. If a step fails midway (for example a 500 after "Zatwierdź import") and the cleanup also fails (`goto`, a click, or a `toBeHidden` timeout on the same broken server), the `github` annotation and the `list` output point at the cleanup, not at the cause. The trace still has the earlier steps. This is the "never hide evidence" rule from CLAUDE.local.md, now inside the CI gate. The pattern predates this change in the two plan specs; `import-approve` is new and copies it.
- **Fix A ⭐ Recommended**: Run the cleanup in `test.step('cleanup', …)` inside its own try/catch. When the body failed, log the cleanup error (`console.error` or `test.info().annotations`) and do not throw. When the body passed, rethrow the cleanup error.
  - Strength: The cause of the failure stays the first error in the report, and a failed cleanup is still visible and reported.
  - Tradeoff: About 10 lines per spec, three specs, or a small helper in `tests/e2e/support/`.
  - Confidence: HIGH — standard JS semantics; a local helper covers all three specs.
  - Blind spot: None significant.
- **Fix B**: Move the cleanup to `test.afterEach`, so Playwright reports both errors on its own.
  - Strength: Native Playwright reporting with no try/catch.
  - Tradeoff: The cleanup needs state from the test (stamp, descriptions) through closure variables at file level, which is harder to read than the current inline flow.
  - Confidence: MED — it changes the structure of three specs.
  - Blind spot: Interaction with `test.setTimeout(120_000)` in month-plan-groups (`afterEach` shares the test's timeout).
- **Decision**: FIXED (Fix A): `withCleanup` in `tests/e2e/support/cleanup.ts`, used by import-approve, irregular-collected and month-plan-groups. Proof: when both the flow and the cleanup fail, the report shows FLOW-ERROR, where the old try/finally showed CLEANUP-ERROR

### F3 — Sprzątanie `import-approve` może się cicho pominąć

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/import-approve.spec.ts:96-103
- **Detail**: `text.count()` straight after `page.goto` neither waits nor retries. The Inertia pages load lazily, so before the list renders `count()` returns 0, the delete is skipped and the test passes. That contradicts the comment "each deletion is asserted". CI is unaffected (its database is thrown away), but rows can be left in the local E2E database, and a later run of the same spec does not see them because of the stamp.
- **Fix**: Before the loop, wait for the page to render with a web-first assertion (for example `await expect(page.getByRole('heading', { name: 'Operacje' })).toBeVisible()`, or the list or empty-state element), or keep an `approved` flag the way `month-plan-groups` keeps `expenseBooked`.
- **Decision**: FIXED: the cleanup waits for the h1 „Operacje” before calling count()

### F4 — Dwa timeouty spoza planu; globalny `expect.timeout` leczy objaw

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Scope Discipline
- **Location**: playwright.config.ts:30-33 (`expect: { timeout: 10_000 }`), playwright.config.ts:55-59 (`timeout: 90_000` in `gate`)
- **Detail**: Both were added after the stability runs. They are documented in `change.md`, `test-stack.md` and `test-plan.md` §6.6, but not in `plan.md`, which the next reviews use as the source of truth. `expect.timeout` applies to every project, including the local `chromium`, and works around the cause: the app ignores `prefers-reduced-motion`, so a closed dialog stays in the DOM until its exit animation ends. No issue was filed for that. The comment on the 90 s says it is for "the month plan and irregular-category flows", but `month-plan-groups.spec.ts:93` sets its own `test.setTimeout(120_000)`, so the 90 s matters only for `irregular-collected` and the short specs. The assertions are unchanged, so these settings hide slowness, not failures.
- **Fix A ⭐ Recommended**: Add a plan addendum (Phase 4: both timeouts and why), correct the comment on the 90 s, and file an issue for `prefers-reduced-motion` (dialog animation) that points at this setting as the thing to remove after the fix.
  - Strength: It costs a few lines of docs, plan.md becomes the source of truth again, and the cause has an owner.
  - Tradeoff: The slow animation stays and the longer wait stays global.
  - Confidence: HIGH — the facts are confirmed in the code and in change.md.
  - Blind spot: Whether the user wants a reduced-motion issue at all (it touches the design system).
- **Fix B**: Fix the cause: the dialog animations honour `prefers-reduced-motion`, the config gets `use.reducedMotion: 'reduce'`, and `expect.timeout` goes away.
  - Strength: It removes the symptom at the source, and the app gains accessibility.
  - Tradeoff: A change to `components/ui/dialog` and Atrament tokens (design system rules, `/dev` kitchen sink) plus another 20-round stability run; that is more than a review fix.
  - Confidence: MED — the cause comes from the Phase 4 note, and I have not confirmed it in the code.
  - Blind spot: Other animations (sheets, toasts) may have the same effect.
- **Decision**: FIXED (Fix A): Addendum in plan.md, corrected comment on the 90 s, and issue #277 (prefers-reduced-motion) linked from the config and from test-stack.md

### F5 — Artefakt porażki bez logów serwera i bez przypadku anulowania

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: .github/workflows/tests.yml:194-202
- **Detail**: The upload takes only `playwright-report/` and `test-results/`. A server exception goes to `storage/logs/laravel.log` (`LOG_STACK=single` from `.env.example`), which is lost with the runner, so a 500 can only be seen through the debug page in the trace. `if: failure()` also uploads nothing when the job is cancelled by `timeout-minutes: 20`. The job's database and APP_KEY are throwaway, so the logs leak nothing.
- **Fix**: Add `storage/logs/` to `path` and change the condition to `if: ${{ failure() || cancelled() }}`.
- **Decision**: FIXED: `storage/logs/` is in the artifact and the condition is `if: ${{ failure() || cancelled() }}`; actionlint is clean

### F6 — Krok 5.4 dowiedziony przebiegiem PR, a nie pushem `development`; push gałęzi poza planem

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/changes/e2e-in-ci/plan.md:479 (Progress 5.4), plan.md:33 and :62
- **Detail**: The plan rejected pushing the branch and opening a PR (decision 4, "What We're NOT Doing") and made 5.4 a check after a push to `development`. In fact the branch was pushed and PR #275 (open, base `development`) gave the evidence. The latest push to `development` (`05b3613`, run 44519512262) has only the `ci` job, so the gate is not in `development` yet. The deviation is harmless and is noted in `change.md` ("Przebieg bazowy PR #275"), but not in the plan.
- **Fix**: Add a note to 5.4 that the evidence is PR #275 and the first push of `development` after the merge, and look at that first push of `development` with the `e2e` job.
- **Decision**: FIXED: a note on 5.4 and in the plan Addendum; the first push of `development` with the e2e job still has to be checked after the merge

### F7 — Rok planu z menu, data wydatku z zegara dialogu

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/month-plan-groups.spec.ts:58-64, tests/e2e/irregular-collected.spec.ts:57-63; resources/js/components/ExpenseFormDialog.vue:439
- **Detail**: `openCurrentMonthPlan` opens `newestExecutionBudgetYear`, while `addExpense` keeps the dialog's default date `new Date().toISOString()`. In CI both are the same year (the seed creates only the current one). In a local E2E database with the next year's Budget in execution (for example in December), the plan opens the next year, the expense lands in the current one, and the spec fails for the wrong reason. The plan excluded S-39 (the dialog's date) on purpose, so this is a known limitation that §6.3 does not state.
- **Fix**: Add one sentence to §6.3 or §6.6 of `test-plan.md` (the dialog date still comes from the browser clock until S-39), or fill the date field explicitly inside the chosen year in both specs.
- **Decision**: FIXED: the known limitation is recorded in test-plan.md §6.6

### F8 — Drobne nieścisłości w komentarzach i dokumentach

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: context/foundation/test-plan.md §5 and §6.6; tests/e2e/import-other-format-without-header.spec.ts:37-38; playwright.config.ts:55-58
- **Detail**:
  - §6.6 presents the base36 stamp as a general rule, while `seed`, `month-plan-groups` and `irregular-collected` keep a decimal `Date.now()`. That is correct for them, because Luhn removal applies only to Import.
  - §5 says "CI na pushu i PR do development", but the workflow runs on every PR and on pushes to `main` and `development` (`test-stack.md` describes it correctly).
  - The comment in import-other-format still mentions "a spec running in parallel", although `workers: 1`.
  - The comment on the 90 s is covered in F4.
- **Fix**: Narrow the §6.6 bullet to the Import specs, correct the §5 triggers, and update the comment in import-other-format.
- **Decision**: FIXED: §6.6 (base36 only for Import), §5 (triggers), and the comment in import-other-format

### F9 — Niezacommitowana zmiana `roadmap.md`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: context/foundation/roadmap.md:62, :195
- **Detail**: The working tree has S-44 `ready` → `in-progress` (table and section), outside the change's commits. The change is already `implemented`/`impl_reviewed`, so `in-progress` will be stale at the archive, where the status should be `done`.
- **Fix**: Commit the status together with the change's closing commit (as `done` at the archive) or roll it back, so it does not leak into an unrelated commit.
- **Decision**: FIXED: recorded as a separate docs(roadmap) entry (S-44 stays in-progress until the archive)

## Triage

The user chose „napraw samodzielnie”: every finding was fixed with its recommended option.

```
Fixed:     F1 (A), F2 (A), F3, F4 (A), F5, F6, F7, F8, F9   (9)
```

Verification after the fixes: the three changed gates are green locally (`e2e_e2e_in_ci`). `vp check`, `types:check`, `actionlint` and `--list` (6 in `gate`, 75 in total) pass.
