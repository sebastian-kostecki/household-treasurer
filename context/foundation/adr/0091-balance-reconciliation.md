# Balance Reconciliation: comparing an Account with its bank is a record, not a lock

The only balance the application keeps is `accounts.current_balance_minor_units`, a counter moved by every Financial Operation, Own Transfer and Capital Movement. Nothing tells the User whether that counter still equals what the bank shows, and nothing answers "what did this Account hold on 31 October". The User compares the app with the bank statement by hand and wants that comparison written down, with the difference visible and the operations since the last comparison at hand. A later trend of Total funds needs the same reading of a past day.

Rule. A **Balance Reconciliation** is a record of three facts: the Account, the day D, and the balance the bank showed (`balance_reconciliations`: `user_id`, `account_id`, `reconciled_on`, `bank_balance_minor_units`). Whether it agrees is not stored. The Difference is the bank balance minus **Balance on a Date** of D, and the reconciliation agrees when it is 0. The state is computed on every read, so a late operation dated on or before D turns an agreeing reconciliation into a disagreeing one, and a fix turns it back.

Balance on a Date. `App\Support\AccountBalanceOnDate`: the opening balance plus every Financial Operation (signed by type: income and expense refund add, expense subtracts), Own Transfer (destination adds, source subtracts) and Capital Movement (from a Term Deposit adds, to a Term Deposit subtracts) of the Account dated on or before D. A Cash Expectation is not in it. Before the opening date there is no balance (the reading is `null`). From the last dated record of the Account on, it equals the stored current balance; a test proves that equality, so the counter and the reading cannot drift apart unseen. The reading serves one Account at a time; the Total funds trend will sum it over Accounts and add Term Deposits in its own change.

Constraints.

- D is not in the future and not before the Account opening date. A Sheet Account (ADR-0085) and an archived Account have no reconciliation.
- One Account has at most one reconciliation per day (unique on `account_id`, `reconciled_on`). Saving the same day again replaces the bank balance, which is also how a typo is fixed.
- It blocks nothing. Operations dated before D can still be created, edited and deleted; the policies, the Import and the Cash Expectation settlement do not read it.
- It creates no correcting operation and changes no balance, Liquidity, Execution, Available Limit or Month Result. The User fixes a difference by editing or adding the real operation.
- The bank balance may be negative or zero (an overdraft, an empty cash box).

UI and HTTP. The Accounts page gets an icon action "Uzgodnij saldo…" (`IconAction`, like „Edytuj”) in the row of every non-archived Account. The dialog (`BalanceReconciliationDialog`) takes the day (default: today) and the bank balance, shows the Balance on a Date of that day and the Difference live as the User types (the day is read from `GET accounts/{account}/reconciliation-preview?reconciled_on=`), and lists the operations of the Account dated after the previous reconciliation, up to D (the newest 100, with a note when there are more). A mismatch is a `warning` Badge, never `destructive`: it is not an exceeded limit. `POST accounts/{account}/reconciliations` (`accounts.reconciliations.store`, action `App\Actions\ReconcileAccountBalance`) saves it and answers with the Accounts page. Each Account row carries `last_reconciliation` (`null`, or the day, the bank balance, the Difference and `agrees`, computed on the day it was saved against today's data) so the page shows "Uzgodniono 31.10.2026 · zgadza się / nie zgadza się (różnica −12,40 zł)".

Snapshot. `balance_reconciliations` enters the User's snapshot as `balance_reconciliations`, version 12 → 13 (`.ai/rules/snapshot.md`). The restore maps `account_id` through the restored Accounts and refuses a row that points at an Account outside the snapshot, a date that is not a date, a second row for one Account and day, and a bank balance that is not an integer. Versions 1 to 12 restore with no reconciliations. `user:reset-data` removes them with the Accounts; `app:control-numbers` does not read them (they change no number it compares).

We rejected:

- locking operations up to D (a lock turns a comparison into a closing of the books; the User edits old operations to fix real mistakes, and Import must keep filling past days);
- a correcting operation for the Difference (it would invent an income or expense with a Category and distort Execution and the reports; the Difference is a symptom of a missing or wrong real operation);
- storing the state `agrees` (it would go stale on every late edit and need a recompute on every write path of three operation types);
- a free number of reconciliations per day (the second one on the same day says the first was wrong or the day moved; one row per day keeps "the last reconciliation" a plain maximum of D);
- deleting a reconciliation in this change (a wrong value is replaced by saving the day again; a wrong day can be left as a harmless row, and removal can follow if the User asks for it).
