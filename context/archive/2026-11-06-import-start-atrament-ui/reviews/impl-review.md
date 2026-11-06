<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Import operacji, krok 1 w Atramencie

- **Plan**: context/changes/import-start-atrament-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-06
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 3 observations

Automated criteria of all phases pass on HEAD `ac30117` (full `composer ci:check` on PostgreSQL: Pest 910 passed / 2 skipped, Vitest 282, check, check:ui, types:check; `sloppy ci`; full Playwright 4/4). Manual items 1.3, 2.6, 2.7, 3.5, 3.6, 3.7, 4.6, 4.7 are still unchecked (pending the user's confirmation; screenshots in `screenshots/` support 3.5, 3.6, 3.7, 4.6, 4.7).

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Findings

### F1 — Browser and server pick different CSV separators

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence / Safety & Quality (data)
- **Location**: resources/js/lib/import-start.ts:41-49 vs app/Support/CsvImport/CsvImportParser.php:102-108
- **Detail**: The plan says to move separator detection out of the page. Phase 2 also changed it: the client strips quoted text before counting, while the server still counts every `;` and `,` in the raw line. The old page (`dd04a3c` `OperationsImport.vue:115-119`) used the same raw count as the server. For `"Opis, długi";Kwota` or `2026-01-02;"Zakup, sklep";-12,50` the browser shows `;` columns, but the server splits on `,`. The chosen indexes then point at different cells in step 2. `import-start.test.ts` asserts the new client rule on `"Opis, długi";Kwota`. Changing the server parser is in "What We're NOT Doing".
- **Fix A ⭐ Recommended**: Restore the server rule in `detectDelimiter` (raw count, `;` only when it wins) and move the quoted-separator test case to a line where both rules agree (`Data;"Opis, długi";Kwota`). Add a test case that asserts parity with the server rule on the line `"Opis, długi";Kwota` (`,`).
  - Strength: The client is the server's mirror again, as before the change, and the plan's scope (no parser changes) is kept.
  - Tradeoff: A file whose header has more quoted commas than semicolons still gets `,`, an existing server weakness that stays visible as odd labels.
  - Confidence: HIGH — the old page code and the parser are identical in logic.
  - Blind spot: A shared fixture run through both PHP and TS is not added, so the two rules can drift again.
- **Fix B**: Teach the server parser to ignore quoted text (Pest test), keep the client.
  - Strength: Fixes the server weakness for quoted commas.
  - Tradeoff: Changes the server parser, which the plan excluded; it needs its own change, an ADR check (ADR-0002) and PostgreSQL tests.
  - Confidence: MED — touches all `other` imports, not just this view.
  - Blind spot: Effect on mBank/BNP parsers that may share the helper is not checked.
- **Decision**: FIXED (Fix A) — `detectDelimiter` counts raw separators like the server; test moved to `Data;"Opis, długi";Kwota` plus a parity case (`"Opis, długi";Kwota` → one cell, same as `str_getcsv` on the server).

### F2 — A failed file read is swallowed without a message

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality (reliability)
- **Location**: resources/js/pages/OperationsImport.vue:120-133, :154
- **Detail**: `void loadFileText(file)` has no `catch`. When `file.text()` rejects (for example `NotReadableError` for a file changed on disk or in a cloud-synced folder), the rejection is unhandled, "Kolumny" never appears and "Pokaż podgląd" stays disabled with no reason. That breaks the project rule "no ignored promise rejections; a catch logs or reports the error with its cause".
- **Fix**: Add a `.catch` that sets a visible error on the file field (new key `operations.import_file_read_failed`, shown through `FormField` `:error`) and calls `console.error` with the error as `cause`. Also ignore the result if a newer file was chosen.
  - Strength: The user sees why the form is stuck, and the cause reaches the console and the error tracker.
  - Tradeoff: One new copy key and a small local error state next to the server `errors.file`.
  - Confidence: HIGH — follows the rule in CLAUDE.local.md and the existing `FormField` error slot.
  - Blind spot: There is no automated test for a rejecting `File` in the page (Vitest covers only the lib).
- **Decision**: FIXED — `.catch` reports `new Error('Import: reading the chosen file failed', { cause })` with `console.error`, and for the current file sets `fileReadError` (`operations.import_file_read_failed`) shown by the file `FormField`; the error is cleared on the next file choice.

### F3 — Formatting commit corrupted globs in .ai/rules/index.md

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: .ai/rules/index.md:9-21 (commit 055f3f1)
- **Detail**: The `chore` commit made during implementation, to unblock `npm run check`, ran `vp fmt` on the index. The formatter reads `*` as markdown emphasis, so rows now show `components/_Dialog.vue`, `routes/_.php` and `\*\*`. The commit message wrongly says the mapping is unchanged. Loading the rules is unaffected (`paths:` frontmatter), but AGENTS.md tells agents to read globs from this file. The next `boost:index-rules` will make `npm run check` fail again.
- **Fix**: Add `.ai/rules/index.md` to `fmt.ignorePatterns` in `vite.config.ts` and regenerate the file with `php artisan boost:index-rules` (raw, correct globs).
- **Decision**: FIXED — `.ai/rules/index.md` is in `fmt.ignorePatterns` with a comment; the regenerated file equals the raw output of `0d071b4`; `npm run check` passes.

### F4 — Whole file is read and split just for the first line

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality (performance)
- **Location**: resources/js/lib/import-start.ts:62, resources/js/pages/OperationsImport.vue:50-58, :121
- **Detail**: `file.text()` decodes the whole file (the server allows 5 MB), and `importColumnLabels` splits all of it on every recompute (header toggle, format change), only to use the first non-empty line.
- **Fix**: Read `file.slice(0, 64 * 1024).text()` in `loadFileText`.
- **Decision**: FIXED — `loadFileText` reads `file.slice(0, FILE_HEAD_BYTES)` (64 KB, named constant with a comment).

### F5 — An .xlsx file with "Inny" chosen by hand leads nowhere

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality (UX)
- **Location**: resources/js/pages/OperationsImport.vue:153, `canSubmit`
- **Detail**: When the user picked "Inny" and then an `.xlsx`, the file is not read, "Kolumny" stays empty and the button stays disabled without a reason. The server accepts only `csv,txt` for this format.
- **Fix**: Show a field hint or error such as „Dla formatu Inny wybierz plik CSV” when an `.xlsx` is chosen with "Inny".
- **Decision**: FIXED — computed `fileError` shows `operations.import_other_needs_csv` under the file field while the format is "Inny" and the file is `.xlsx` (also after switching the format later); a read failure (F2) takes precedence, the server `errors.file` comes last.

### F6 — Small inaccuracies in docs, guard and sink fixtures

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Unit/ImportStartHardcodedUiTest.php:5, :64-66; resources/js/pages/dev/import-start-fixtures.ts:54; context/foundation/design-system.md:103; .ai/rules/js-import.md:18
- **Detail**: The guard's PHPDoc attributes F6 to this change. It belongs to `accounts-atrament-ui`: the shared-helper debt. The sample path in the guard is still `pages/Accounts.vue`. The fixture's `file` error is never rendered, because no sink scenario has a file field. The sink paragraph in design-system.md claims full mBank and BNP panels and a spinner in the sink. The panels appear only as `ImportRecognizedFields`, and the spinner exists only as a screenshot. js-import.md says the guard covers "the view file", but it covers five files.
- **Fix**: Correct the PHPDoc and sample path, drop the dead `file` key, and align the sink paragraph and the js-import.md sentence with what exists.
- **Decision**: FIXED — guard PHPDoc points F6 to `accounts-atrament-ui`, sample path is `OperationsImport.vue`, the dead `file` key is gone from the fixtures (the matrix says the file error comes only from the server), the sink paragraph lists what the sink really shows, and js-import.md names all five guarded files plus the F1/F2/F5 rules.

## Triage summary (2026-11-06)

- Fixed: F1 (Fix A), F2, F3, F4, F5, F6
- Gates after the fixes: `composer ci:check` on PostgreSQL passes (Pest 910 passed / 2 skipped, Vitest, check, check:ui, types:check), `sloppy ci` passes.
- Playwright: one full-suite run failed in `import-other-format-without-header.spec.ts:54` (step 2 not shown within 5 s, no field error, button idle). The spec then passed 3/3 alone and the full suite passed 2/2. This is a local flake: `fullyParallel` with several workers and the default 5 s expect timeout on the preview POST. CI runs 1 worker with 2 retries. Follow-up candidate: a longer timeout on the step 2 assertion, or `waitForURL` on the preview route before the text checks.
