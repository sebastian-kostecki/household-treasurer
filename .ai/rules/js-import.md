---
paths:
    - 'resources/js/pages/OperationsImport*.vue'
    - 'resources/js/components/ImportPreview*.vue'
    - 'resources/js/lib/import-*.ts'
---

# Import preview

## Named classes and the `pair` tint

The import preview uses `.import-preview-grid`, `.import-preview-header-row` (hidden below `xl`, caption through `table-header`) and `.import-preview-row` with the modifiers `-duplicate` (warning tint) and `-pair` (own transfer pair candidate); do not rebuild them from utilities. The `pair` token is only the tint of a pair candidate row, never text, amounts or the operation type (that is `transfer`).

## A row alert needs a kind in importRowHasNotes, or it is silently lost

A new alert inside a row needs a new kind in `importRowHasNotes` (resources/js/lib/import-preview-rows.ts); otherwise the notes block does not render and the alert is silently lost.
`CategorySelect` and `AccountSelect` used without a `<Label for>` get an `ariaLabel`; the reka `Select` trigger has no other accessible name.
The kitchen sink at `/dev/import-preview` (`local` only) renders the 7-state matrix from `resources/js/lib/import-preview-fixtures.ts`; add new states there.

## A category suggestion enters row state only through suggestedCategoryId

A category suggestion (`row.category_suggestion`) enters row state only through `suggestedCategoryId` (resources/js/lib/import-preview-rows.ts); it never changes the type or `selected`. The marker is muted text under `CategorySelect` ("✦ podpowiedź"), linked by `aria-describedby`, shown only while the value equals the suggestion; it is not a `Badge` or an alert.
