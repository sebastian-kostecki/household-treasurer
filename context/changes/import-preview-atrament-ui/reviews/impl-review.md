<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Podgląd importu, krok 2 w Atramencie

- **Plan**: context/changes/import-preview-atrament-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-06
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical 3 warnings 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Notes: diff 1fc9820..deb008d matches the plan's file list (no EXTRA, no MISSING). No empty catch or swallowed error; no float money (importRowAmount works on int minor units, reuses format.ts helpers, ADR-0038 sign/colour mapping as planned); no hex or palette colours in changed files (fixtures use DEFAULT_CATEGORY_COLOR); components/ui/* untouched; the prop change (invalid_reason code, pair candidate without `reason`) is on both sides with assertInertia tests; the unknown-code fallback keeps old-session evidence. Automated gates were run green by the orchestrator per phase (ci:check PG 926 passed, npm test 299, check:ui, check, types:check, sloppy, Playwright 4). Manual Progress rows 1.3, 2.6, 3.4-3.6, 4.6, 4.7 are still `[ ]` (pending, not falsely ticked).

## Findings

### F1 — Column caption "Importuj" overlaps "Data" at xl (1280px)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/css/app.css:1022-1031 (xl grid, 2rem select column) and .import-preview-header-row (~:1072)
- **Detail**: Confirmed in screenshots preview-1280-light.png / preview-duplicate-error.png: the header cell "Importuj" (import_select) is wider than the 2rem select column and runs into "Data". The grid itself is unchanged by this change (pre-existing), but plan step 3.6 told the implementer to inspect column widths on the screenshot and fix them in the same class, and 4.6 asks the shots to match the PageImportPreview card. The defect is committed as evidence.
- **Fix**: Widen the xl select column (e.g. 4.5rem) or make that header cell `sr-only`/shorter, then re-take the 1280 shots.
- **Decision**: FIXED — `.import-preview-*` grid: the xl select column is `4.5rem` (was `2rem`); at 1280 px "Importuj" ends at x=340 and "Data" starts at x=373; amount cells stay on one line. 1280/390 shots re-taken in both themes.

### F2 — Tall empty gap inside the summary ListSurface and the rows panel

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/OperationsImportPreview.vue:243 (summary ListSurface) and the rows ListSurface; resources/js/components/ListSurface.vue:41-52
- **Detail**: Confirmed (about 60px between the h2/meta header and the pills; similar gap above the table header row). Cause: non-flush ListSurface renders Card (default flex gap-6) + CardHeader pb-4, and the body adds its own `p-4`, so the spacing stacks. ADR-0052 specifies ListSurface with #header, so the structure is right; the spacing is not.
- **Fix**: Pass a spacing class on these ListSurface uses (e.g. reduced gap, drop the redundant `p-4`) or use `flush` with the bordered header bar; re-take shots. Do not edit components/ui/*.
- **Decision**: FIXED — rows panel is `<ListSurface flush>` (as `MonthPlanTable`, `OperationsListPanel`) with `px-4` on `.import-preview-header-row` and `.import-preview-row`; the summary drops the redundant body `p-4` and uses `gap-4`. The gap above the table is gone; the summary keeps ~38 px (CardHeader `pb-4` + `gap-4`, same shell as `MonthSummaryCard`). Shots re-taken.

### F3 — Server validation message for duplicate acknowledgement reaches the UI in English

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: app/Http/Controllers/CsvImportController.php:336 (shown by ImportPreviewDuplicateAcknowledgement.vue, see preview-duplicate-error.png)
- **Detail**: Confirmed: "Acknowledge the import duplicate warning before committing selected duplicates." renders under the new Polish checkbox. The plan consciously defers server English text (D1, incl. `rows.*` errors), so this is within "NOT doing", but this is the very message the new component was built to display and it undermines the Polish-copy goal of the screen; the plan did not list it explicitly.
- **Fix A ⭐ Recommended**: Record as a follow-up under D1 (follow-ups/review-fixes.md).
  - Strength: keeps the agreed scope; one decision for all `__()` calls.
  - Tradeoff: user sees English on this error for now.
  - Confidence: HIGH — the plan's D1 explicitly excludes server messages.
  - Blind spot: None significant.
- **Fix B**: Send a code and translate client-side, or add a single `lang/pl.json` key.
  - Strength: fixes the visible defect now.
  - Tradeoff: re-opens the D1 mechanism decision.
  - Confidence: MED — needs a decision on the mechanism.
  - Blind spot: Other `rows.*` messages stay English.
- **Decision**: FIXED (user decision: Polish on the UI side) — `duplicateAcknowledgementErrorText` in `resources/js/lib/import-preview-errors.ts` (Vitest red first) returns `operations.import_duplicate_acknowledgement_required` when the server sends the error and falls back to the server text; the server and the Inertia contract are unchanged. ADR-0052 "Reasons" and `.ai/rules/js-import.md` mention it. `preview-duplicate-error.png` re-taken.

### F4 — Sink offsets fixture row indexes (+100..+400)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: resources/js/pages/dev/ImportPreviewSink.vue:134-200
- **Detail**: Confirmed and documented in a comment: ImportPreviewRow builds element ids from row.index, so sections reuse fixture rows with shifted indexes to keep ids unique. Dev-only; plan 4.7 requires no duplicate ids. Cost is the `row.index - section.offset` arithmetic in the template (:338-357).
- **Fix**: Acceptable as is; optionally give ImportPreviewRow an id-prefix prop later.
- **Decision**: ACCEPTED — workaround stays, documented in the sink; an id prefix on `ImportPreviewRow` is a possible follow-up, listed for the user.

### F5 — Manual verification rows still open

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: plan.md Progress rows 1.3, 2.6, 3.4, 3.5, 3.6, 4.6, 4.7
- **Detail**: Correctly unticked. 4.6 should stay open until F1/F2 are addressed and shots refreshed.
- **Fix**: Tick after human confirmation following F1/F2.
- **Decision**: OPEN — manual rows stay unticked until the user confirms them.

## Observation dismissed (not a finding)

(e) "Żabka -32,90" missing from the real-app preview: not a defect of this change. `isOperationShapedRow` (CsvImportController.php:472-479, unchanged) silently drops CSV rows with an unparseable date or amount before the preview, so it is a data/parsing quirk of the screenshot CSV. Possible separate issue: such rows vanish without any message.

## Follow-ups (user decisions, 2026-11-06)

- **Minus sign.** `formatMoney` prints an ASCII hyphen ("-64,00 zł") while `formatFinancialOperationAmount` prints U+2212. Unify it in a separate change for the whole app (Accounts, Dashboard, Operations, import preview), not locally in `importRowAmount`.
- **Server English in Import (D1).** Extend this change's pattern step by step: an error becomes a code (or a known field) and the UI composes the Polish text from `lang/pl/ui.php`, with the server text as fallback. Starts with the `rows.*` errors of `CsvImportController` and the toast after commit.
- **Summary gap.** The ~38 px between the summary header and the pills stays, consistent with `MonthSummaryCard`.
- **Sink row ids.** The index offsets in `/dev/import-preview` stay (F4).
- **Copy.** The duplicate-acknowledgement error reads „Potwierdź, że chcesz zaimportować zaznaczone duplikaty.”; ADR-0052 "Reasons" cites ADR-0050 instead of ADR-0043.
