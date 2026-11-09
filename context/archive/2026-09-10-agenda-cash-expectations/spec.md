# Agenda and Cash Expectations

Map: [Unpaid dated cash events: a plane next to Liquidity and Execution](https://github.com/sebastian-kostecki/household-treasurer/issues/68). Glossary: `context/foundation/glossary.md`. ADRs 0017–0022.

## Problem Statement

The User already knows some cash will move — a salary date, a bill, a shop refund coming back — but Household Treasurer only books **Financial Operations** on a cash basis (date cannot be in the future). There is no honest place for a known, not-yet-booked movement. Putting it on Operations would fake Execution and Liquidity. Putting it on Budget would earmark **Available Limit**. Spreadsheets and calendar reminders sit outside the ledger, so settlement is easy to double-book or forget.

## Solution

Add **Agenda**: a plane of **Cash Expectations** beside Liquidity and Execution. Each Cash Expectation is one dated income, expense, or expense refund that is not yet a Financial Operation. While unpaid it does not move Liquidity, Execution, Available Limit, or Planned Balance. The User creates, edits, deletes, and settles only on Agenda. Dashboard notices overdue and today (any year). Settlement leaves exactly one Financial Operation and **destroys** the Cash Expectation (spawn a new FO, or attach an existing one). Import never creates Cash Expectations; a preview row may settle one when the User opts in on a **Settlement candidate**.

This is a calendar of known events, not a debt ledger, not a monthly liquidity forecast, and not a recurrence engine.

## User Stories

1. As a User, I want a primary-nav item **Agenda** after Operacje (Dashboard, Budżet, Konta, Lokaty, Operacje, Agenda), so that unpaid cash sits on its own plane.
2. As a User, I want Agenda to follow the shell Budget year like Budget, so that 2026 bills are not mixed with 2027 while I am looking at 2027.
3. As a User, I want Agenda to be a date-ordered list for that shell year (overdue, today, then later dates), so that I scan a calendar of events without a month-grid widget in this spec.
4. As a User with no Cash Expectations in the shell year, I want an empty Agenda to be a valid forever state, so that First Run and daily use do not require a row.
5. As a User, I want to create a Cash Expectation from Agenda (not from Operations or Budget), so that I do not pretend it is a Financial Operation or an earmark.
6. As a User, I want a Cash Expectation to be income, expense, or expense refund (negative expense amount; not a fourth persisted type), so that the shapes match Financial Operation.
7. As a User, I want required date, non-zero amount in Minor units, description, Account, and Category of matching type, so that the record is a complete expected movement.
8. As a User, I want optional Counterparty, so that payer/payee can wait until settlement.
9. As a User, I do not want Raw Statement Description, notes, status, or a Financial Operation foreign key on this record, so that it stays unpaid and hand-entered.
10. As a User, I want the date to allow future, today, or already overdue at create, so that I can catch up without a “date ≤ today” cap.
11. As a User, I want the date blocked if it is before the Account opening date, so that Agenda cannot precede the holding.
12. As a User, I want the date’s calendar year to equal the Category’s Budget year (draft or execution), so that next year’s bills can sit on a draft Budget.
13. As a User with no Budget for that year, I want create and edit rejected, so that a year without a plan cannot hold Agenda rows.
14. As a User, I want archived Account or Category rejected on create and edit, so that retired holdings are not used for new expectations.
15. As a User, I want create to leave Account current balance, Category Execution, Available Limit, Planned Balance, Spendable Liquidity, and Total funds unchanged, so that unpaid is not booked.
16. As a User, I want to edit an unpaid Cash Expectation with the same fields and validation as create (including income ↔ expense refund, not ordinary expense ↔ income), so that I can correct it before settlement.
17. As a User, I want to delete an unpaid Cash Expectation without moving Liquidity or Execution, so that dropping a plan does not rewrite the books.
18. As a User, I do not want cancel, skip, or stored overdue status, so that the only clock is “date before today”.
19. As a User, I want overdue derived from the date vs today (no job, no auto-settle, no auto-delete), so that passing midnight only changes chrome.
20. As a User, I want to settle from that Cash Expectation only (the blank income/expense form does not settle), so that settlement is intentional.
21. As a User settling by hand, I want a prefilled Financial Operation (same type family) with date defaulting to **today** in all cases, and editable date, amount, description, Account, Category, and Counterparty, so that I can correct on the way in.
22. As a User, I want a future Financial Operation date still forbidden (FR-TRX-03), so that cash basis of booked operations stays.
23. As a User whose spawned amount differs from the Cash Expectation, I want that save to book one Financial Operation and destroy the Cash Expectation in full, so that the difference is a correction, not a remainder or installment.
24. As a User, I do not want an extra mismatch warning on that correction (Available Limit and Negative Balance Warning already run on the Financial Operation), so that settlement is not a second warning regime.
25. As a User who still expects leftover cash after a correction, I want to create a **new** Cash Expectation by hand, so that leftovers are explicit.
26. As a User whose spawn save fails validation or is abandoned, I want the Cash Expectation to stay unpaid on Agenda, so that a failed settle does not destroy the plan.
27. As a User whose Budget for the Financial Operation date is still draft, I want spawn rejected like any Financial Operation, so that Execution still requires a started year.
28. As a User settling on an archived Account, I want spawn rejected until I edit the Cash Expectation to a live Account or restore the Account.
29. As a User, I want a successful spawn to be an ordinary Financial Operation (no Raw Statement Description, execution Budget for the FO date’s year), and the Cash Expectation destroyed with no unpay and no paid list.
30. As a User who already booked the movement (unmarked Import or blank-form FO), I want settle-from-Agenda to **attach** that Financial Operation: no second FO, FO fields not rewritten, Cash Expectation destroyed.
31. As a User attaching, I want the offer set to be Settlement candidates: same User, Account, type family, exact type-aware amount, dates within 31 inclusive calendar days (CE date vs live FO date); description, Counterparty, and Category do not gate.
32. As a User, I want Own Transfer and Capital Movement never offered as Settlement candidates.
33. As a User whose Cash Expectation is 800 and the Financial Operation is 500, I want attach refused until I edit the Cash Expectation to 500, so that attach stays exact-amount.
34. As a User, I want a Financial Operation already used in an attach to stop being a candidate, so that two Cash Expectations cannot consume the same FO.
35. As a User who deletes or edits that Financial Operation later, I do not want the Cash Expectation recreated, so that history is the FO alone.
36. As a User importing, I do not want Import to create Cash Expectations from pending or future-dated file rows.
37. As a User on Import preview, I want Settlement candidates offered per row (Account + type family + exact type-aware amount + 31-day window vs **file** date); I pick at most one; opt-in is required; auto-match is out.
38. As a User, I want invalid and Import duplicate still to win over settlement overlay; Import-duplicate rows are not settlement candidates (attach from Agenda instead).
39. As a User, I want pair opt-in and settlement opt-in mutually exclusive on the same preview row; a stale settlement pick clears when the row fails the predicate.
40. As a User opting in, I want file date, amount, description, Counterparty, and Raw Statement Description unchanged; Category prefills from the Cash Expectation only if the row has none and that Category is legal for the file date’s year.
41. As a User whose Cash Expectation Category is illegal for the file/FO date’s year, I want to pick a Category for that year; attach still does not rewrite the Financial Operation.
42. As a User committing Import with settlement opt-in, I want one Import-born Financial Operation and the Cash Expectation destroyed in the same atomic commit as the rest of the selected rows.
43. As a User, I want Dashboard to notice overdue and today by **calendar date**, any Budget year, as notice only (no create, no settle, no Agenda nav badge).
44. As a User clicking a Dashboard notice row, I want the shell Budget year to switch to that Cash Expectation’s date year and Agenda to open, so that I act on the right year.
45. As a User, I do not want Operations or Budget to grow a “new Cash Expectation” action or to list unpaid rows as operations.
46. As a User, I want an Account named by a Cash Expectation undeletable; archive allowed; existing rows keep pointing; new rows cannot use the archived Account.
47. As a User, I want Account opening date unable to move later than the earliest Cash Expectation on that Account.
48. As a User, I want a Category named by a Cash Expectation undeletable and its type unchangeable; archive allowed with the same “existing keep pointing / new cannot use” rule as Account.
49. As a User, I want a draft Budget that still has Cash Expectations on its Categories undeletable, so that Agenda rows are not silently orphaned.
50. As a User, I want First Run to stay four start conditions (Account, Budget, Category, Budget in Execution); a Cash Expectation is not a start condition; checklist copy stays those four rows; hide still requires all four.
51. As a User, I want Agenda reachable during incomplete First Run (like Operacje and Lokaty), and the overdue/today notice independent of the checklist.
52. As an administrator, I want `user:backup` to write unpaid Cash Expectations under JSON key `cash_expectations`, `user:restore` to remap Account and Category then insert them, and `user:reset-data` to delete them with the domain wipe.
53. As an administrator restoring a snapshot that predates this key (versions 1–4), I want Agenda empty, not a rejected file.
54. As an administrator, I want a **new** snapshot version (5; code writes 4 today) to require `cash_expectations`; missing it is FR-ADM-04.
55. As a User, I cannot read or change another User’s Cash Expectations.
56. As a guest, I cannot open Agenda or write Cash Expectations.

## Implementation Decisions

- New first-class Inertia page and authenticated routes, sibling to Term Deposits / Operations: index (Agenda list for `shell.budgetYear`), create, update, destroy, spawn-settle, attach. Wayfinder for Vue. Do not add create onto `IncomeController` / `ExpenseController` store; those blank forms must not accept a Cash Expectation id.
- Persist one table of unpaid Cash Expectations owned by the User. Amounts are signed bigint Minor units. Expense refund is a negative expense amount. No status column. No `financial_operation_id`. Destroy the row on successful spawn, attach, or Import opt-in.
- Policy isolation like Financial Operation. Form Requests + Actions in the neighbouring style (`ManageFinancialOperation` for the spawned FO; a dedicated Action to create/update/delete Cash Expectations and to attach/destroy).
- Validation: date may be future; date ≥ Account opening date; Category type matches; date year = Category Budget year; that Budget may be draft or execution; archived Account/Category invalid; Own Transfer and Capital Movement are not types.
- Spawn: authorize the Cash Expectation, then create the Financial Operation with existing create rules (execution Budget, FR-TRX-03, warnings). Destroy the Cash Expectation only after that write succeeds, in one transaction. Redirect to Agenda (or Operations only if that matches neighbouring settle UX — prefer Agenda). Date on the form defaults to today even when the Cash Expectation is overdue or future.
- Attach: POST with a Financial Operation id that currently satisfies the Settlement candidate predicate; do not rewrite FO fields; destroy the Cash Expectation. Reject if not a candidate (including amount mismatch).
- Import: extend preview like Import pair candidate (a set per row, User pick, default off). Invalid and Import duplicate win. Pair and settlement mutually exclusive. Import duplicate rows are not settlement candidates. Commit destroys the picked Cash Expectation with the new FO. Do not create Cash Expectations from the file.
- Shell nav: add Agenda after operations in `lang/pl/ui.php` (`nav.agenda` => `Agenda`). App shell test provider gains the new route. Visual language stays OpenShip (ADR-0001 / ADR-0003). List chrome is a date-ordered grouping for the shell year, not a month grid.
- Dashboard payload: overdue and today Cash Expectations for this User (any year), notice-only. Click uses existing `budget.select-year` then visits Agenda (same year-switch behaviour as other shell year changes).
- Account destroy / opening-date update: treat Cash Expectations as history for FR-ACC-04/06 analogs. Category destroy and type change: block while named by a Cash Expectation. Draft Budget destroy: block while any of its Categories are named by a Cash Expectation.
- Snapshots: bump `user:backup` version to **5**; require `cash_expectations` on v5; restore v1–4 without the key as empty Agenda. Reset wipes the table. Spatie whole-database backups (ADR-0013) need no extra path. Extends ADR-0008; no new snapshot ADR.
- Update `context/foundation/prd-mvp.md` and `context/foundation/mvp.md`: Cash Expectation / Agenda in scope; BR-14 remains “they are not Financial Operations”; §12 first bullet narrows to debt ledger / remaining loan balance (Cash Expectation is not that). FR-ADM-01 lists `cash_expectations` and version 5 (restore still accepts 1–4). First Run unchanged (FR-TD-11 analog).
- Polish UI copy in `lang/pl/ui.php`. Code and docs stay English (`Cash Expectation`, `Agenda`, `Settlement candidate`). Do not label the nav Zobowiązania, Harmonogram, or Prognoza.

## Testing Decisions

- Highest seam: Laravel HTTP / Inertia feature tests (`actingAs`, `get`/`post`/`patch`/`delete`, `RefreshDatabase`, factories). Assert redirects, Inertia props, and the database (balances, Execution, Available Limit, presence/absence of the Cash Expectation). Do not assert Vue internals, Tailwind, or screenshots. No `pest-plugin-browser`.
- Prior art: `TermDepositTest`, `ExpenseTest`, `IncomeTest`, `CsvImportTest` (pair-candidate overlay), `DashboardTest`, `AppShellTest`, `UserSnapshotCommandTest`, `ResetUserDataCommandTest`, `ArchiveAccountTest`, `AccountTest` (opening date / delete), `ArchiveCategoryTest`.
- Prefer a new `tests/Feature/CashExpectationTest.php` (or `AgendaTest.php`) for CRUD, year rules, no-ledger-on-unpaid, overdue derivation, spawn, attach, correction amount, failed spawn leaves the row, isolation. Extend `CsvImportTest` for settlement overlay, mutual exclusion with pair, duplicate-wins, atomic destroy. Extend dashboard, shell, snapshot (v5 key required; v4 restore empty Agenda), account/category/budget destroy fences. Do not duplicate guest-auth for every new route if `AppShellTest` already covers GET; do cover guest POST.
- Do not add a unit test that restates the 31-day predicate unless a detector Action cannot be seen through HTTP preview/attach.

## Out of Scope

- Recurrence / series templates.
- Debt ledger, remaining loan balance, credits, tax, installments as a verb.
- Own Transfer or Capital Movement on Agenda.
- Import creating Cash Expectations.
- Monthly schedules and liquidity forecasts.
- Term Deposit maturity on Agenda (other map).
- Month-grid calendar widget, Agenda nav badge, Operations/Budget as a second home.
- Unpay, paid/cancelled lists, resurrecting a Cash Expectation when a Financial Operation is deleted.
- Auto-match Import; force-attach on amount mismatch; auto-creating a remainder Cash Expectation.
- Fifth First Run start condition or checklist copy that teaches Agenda.
- Formal WCAG audit (NFR-ACC still deferred to dogfood).

## Further Notes

- Next step is `/to-tickets` against this spec, then `/implement` per ticket. Do not implement this GitHub issue as one shot.
- Polish Agenda label is **Agenda**. Date-ordered list (overdue / today / later) is the chrome; a month grid would be a later effort.
- Seams: one HTTP/Inertia seam, same as Term Deposits and Import.
