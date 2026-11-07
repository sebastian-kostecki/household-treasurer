---
paths:
    - 'resources/js/pages/OperationsImport*.vue'
    - 'resources/js/components/ImportPreview*.vue'
    - 'resources/js/lib/import-*.ts'
---

# Import

## Step 1 (`OperationsImport.vue`) follows PageImportStart

Step 1 is a form panel built to ADR-0048: `.form-panel` (`--form-panel-width`) with header, body and footer, `FormField` for every field, `.form-preview` with `.form-preview-list`, `.form-check-row`, `fieldset.form-fieldset` for "Kolumny" and a `SegmentedControl` (`block`, `primary`, with `name`) for the amount layout. Do not rebuild them from utilities and do not wrap the form in `Card`.

- `has_header` travels only through an explicit hidden input, so `0` reaches the server; the `Checkbox` has no `name` and is bound with `v-model`.
- The logic of the columns and of the format (file extension sets the format only until the user chooses it by hand; a change to "Inny" recalculates the columns from the loaded file) lives in `resources/js/lib/import-start.ts` with a test next to it, not in the view.
- No active Account: `IndexEmptyState` with a link to Konta instead of the form.
- The kitchen sink at `/dev/ui/import` (`local` only) renders the 7-state matrix; add new step 1 states there.
- `tests/Unit/ImportStartHardcodedUiTest.php` guards the step 1 files (the page, `ImportRecognizedFields`, `ImportColumnMapping`, the sink and its fixtures); they are also on the `check:ui` list.
- The file field shows why the form cannot go on: a failed read of the file (reported with its cause) or an `.xlsx` with "Inny", which maps columns only from CSV text. Column labels split the first line with the same separator rule as `CsvImportParser`, so the chosen indexes point at the cells the server reads.

## Step 2 (preview)

The sections below apply to `OperationsImportPreview.vue` and `ImportPreview*.vue`.

Step 2 follows PageImportPreview (ADR-0052):

- Title row as in step 1: `<Head>` and h1 `import_title`, `meta` "Krok 2: …", "Wróć do operacji" as `outline` with `ArrowLeft` to Operations; the empty state has "Wybierz inny plik" to step 1.
- The summary is `ListSurface` with `#header` (`h2.section-title` + `p.meta`, the duplicates caveat in `meta`), not `.form-panel` and not `Card`/`CardTitle`.
- Pills: valid `neutral`, invalid `destructive-soft`, duplicate and pair `outline`; never the full `destructive`.
- The amount goes through `importRowAmount` (`resources/js/lib/import-row-amount.ts`): sign and colour by the chosen type, "zł" in the cell, style `amount`; no `amountToneClass` by the sign in the file.
- Reasons are composed in the UI by `resources/js/lib/import-row-reasons.ts` from the code and data sent by the server. A new rejection code is a case of the enum `ImportRowInvalidReason` plus a key `import_invalid_reason_<code>` in `lang/pl/ui.php`; the server never sends a sentence.
- The duplicate-acknowledgement error (`duplicate_warning_acknowledged`) is also composed in the UI by `duplicateAcknowledgementErrorText` (`resources/js/lib/import-preview-errors.ts`) from `import_duplicate_acknowledgement_required` in `lang/pl/ui.php`; the server's English message stays as the fallback.
- The row checkbox is 24 px through `.import-preview-select-check`; the duplicates confirmation is `.form-check-row` with its error linked by `aria-describedby`.
- The kitchen sink at `/dev/import-preview` mounts the real page.
- `tests/Unit/ImportPreviewHardcodedUiTest.php` guards `OperationsImportPreview.vue`, `ImportPreviewRow.vue`, `ImportPreviewSubmitBar.vue`, `ImportPreviewDuplicateAcknowledgement.vue`, `pages/dev/ImportPreviewSink.vue` and `lib/import-preview-fixtures.ts`; they are also on the `check:ui` list.

## Named classes and the `pair` tint

The import preview uses `.import-preview-grid`, `.import-preview-header-row` (hidden below `xl`, caption through `table-header`) and `.import-preview-row` with the modifiers `-duplicate` (warning tint) and `-pair` (own transfer pair candidate); do not rebuild them from utilities. The `pair` token is only the tint of a pair candidate row, never text, amounts or the operation type (that is `transfer`).

## A row alert needs a kind in importRowHasNotes, or it is silently lost

A new alert inside a row needs a new kind in `importRowHasNotes` (resources/js/lib/import-preview-rows.ts); otherwise the notes block does not render and the alert is silently lost.
`CategorySelect` and `AccountSelect` used without a `<Label for>` get an `ariaLabel`; the reka `Select` trigger has no other accessible name.
The kitchen sink at `/dev/import-preview` (`local` only) renders the 7-state matrix from `resources/js/lib/import-preview-fixtures.ts`; add new states there.

## A category suggestion enters row state only through suggestedCategoryId

A category suggestion (`row.category_suggestion`) enters row state only through `suggestedCategoryId` (resources/js/lib/import-preview-rows.ts); it never changes the type or `selected`. The marker is muted text under `CategorySelect` ("✦ podpowiedź"), linked by `aria-describedby`, shown only while the value equals the suggestion; it is not a `Badge` or an alert.
