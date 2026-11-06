---
paths:
    - 'resources/js/components/*Dialog.vue'
    - 'resources/js/components/FormField.vue'
    - 'resources/js/components/FormPreview.vue'
    - 'resources/js/components/ui/dialog/**'
    - 'resources/js/lib/operation-dialog-preview*.ts'
    - 'resources/js/composables/useOperationAmountInput*.ts'
---

# Form dialogs

## Form dialogs are built on FormField and the ADR-0040 contract

A form field in a dialog is `FormField` (34 px control, label, hint, error with `id` and `aria-describedby`, `money` variant with "zł"), not a hand-written `div.grid > Label + input + InputError`. A choice in a form is `SegmentedControl` `block` with a `name` (exception: when the payload is a conversion flag, as in the expense and income dialogs, the choice has no `name`, ADR-0041). "Anuluj" is `outline`; saving shows a spinner and blocks closing. Focus is a 2 px `outline` with `outline-solid` (never `ring-3`/`ring-ring/50`). Dialog shell tokens (460 px, `radius-xl`, `shadow-dialog`, `scrim`) come from `ui/dialog`; do not override them per dialog. Add new dialog states to the kitchen sink at `/dev/ui/dialogs` (`local` only). `ui-contract.test.ts` fails when `pages/Budget.vue` or `BudgetCategoryRowActions.vue` import `DialogContent`; use `ConfirmActionDialog`.

## A reading before saving is a FormPreview with warnings under the reading

A reading before saving (To Spend, category limit, balance) is a `FormPreview` with rows built by `lib/operation-dialog-preview.ts`: "before" is the stored state, so an edit without changes shows equal values, and warnings stand in the block under the reading they concern (ADR-0041), not in an `Alert`. A row of two fields is `.form-field-row`, read-only text is `.form-readonly-text`. An operation amount is bad when unreadable or negative (`operations.amount_invalid` under the field, "Zapisz" disabled); the server rejects zero. Operation dialogs get this rule from `useOperationAmountInput`, so do not copy it into a dialog. Details: ADR-0040, ADR-0041 and `.docs/design-system.md`.
