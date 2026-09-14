# Agenda, Agenda Category, and monthly Cash Expectations

Spec: [Agenda, Agenda Category, and monthly Cash Expectations](https://github.com/sebastian-kostecki/household-treasurer/issues/120). Supersedes: [Agenda and Cash Expectations](https://github.com/sebastian-kostecki/household-treasurer/issues/88). Glossary: `CONTEXT.md`. ADRs 0017–0022, 0024–0027.

## Problem Statement

The User already knows some cash will move — a salary date, rent, a loan installment, a shop refund coming back — but Household Treasurer only books **Financial Operations** on a cash basis (date cannot be in the future). There is no honest place for a known, not-yet-booked movement. Putting it on Operations would fake Execution and Liquidity. Putting it on Budget would earmark **Available Limit**.

The existing Budget month reading (month chips and the **W miesiącu** column) cannot show that honesty either. Paced leftover and One-off Execution do not say “rent is expected on the 10th” versus “rent hit the statement”. Spreadsheets and calendar reminders sit outside the ledger, so settlement is easy to double-book or forget. Recreating twelve rent rows by hand is the other failure: a live series would lie about next year and about leftover loan principal, both of which this product refuses.

## Solution

Add **Agenda**: a plane of **Cash Expectations** beside Liquidity and Execution. Each Cash Expectation is one dated income, expense, or expense refund that is not yet a Financial Operation. While unpaid it does not move Liquidity, Execution, Available Limit, or Planned Balance. The User creates, edits, deletes, and settles only on Agenda. Dashboard notices overdue and today (any year). Settlement leaves exactly one Financial Operation and **destroys** the Cash Expectation (spawn a new FO, or attach an existing one). Import never creates Cash Expectations; a preview row may settle one when the User opts in on a **Settlement candidate**.

Give Categories a third, exclusive variant: **Agenda Category** (Polish: „powiązana z agendą”). An expense Category is Paced, One-off, or Agenda Category. An income Category is ordinary or Agenda Category. Only an Agenda Category may be named by a Cash Expectation. Its Budget **W miesiącu** cell is a **list** of that month’s unpaid Cash Expectations and booked Financial Operations (membership is each record’s own date), not Month Remaining and not one mashed number. Create stays on Agenda; Budget is a reading. Annual Current Plan and Available Limit stay User-set; unpaid Cash Expectations do not earmark them.

On create, occurrence is **Raz** (one-shot, always a calendar date) or **Co miesiąc** (create-only). Monthly occurrence writes sibling rows from the first date’s month through December of that Budget year, then drops the grouping: no series, no edit-future, no automatic next year.

This is a calendar of known events plus a Budget reading of those events, not a debt ledger, not a monthly liquidity forecast, and not a live recurrence engine.

## User Stories

1. As a User, I want a primary-nav item **Agenda** after Operacje (Dashboard, Budżet, Konta, Lokaty, Operacje, Agenda), so that unpaid cash sits on its own plane.
2. As a User, I want Agenda to follow the shell Budget year like Budget, so that 2026 bills are not mixed with 2027 while I am looking at 2027.
3. As a User, I want Agenda to be a date-ordered list for that shell year (overdue, today, then later dates), so that I scan a calendar of events without a month-grid widget in this spec.
4. As a User with no Cash Expectations in the shell year, I want an empty Agenda to be a valid forever state, so that First Run and daily use do not require a row.
5. As a User, I want to create a Cash Expectation from Agenda (not from Operations or Budget), so that I do not pretend it is a Financial Operation or an earmark.
6. As a User, I want a Cash Expectation to be income, expense, or expense refund (negative expense amount; not a fourth persisted type), so that the shapes match Financial Operation.
7. As a User, I want required date, non-zero amount in Minor units, description, Account, and an **Agenda Category** of matching type, so that the record is a complete expected movement on a Category that knows how to show it.
8. As a User, I want optional Counterparty, so that payer/payee can wait until settlement.
9. As a User, I do not want Raw Statement Description, notes, status, a series id, or a Financial Operation foreign key on this record, so that it stays unpaid, hand-entered, and one movement.
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
45. As a User, I do not want Operations or Budget to grow a “new Cash Expectation” action, so that Agenda stays the only write home.
46. As a User, I want an Account named by a Cash Expectation undeletable; archive allowed; existing rows keep pointing; new rows cannot use the archived Account.
47. As a User, I want Account opening date unable to move later than the earliest Cash Expectation on that Account.
48. As a User, I want a Category named by a Cash Expectation undeletable and its type (income/expense) unchangeable; archive allowed with the same “existing keep pointing / new cannot use” rule as Account.
49. As a User, I want a draft Budget that still has Cash Expectations on its Categories undeletable, so that Agenda rows are not silently orphaned.
50. As a User, I want First Run to stay four start conditions (Account, Budget, Category, Budget in Execution); a Cash Expectation is not a start condition; checklist copy stays those four rows; hide still requires all four.
51. As a User, I want Agenda reachable during incomplete First Run (like Operacje and Lokaty), and the overdue/today notice independent of the checklist.
52. As an administrator, I want `user:backup` to write unpaid Cash Expectations under JSON key `cash_expectations`, `user:restore` to remap Account and Category then insert them, and `user:reset-data` to delete them with the domain wipe.
53. As an administrator restoring a snapshot that predates this key (versions 1–4), I want Agenda empty, not a rejected file.
54. As an administrator, I want a **new** snapshot version (5; code writes 4 today) to require `cash_expectations`; missing it is FR-ADM-04.
55. As a User, I cannot read or change another User’s Cash Expectations.
56. As a guest, I cannot open Agenda or write Cash Expectations.
57. As a User creating an expense Category, I want to choose **Paced**, **One-off**, or **Agenda Category** as exclusive variants, so that rent is not mixed into groceries’ Month Remaining.
58. As a User creating an income Category, I want to choose ordinary (annual plan only) or **Agenda Category**, so that salary can show on the month reading without inventing Paced income.
59. As a User, I want a new expense Category to default to Paced and a new income Category to default to ordinary, so that Agenda is an explicit choice.
60. As a User, I want Polish Category copy **Równomierna / Jednorazowa / Powiązana z agendą**, so that “jednorazowa” stays the One-off Category word already in the product.
61. As a User, I want Cash Expectation create copy **Raz** vs **Co miesiąc** (not “jednorazowe”), so that occurrence is not confused with One-off Category.
62. As a User, I want only an Agenda Category offered when creating or editing a Cash Expectation, so that a Paced or One-off Category cannot be named by one.
63. As a User, I want create/edit of a Cash Expectation rejected when the Category is Paced, One-off, ordinary income, archived, or the wrong income/expense type, so that the month reading stays honest.
64. As a User, I want an Agenda Category to keep a User-set annual Current Plan and (if expense) Available Limit, not derived from Cash Expectations, so that Planned Balance still includes rent before it is booked.
65. As a User, I want unpaid Cash Expectations not to reduce Available Limit, so that ADR 0018 stays: Agenda is not an earmark.
66. As a User, I want expense Agenda Categories excluded from the Paced pool and from Month Remaining, so that leftover groceries are not diluted by rent.
67. As a User, I want to change a Category to or from Agenda Category while it is not named by a Cash Expectation, so that I can retag Żywność after a mistake without waiting for year-end.
68. As a User, I want that change blocked while any Cash Expectation names the Category (same fence as income ↔ expense), so that hanging rent rows cannot sit on a Paced Category.
69. As a User, I want an archived Category’s variant frozen, so that restore does not silently rewrite how the month column reads.
70. As a User copying Categories onto a new Budget, I want the Agenda Category variant copied and Cash Expectations omitted, so that 2027 rent is created again on Agenda, not cloned as last year’s unpaid rows.
71. As a User creating a Cash Expectation, I want to choose **Raz** and get exactly one dated row, so that a one-time bill is one movement.
72. As a User creating a Cash Expectation, I want to choose **Co miesiąc** and get one row per calendar month from the first date’s month through December of that Budget year, so that rent is not a live template.
73. As a User, I want each monthly sibling to keep the same day-of-month, clamped to the last day of that month (31 January → 28 or 29 February, then 31 March), so that February is not skipped.
74. As a User whose Account opened mid-year, I want months whose date would fall before the opening date skipped, so that January rent is not invented on a July Account.
75. As a User whose monthly create would skip every month, I want the create rejected, so that I do not get a silent empty save.
76. As a User, I want monthly occurrence to be create-only: editing a row never writes extra months, so that flipping Raz → Co miesiąc cannot duplicate July.
77. As a User, I want sibling rows independent after create (no group, no edit-future), so that a July rent rise is an edit of July–December, not a hidden series.
78. As a User settling or deleting one sibling, I want the other months to stay unpaid, so that paying June rent does not erase November.
79. As a User, I want a one-shot create to always have a calendar date (no dateless “somewhere in September”), so that overdue and the 31-day Settlement candidate window still work.
80. As a User, I want the Budget expense table **W miesiącu** column for an Agenda Category to list each unpaid Cash Expectation and each Financial Operation whose own date falls in the viewed month (date, description, amount, expected vs booked), so that I see agenda executed or not.
81. As a User who skipped Import settlement, I want that month to show two rows (the unpaid Cash Expectation and the booked Financial Operation), not a doubled total and not a hidden expectation, so that I am prompted to attach.
82. As a User, I want an optional sum on that list to be extra, never a substitute, and hidden when the list is empty, so that a mashed number cannot replace the rows.
83. As a User viewing a month with no Cash Expectations and no Financial Operations on that Agenda Category, I want an empty list (not an em dash, not a lone zero), so that “nothing this month” is distinct from One-off’s “no month reading”.
84. As a User, I want membership of a list row to be that record’s own date; overdue Cash Expectations do not roll into the current chip, so that May rent stays in May while Agenda still shows it as overdue.
85. As a User who spawns a late settlement (Financial Operation date = today in a later month), I want the estimate to leave the Cash Expectation’s month once destroyed and the booking to appear in the Financial Operation’s month, so that the column stays cash-basis.
86. As a User, I want one-shot and monthly Cash Expectations on the same Agenda Category in the same month to appear as separate list rows, so that a one-time extra and the regular rent are both visible.
87. As a User, I want clicking an unpaid list row to open Agenda (switching shell year when needed), and clicking a booked row to open Operations, so that Budget stays a reading.
88. As a User, I want to book a Financial Operation on an Agenda Category without settling a Cash Expectation, so that cash-basis entry and Import without opt-in still work.
89. As a User, I want no extra warning when a booked amount differs from the estimate, so that Available Limit and Negative Balance Warning remain the only save-time notices.
90. As a User, I want the income table on Budget to gain the same **W miesiącu** column: Agenda Category uses the list; ordinary income uses Execution in that month or an em dash, like One-off, so that salary is readable beside rent.
91. As a User, I want Dashboard month leftover to treat an Agenda Category like One-off (no Month Remaining, not a mini-list), so that the Budget list is not duplicated on Dashboard.
92. As a User, I want hand-entry and Import Month Remaining warnings to skip Agenda Categories the same way they skip One-off, so that rent does not raise a Paced leftover Uwaga.
93. As a User aligning Current Plan on an Agenda Category, I want Align to stay the annual rewrite (max(0, Execution)), not a close of a month list, so that ADR 0024 is not reopened.
94. As an administrator restoring a v1–4 snapshot, I want `is_paced` true to become Paced (expense) or ordinary (income), `is_paced` false to become One-off, and never Agenda Category, so that old files do not invent rent Categories.
95. As an administrator, I want snapshot version 5 to write the Category variant alongside `cash_expectations`, so that Agenda Categories survive backup/restore.
96. As a User, I want Paced Drip / “Na bieżąco” (separate Month Pace work) to stay a flag only on Paced Categories, so that Agenda Category is not a fourth pacing mode.

## Implementation Decisions

- New first-class Inertia page and authenticated routes, sibling to Term Deposits / Operations: index (Agenda list for the shell Budget year), create, update, destroy, spawn-settle, attach. Wayfinder for Vue. Do not add create onto the blank income/expense store; those forms must not accept a Cash Expectation id.
- Persist one table of unpaid Cash Expectations owned by the User. Amounts are signed bigint Minor units. Expense refund is a negative expense amount. No status column. No Financial Operation foreign key. No series/group column. Destroy the row on successful spawn, attach, or Import opt-in.
- Policy isolation like Financial Operation. Form Requests + Actions in the neighbouring style (existing Financial Operation manage Action for the spawned FO; a dedicated Action to create/update/delete Cash Expectations and to attach/destroy). Monthly create is one request, one transaction, many inserts.
- Replace the Paced/One-off boolean with one exclusive persisted variant: expense is Paced, One-off, or Agenda Category; income is ordinary or Agenda Category. Do not add a second “uses Agenda” flag on Paced/One-off. Expense create/update uses a three-way control, not a checkbox that can only express two states. Income create/update gains the ordinary vs Agenda Category control (income still cannot be Paced or One-off). Archived Categories reject variant changes. A Category named by a Cash Expectation rejects variant change and income/expense type change.
- Validation for Cash Expectation: date may be future; date ≥ Account opening date; Category is an Agenda Category of matching type; date year = Category Budget year; that Budget may be draft or execution; archived Account/Category invalid; Own Transfer and Capital Movement are not types. Occurrence (`Raz` / `Co miesiąc`) is accepted only on create; update ignores or rejects it rather than materializing extra months.
- Monthly create: from the first date’s month through December of that Budget year; same day-of-month, clamped to each month’s last day; skip any month whose date would be before the Account opening date; if no rows remain, 422. After insert the rows share no grouping. A December first date yields one row. Next Budget year is not written.
- Spawn: authorize the Cash Expectation, then create the Financial Operation with existing create rules (execution Budget, FR-TRX-03, warnings). Destroy the Cash Expectation only after that write succeeds, in one transaction. Redirect to Agenda. Date on the form defaults to today even when the Cash Expectation is overdue or future. Spawned Category may be any legal Category for the FO (including a non-Agenda Category); that is a correction of the booking, not a rewrite of remaining siblings.
- Attach: POST with a Financial Operation id that currently satisfies the Settlement candidate predicate; do not rewrite FO fields; destroy the Cash Expectation. Reject if not a candidate (including amount mismatch).
- Import: extend preview like Import pair candidate (a set per row, User pick, default off). Invalid and Import duplicate win. Pair and settlement mutually exclusive. Import duplicate rows are not settlement candidates. Commit destroys the picked Cash Expectation with the new FO. Do not create Cash Expectations from the file. Booking an Agenda Category without opt-in remains allowed.
- Budget month grain: keep Paced = Month Remaining and One-off = Execution-in-month or em dash. For each Agenda Category (income and expense), expose a list of unpaid Cash Expectations and Financial Operations whose own dates fall in `viewedMonth` of the shell year: date, description, signed Minor units, and whether the row is expected or booked. Optional sum is computed only when the list is non-empty and is not a replacement for the list. Empty viewed month = empty list. Overdue does not relocate a Cash Expectation into the current chip. Income table enables the same **W miesiącu** column (ordinary income uses the One-off cell rule). List rows are links: unpaid → Agenda (year switch if needed); booked → Operations. No create/edit/settle form on Budget.
- Paced pool, Dashboard month leftover, hand-entry Month Remaining preview, and Import Month Remaining warnings: treat Agenda Category like One-off (no Month Remaining, no leftover Uwaga). Align Current Plan stays annual. Copy onto a new Budget copies the variant, not Cash Expectations.
- Shell nav: add Agenda after operations (`nav.agenda` => `Agenda`). App shell test provider gains the new route. Visual language stays OpenShip. List chrome is a date-ordered grouping for the shell year, not a month grid.
- Dashboard payload: overdue and today Cash Expectations for this User (any year), notice-only. Click uses existing year-select then visits Agenda. Dashboard category meters do not grow a month list for Agenda Category.
- Account destroy / opening-date update: treat Cash Expectations as history for the existing Account history fences. Category destroy, type change, and variant change: block while named by a Cash Expectation. Draft Budget destroy: block while any of its Categories are named by a Cash Expectation.
- Snapshots: bump `user:backup` version to **5**; require `cash_expectations` on v5; write the Category variant on v5. Restore v1–4 without the key as empty Agenda; map `is_paced` true → Paced (expense) or ordinary (income), `is_paced` false → One-off, never Agenda Category. Reset wipes the Cash Expectation table. Spatie whole-database backups need no extra path. Extends the existing snapshot ADR; no new snapshot ADR.
- Update product docs: Cash Expectation / Agenda / Agenda Category in scope; BR-14 remains “they are not Financial Operations”; out-of-scope debt ledger / remaining loan balance stays (Cash Expectation is not that). FR-ADM-01 lists `cash_expectations` and version 5 (restore still accepts 1–4). Copy-Category requirement gains the variant. First Run unchanged.
- Polish UI copy in the existing UI language file. Code and docs stay English (`Cash Expectation`, `Agenda`, `Agenda Category`, `Settlement candidate`, `one-shot`, `monthly occurrence`). Do not label the nav Zobowiązania, Harmonogram, or Prognoza. Do not rename One-off’s „jednorazowa”.

## Testing Decisions

- Highest seam: Laravel HTTP / Inertia feature tests (`actingAs`, `get`/`post`/`patch`/`delete`, `RefreshDatabase`, factories). Assert redirects, Inertia props, and the database (balances, Execution, Available Limit, presence/absence/count of Cash Expectations, Category variant, Budget month list membership). Do not assert Vue internals, Tailwind, or screenshots. No browser plugin.
- Prior art: Term Deposit, Expense, Income, CSV Import (pair-candidate overlay), Dashboard, App shell, user snapshot commands, reset-user-data, archive Account, Account opening date / delete, archive Category, Category Paced flag, Budget Month Remaining, hand-entry Month Remaining, Import Month Remaining warnings, Budget copy-on-create.
- Prefer a new Cash Expectation / Agenda feature test for CRUD, year rules, Agenda-Category-only, no-ledger-on-unpaid, overdue derivation, one-shot vs monthly materialize (clamp, skip-before-opening, zero-remaining reject, December-only, sibling independence, edit does not materialize), spawn, attach, correction amount, failed spawn leaves the row, isolation. Extend CSV Import for settlement overlay, mutual exclusion with pair, duplicate-wins, atomic destroy, and Month Remaining warnings skipping Agenda Category. Extend Budget month reading for the Agenda Category list (two rows when settlement was skipped; overdue stays in its own month; empty list; income table column). Extend Dashboard (notice + leftover treats Agenda like One-off), shell, snapshot (v5 key and variant required; v4 restore empty Agenda and `is_paced` mapping), copy-on-create (variant copied, no Cash Expectations), account/category/budget destroy and variant-change fences. Do not duplicate guest-auth for every new route if App shell already covers GET; do cover guest POST.
- Cover Category variant: expense three-way, income two-way, defaults, archived freeze, named-by-CE block, Paced pool excludes Agenda Category.
- Do not add a unit test that restates the 31-day predicate or the day-clamp table unless that logic cannot be seen through HTTP create/preview/attach (it can).

## Out of Scope

- A live recurrence template, persisted series, edit-future / delete-future, or automatic Cash Expectations on the next Budget year.
- Debt ledger, remaining loan principal, credits, tax, installments as a verb.
- Own Transfer or Capital Movement on Agenda.
- Import creating Cash Expectations.
- A liquidity forecast (Spendable Liquidity projected forward).
- Term Deposit maturity on Agenda (other map).
- Month-grid calendar widget, Agenda nav badge, Budget or Operations as a second create home.
- Unpay, paid/cancelled lists, resurrecting a Cash Expectation when a Financial Operation is deleted.
- Auto-match Import; force-attach on amount mismatch; auto-creating a remainder Cash Expectation.
- Dateless “somewhere in this month” Cash Expectation; a separate “last day of month” occurrence mode.
- Rolling overdue Cash Expectations into the viewed month chip; pinning a Financial Operation to a destroyed Cash Expectation’s month.
- Deriving Current Plan from Cash Expectations; earmarking Available Limit.
- A second “over the estimate” warning regime.
- Drip / “Na bieżąco” (Month Pace): remains a Paced-only flag on its own spec.
- Fifth First Run start condition or checklist copy that teaches Agenda.
- Formal WCAG audit (still deferred to dogfood).

## Further Notes

- Next step is `/to-tickets` against this spec, then `/implement` per ticket. Do not implement this GitHub issue as one shot. Open Agenda tickets written against #88 (#89–#96) are stale: replace them, do not stretch their current text to cover Agenda Category, monthly materialize, or the month list.
- Polish Agenda label is **Agenda**. Date-ordered list (overdue / today / later) is Agenda chrome; the Budget month chips stay the month reading.
- Seams: one HTTP/Inertia seam, same as Term Deposits, Import, and Month Remaining.
- Contradicts the #88 out-of-scope “not a recurrence engine / not a monthly reading” only in the narrow sense recorded in ADR 0026 (materialized siblings) and ADR 0027 (Budget list reading). ADR 0017–0022 and 0024 still hold.
