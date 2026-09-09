# Reclassify Income as expense refund on edit

## Problem Statement

A shop return lands on the bank file as a credit. Import defaults that row to Income, and the User parks it on any income Category. The Budget then shows extra income and the original spend still on the expense Category (for example clothes still at 200 PLN after 150 PLN came back). The User does not want that credit as Income; they want net spend on the expense Category. Expense refund already exists as a negative expense. Edit cannot change Income into that record: income and expense updates refuse the other type, so the User would have to delete and re-enter.

## Solution

On edit, a Financial Operation that is Income can become an expense refund in one save: the User marks it as a refund and picks an expense Category. The previous Income effect is reversed and the refund is applied (Account net unchanged when Account and absolute amount stay the same; income Execution falls; expense Execution falls by the refund). The reverse is also allowed: an expense refund can become Income. Import, create-income, and linking a refund to a specific purchase stay unchanged (ADR-0015).

Recorded in ADR-0015. Requirements: FR-TRX-13–16.

## User Stories

1. As a User who imported a 150 PLN shop return as Income on any income Category, I want to edit that Financial Operation into an expense refund on clothes, so that clothes Execution is net spend and income Execution no longer includes the return.
2. As a User editing that conversion, I want one save to reverse the Income and apply the refund, so that I do not delete and re-enter.
3. As a User who kept the same Account and the same absolute amount, I want the Account current balance unchanged after the conversion, so that Liquidity still matches the bank credit.
4. As a User converting Income to an expense refund, I want to pick an expense Category for the operation date’s Budget year, so that the refund hits the right bucket.
5. As a User who parked the credit on the wrong income Category, I want the conversion to work the same whether the Income was Import-born or hand-entered, so that I am not gated on Raw Statement Description.
6. As a User who converted by mistake (it was salary), I want to edit the expense refund back into Income on an income Category, so that I can undo the reclassification.
7. As a User creating Income by hand, I do not want a refund control on the create-income dialog, so that new refunds still start from add-expense plus the refund checkbox.
8. As a User importing a credit, I want Import preview to keep defaulting `+` to Income with no refund hint or match overlay, so that I can park any income Category and fix later.
9. As a User with a positive expense, I do not want that edit path to turn it into Income, so that money-out cannot silently become money-in.
10. As a User converting Income, I do not want that edit path to turn it into a positive expense, so that a parked credit cannot be stored as money leaving the Account.
11. As a User whose expense Budget for that year has no usable expense Category, I want the conversion rejected, so that a refund cannot land without a matching Category.
12. As a User changing Account or amount in the same save as the conversion, I want balances recalculated from the new values after reverting the old Income, so that one edit stays consistent.
13. As a User of an Import-born Financial Operation, I want Raw Statement Description and the Import duplicate fingerprint unchanged by reclassification, so that a later re-Import still sees the same row.
14. As a User, I want the list to show the record as an expense refund after conversion (and as Income after the reverse), so that type chrome matches the Budget.
15. As a User who does not own the Financial Operation, I cannot reclassify it.

## Implementation Decisions

- Keep using the existing income update and expense update HTTP actions (Wayfinder). Do not add a reclassify route.
- Income update, on edit of an Income, may accept a refund conversion: expense Category of matching type and year, amount stored as a negative expense (same absolute Minor units unless the User also changes the amount). After a successful conversion the record’s type is Expense. Subsequent edits use the expense update action.
- Expense update, on edit of an expense refund (negative amount), may accept an Income conversion: income Category of matching type and year, amount stored as positive Income. After a successful conversion the record’s type is Income.
- Reject: Income → positive expense; positive expense → Income; positive expense → refund is already the existing refund checkbox on expense edit and is unchanged; create-income must not grow a refund control.
- Apply the existing update rule: revert the stored effect, then apply the new type, amount, Account, and Category in one transaction (same as today’s edit). Do not invent a second balance path.
- Income edit dialog (edit mode only): reuse the existing “this is an expense refund” copy to switch the Category picker to expense Categories for that year. Create-income stays income-only.
- Expense edit dialog: when the current record is an expense refund, offer a control to treat it as Income and switch the Category picker to income Categories. Unchecking refund on a refund must remain “positive expense”, not Income.
- Warning preview uses the proposed kind (refund or income) after revert-of-original, same as other edits. Same Account and same absolute amount typically produce no Account movement.
- Do not change Import preview defaults, type options, or pair-candidate behaviour.
- Do not add a foreign key from the refund to an earlier expense Financial Operation.
- Polish UI copy in `lang/pl/ui.php`. Glossary stays English (`Income`, `expense refund`, `Category`). Do not call the parked credit a fourth persisted type.
- Validation errors render on the dialog fields (Category, amount). Existing date, Account opening date, archived, and execution-Budget rules still apply to the destination Category.
- Import identity columns and Raw Statement Description are not written on this edit (ADR-0005, ADR-0007).

## Testing Decisions

- Feature tests in `tests/Feature/FinancialOperationEditDeleteTest.php` (or a sibling Feature file if that file grows past neighbouring size). HTTP seam: `actingAs`, `patch` to the income update route and the expense update route, `RefreshDatabase`, factories. Assert redirect, database type/amount/Category, Account current balance, and both Categories’ Execution. Do not assert Vue internals, Tailwind, or screenshots. No `pest-plugin-browser`.
- Cover: Income → expense refund recalculates (income Execution down, expense Execution down by the refund, Account unchanged when Account and absolute amount stay the same); refund → Income reverse; Import-born Income (fingerprint and Raw Statement Description unchanged); hand-entered Income converts the same; conversion without a destination Category of the right family/year is rejected and leaves the original record; Income → positive expense rejected; positive expense → Income rejected; other User’s Financial Operation cannot be updated; create-income still cannot store a refund; Import commit of a credit as Income is unchanged.
- Guest cannot patch (existing auth coverage is enough if it already hits these routes).
- Do not add a unit test that restates the revert-then-apply arithmetic unless a new Action grows logic that HTTP cannot see.

## Out of Scope

- Import preview hints, empty default type, or auto-setting type from Category family.
- Matching a credit to a specific earlier expense Financial Operation.
- Changing a positive expense into Income, or Income into a positive expense, on edit.
- A refund control on create-income.
- A new persisted type besides Income and Expense.
- Own Transfer conversion in this ticket.

## Further Notes

- Glossary: `CONTEXT.md`. Product requirements: `.docs/prd.md` (FR-TRX-13–16, BR-17). Decision: `.docs/adr/0015-reclassify-shop-return-on-edit-not-import.md`.
- Visual language stays the OpenShip surface recipe (ADR-0001 / ADR-0003). This spec adds a control to existing edit dialogs; it does not restyle the shell.
