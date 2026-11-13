# The Category of a sheet operation can be changed

ADR-0085 locks every operation of a Sheet Account: the policy answers 403 to any edit, and a wrong Category is fixed only by `budget:unload-sheet` and a new `budget:load-sheet` with a corrected mapping. In use the User finds single rows of the loaded years in the wrong Category on the Operations page and wants to move them there, one at a time, without reloading a whole year. This ADR replaces only the sentence of ADR-0085 „their Category cannot be changed in the UI”; every other rule of ADR-0085 stands.

Rule. An operation of a Sheet Account can change its Category, and only its Category. Amount, date, Account, description, counterparty and type stay as the load wrote them, so the year still sums to its sheet (`Analiza!B16`, `Analiza!C16`) and the Sheet Account keeps its balance. The full edit (`incomes.update`, `expenses.update`) still answers 403 for such an operation; the loaded year stays closed for hand-entry and Import.

Effect. The change moves the operation's amount from the old Budget Line to the new one in the Budget of the loaded year, in Execution, Current Plan and Initial Plan together. A loaded line has Plan = Execution (ADR-0085, decision 6), so the move gives the same lines as a reload with that row mapped to the other Category; it does not invent a deviation from a plan the User never made. Real Accounts, the Budgets 2026 and 2027, Month Plans, Rhythms and Collected do not change.

Choice. The new Category follows ADR-0067 for the year of the operation: the same type as the operation (a refund is an expense), active on the list, with a line in the loaded Budget that is not archived, or joining it through `JoinBudgetAtFirstUse` with plan 0 before the move. The check is `OperationCategoryChoice::checkSheetRecategorisation()`, the write `App\Actions\ChangeSheetOperationCategory`, both under one lock of the two lines.

UI and HTTP. `PATCH operations/{financial_operation}/category` (`operations.category.update`, policy `changeCategory`: owner and a Sheet Account) takes only `category_id`. Each row of the `operations` prop carries `sheet_operation` (`bool`); for such a row the "⋯" menu says „Zmień kategorię” instead of „Edytuj” and opens `OperationCategoryDialog`, which shows amount, date, Account and description as read-only text and one Category selector of the operation's year.

Amended: ADR-0085 (the sentence „their Category cannot be changed in the UI”).

We rejected:

- unlocking the full edit (a changed amount, date or Account would move the Sheet Account balance off 0 and the year off its sheet, or carry an operation out of the loaded year);
- moving only Execution (every recategorised line of a past year would show an overrun or a saving against a plan the sheet never had);
- reloading the year for every single row (the current way; it is right for a wrong mapping, too heavy for one misfiled operation).
