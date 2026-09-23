# Household Treasurer

A single-user household budget and liquidity tracker: annual category plans versus real cash on bank and cash accounts. UI is Polish; this glossary is the English domain language used in code and agent docs.

## Language

**User**:
The person who signs in. Owns an isolated data space. Not shared with household members in MVP.
_Avoid_: Account (for the login identity), Customer, Member

**Account**:
A liquid holding the User owns: a bank account or a cash account. Has an opening date, opening balance, and current balance. Independent of any Budget year. A bank Account may have a Bank. An Account named by a Cash Expectation cannot be deleted; archive is allowed (existing Cash Expectations keep pointing; new ones cannot use the archived Account). Opening date cannot be later than the earliest Cash Expectation, Financial Operation, Own Transfer, or Capital Movement on that Account.
_Avoid_: Wallet, Portfel, BankAccount (unless disambiguating in a narrow type name), “account” for the User, Term Deposit

**Term Deposit**:
A holding of the User’s money in a bank term-deposit product. Not an Account and not a Category. Each Term Deposit is its own holding, not a pooled balance. Not an Own Transfer endpoint and not the Account of a Financial Operation. Has an opening date (the first day this holding is in the ledger, not the bank product start) and a single opening balance: that snapshot is not Income, not a capital movement, not a split of capital versus interest, and does not change any Account. Opening balance may be zero or negative. Current balance is that snapshot plus Capital Movements; interest does not change the Term Deposit. Current balance may be negative. Create or edit of that snapshot that would leave current balance negative is Negative Balance Warning, not a block. In-ledger capital in or out of the holding moves a liquid Account through a Capital Movement. Polish UI copy: „lokata”.
_Avoid_: Deposit (unqualified), Savings, Investment, Lokata in code, Account (for this holding)

**Bank**:
The named institution of a bank Account or Term Deposit: Alior, BNP Paribas, ING, Lokaty Facto, mBank, Pekao, PKO BP, Revolut, Santander, or Other. Required on bank Accounts and Term Deposits, absent on cash Accounts. Distinct from Import format (file layout of an export); Other here is not Import format Other. A named Bank does not imply a matching Import format.
_Avoid_: Import format, Connector, Integration, BankAccount (as this field’s name)

**Budget**:
The User’s plan and execution for one calendar year. At most one Budget per User per year. Lives in draft, then execution. Year Close does not end execution: later Financial Operations dated in that year remain allowed.
_Avoid_: Year, Period, Wallet

**Initial Plan**:
The planned income amounts and expense limits frozen when execution starts. Immutable afterwards.
_Avoid_: Original budget, Baseline (in UI copy)

**Current Plan**:
The live planned income amounts and expense limits. May change during execution without touching the Initial Plan. Align sets it to max(0, Execution) on that Category (Available Limit becomes 0). That rewrite is live Current Plan for every month of the Budget year; it is not Year Close and not a month-scoped close of Month Remaining. Year Close Aligns every expense Category of that Budget after it posts. The User may later opt in to add an Earmark or unlabeled Reserve into a later Budget’s expense Current Plan; that is a Current Plan edit, not a second movement type.
_Avoid_: Revised budget, Forecast

**Category**:
An income or expense bucket that belongs to one Budget. Income categories have a planned amount; expense categories have a limit. A Category on one Budget is not the same Category as one with the same name on another Budget. Matching several years by the same name and type is a query over those records, not one Category. A Category named by a Cash Expectation on Agenda cannot be deleted or have its type changed. An expense Category is either a Paced Category or a One-off Category. Archive is allowed; archived Categories keep Execution history on that Budget. Dashboard meters and Dashboard plan totals (income, expense, Planned Balance) omit archived Categories. Budget lists them in an archived section and omits them from plan totals unless the User asks to show archived. Month Remaining arithmetic still uses all Paced expense Categories of that Budget, including archived.
_Avoid_: Tag, Label, Envelope, Category template, Category kind, a Category identity that spans years

**Paced Category**:
An expense Category whose leftover Current Plan is read across the remaining calendar months of that Budget year. It has Month Remaining. New expense Categories default to this. Income Categories never have this flag. Polish UI copy: „równomierna”; the Paced pool is „pula równomiernych”.
_Avoid_: Envelope, monthly Category, scheduled Category, Available Limit (for the month figure)

**One-off Category**:
An expense Category excluded from the Paced pool and from Month Remaining. A flag only: no target month. It still has annual Available Limit. Income Categories never have this flag. Polish UI copy: „jednorazowa”.
_Avoid_: Envelope, unbudgeted, unplanned, a Category with a due month

**Execution**:
Booked income and expense amounts against a Category in a Budget that has started execution. Not unpaid Cash Expectations.
_Avoid_: Actuals (in code names; UI may say “wykonanie”), Cash Expectation, Agenda (as this plane)

**Available Limit**:
Current Plan of an expense Category minus booked expenses. Not cash on Accounts. Unpaid Cash Expectations do not reduce it. Not Month Remaining. A positive leftover on any expense Category (Paced or One-off) is a candidate Earmark of a slice of Year Result, not a second pot. A negative leftover is not an Earmark: that overspend already sits inside Year Result.
_Avoid_: Balance, Available funds, Remaining cash, using this figure as Reserve or as an Earmark, Month Remaining

**Month Remaining**:
A signed reading, in Minor units, of leftover Paced Current Plan for one calendar month of a Budget year: one Paced Category, or the Paced pool (all Paced Categories of that Budget). Not stored. A One-off Category has none. Arithmetic is catch-up leftover, not an even split of Current Plan. Polish UI copy: „Pozostało w miesiącu”.
_Avoid_: Available Limit, monthly limit, Envelope remaining, Spendable Liquidity, Year Elapsed

**Year Elapsed**:
The fraction of calendar days of a Budget year that have passed as of an as-of date: day-of-year divided by days in that year. The as-of date is today when the selected Budget is the current calendar year, 31 December of that Budget year when it is past, and 1 January of that Budget year when it is future. A mark on a Category meter, not a money reading. Income Categories and Paced expense Categories may show it; a One-off Category does not. Distinct from Execution as a percent of Current Plan and from Month Remaining. Dashboard always reads the current calendar year’s Budget, so its as-of date is today. Polish UI copy: „Postęp roku”; the Dashboard checkbox always shows the as-of date next to that copy.
_Avoid_: Month Remaining, Year Pace, tempo roku, using percent complete for this mark

**Planned Balance**:
Income Current Plan minus expense Current Plan for one Budget. A plan-plane summary, not cash on Accounts.
_Avoid_: Balance (unqualified), Liquidity, Account balance, Available Limit, Month Result. Polish UI copy: „Bilans”.

**Month Result**:
Execution income minus Execution expense for one calendar month of a Budget year. Booked Financial Operations dated in that month only, all income and expense Categories including archived and One-off. Expense refunds net into the expense figure. Not Planned Balance, not Month Remaining, not unpaid Cash Expectations, not Own Transfer, not Capital Movement.
_Avoid_: Balance (unqualified), Planned Balance, Available Limit, Month Remaining, Liquidity, Year Result. Polish UI copy: „Wynik”.

**Year Result**:
Execution income minus Execution expense for one Budget year. Same inclusions as Month Result, for every date in that year rather than one month. A reading; Year Close may post it into Reserve. Polish UI for this reading: „Wynik roku”; the User’s „zysk” / „ostateczny zysk” means this reading, not Planned Balance. Leftover Available Limit is not added on top of it. At Year Close, Earmarks from positive leftovers total at most max(0, this reading); if it is ≤ 0 there are no Earmarks. Not Planned Balance, not a change in Spendable Liquidity or Total funds.
_Avoid_: Month Result (for this year figure), bare Wynik, Bilans, Planned Balance, leftover Available Limit (as if it were this reading or a second pot), Pozostało, profit, surplus (unqualified), Δ Liquidity, Spendable Liquidity, Reserve (as if this reading were the stock)

**Year Close**:
A User-triggered ritual on a Budget in execution. It computes Year Result, lets the User pick Earmarks from positive leftover Available Limit on any expense Category (sum at most max(0, Year Result); remainder unlabeled), posts Year Result into Reserve, then Aligns every expense Category of that Budget. A later Year Close on the same Budget replaces that Budget’s previous post. It does not lock the year and does not move Liquidity. Not Align by itself. Polish UI copy: noun „Zamknięcie roku”, verb „Zamknij rok”.
_Avoid_: Align, Zakończ, Zakończenie roku, month close, period lock, year-end freeze, closing the books, blokada roku, zamknięcie ksiąg (as if they were this ritual), Month Remaining close

**Reserve**:
A plan-plane stock the User owns, independent of any Budget year and of Account and Term Deposit balances. Holds a signed unlabeled remainder plus Earmarks. Posted from Year Result at Year Close; leftover Available Limit is not added on top. A later negative Year Result reduces unlabeled remainder first (that remainder may go negative) and does not silently consume Earmarks. The User may opt in to add a positive unlabeled amount or an Earmark (in full or in part) into an expense Current Plan on a later Budget (draft or execution; not the Budget just closed). An Earmark may be released back into unlabeled remainder without touching a Budget. Does not move Liquidity. Polish UI copy: „Rezerwa”.
_Avoid_: Account, Term Deposit, a Category named Reserve, a Category identity that spans years, Available Limit (as this stock), Year Result (as if the reading were this stock)

**Earmark**:
A labeled slice of Reserve: an amount in Minor units keyed by expense Category name and type, not by a Category id. Not a Category and not leftover Available Limit after posting. Any expense Category with positive leftover may be a candidate, Paced or One-off. It leaves Reserve when the User opts in to add some or all of it to a later Budget’s expense Current Plan (default: same name and type; the User may pick another expense Category on that Budget). Matching is a query over name and type.
_Avoid_: Category, Available Limit, Reserve (unqualified, for this slice), kategoria rezerw, rezerwa kategorii

**Financial Operation**:
An income, expense, or expense refund: dated (past dates included; not a second type), non-zero amount, description, optional Counterparty, one Account, one Category of matching type. Affects both Account balance and Category Execution. May carry a Raw Statement Description when created by Import. Import-derived description and Counterparty omit payment-instrument numbers from the file row (Polish NRB, IBAN, card PAN including masked); name, merchant, city, amount, and date in that text stay. May settle a Cash Expectation (spawned amount may differ: correction): that Cash Expectation is destroyed and is not a second movement. Interest credited to an Account is Income on that Account and an income Category; it does not name a Term Deposit and is not a Capital Movement.
_Avoid_: Transaction (overloaded with DB transactions), Payment, Transfer (for this type), Term Deposit (as the Account), Capital Movement, Term Deposit interest (as a fourth persisted type), Cash Expectation, archival data / historical dump as a persisted type, Archive (for booked history)

**Expense refund**:
A negative amount on an expense Category (Financial Operation or Cash Expectation), not Income and not a fourth persisted type. As a Financial Operation it restores Account balance and Available Limit, so Execution is net spend; as a Cash Expectation it does not move Liquidity, Execution, or Available Limit.
_Avoid_: Income (for money returned from a purchase), refund as a fourth persisted type

**Cash Expectation**:
A known income, expense, or expense refund that is not yet a Financial Operation: date (future, today, or already overdue; not before the Account opening date), non-zero amount, description, Account, Category of matching type, optional Counterparty. Overdue after create is derived (date before today), not a stored status. The date’s calendar year must equal the Category’s Budget year; that Budget may be draft or execution; a year with no Budget is invalid. One record is one movement, not a series; it sits on Agenda and does not move Liquidity, Execution, Available Limit, or Planned Balance. While unpaid the User may edit (same fields and validation as create, including income ↔ expense refund, not ordinary expense ↔ income) or delete; delete does not move Liquidity or Execution. There is no cancel or skip state and no Financial Operation link. Settlement leaves exactly one Financial Operation (a new one spawned from this record, or an existing one that already is that movement); this record is destroyed, not converted in place and not kept for unpay. A spawned amount that differs from this record is a correction, not a remaining balance: this record still dies in full; Liquidity and Execution move only by that Financial Operation’s amount; nothing is auto-created for a leftover. Attach and Import still require a Settlement candidate (exact amount); to attach a different-amount Financial Operation the User edits this record to that amount first. A leftover still-expected amount is a new Cash Expectation. Edit or delete of that Financial Operation does not recreate a Cash Expectation. An Import preview row or an already-saved Financial Operation is offered for that attach or opt-in only when it is a Settlement candidate.
_Avoid_: Obligation, Payment, Transaction, Unpaid, Pending, Schedule, Scheduled, Plan, Planned, Forecast, Future Operation, Upcoming, Accrual, Due, due date, Payable, Receivable, Reminder, Commitment, Movement (as this record), Operation (as this record), operation date (for this record’s date), Event (as this record), Cash Event, Expectation (unqualified), Financial Operation, Own Transfer, Capital Movement, status (on this record), unpay, skip, cancel (as a persisted state), partial pay (as a verb), remaining balance (on this record), installment

**Counterparty**:
The other party of a Financial Operation or a Cash Expectation: the payer on income, the payee on expense or expense refund. Optional. Absent on Own Transfer. An Import-derived Counterparty omits payment-instrument numbers; if nothing else remains, it is absent.
_Avoid_: Subject, Payee, Payer, Party (as this field’s name)

**Raw Statement Description**:
Immutable text from the Import file row that created a Financial Operation, a Capital Movement, or an Import-born Account on an Own Transfer. Keeps payment-instrument numbers even when Import omits them from description and Counterparty. Null when that record or leg was created by hand; an Own Transfer may hold two after a cross-Import pair. A Capital Movement holds at most one (the liquid Account). Not edited when description or Counterparty later change.
_Avoid_: raw_description, statement_description, Subject

**Own Transfer**:
A move of money between two of the User’s Accounts. No Category. Does not affect Execution. ATM withdrawal is an Own Transfer from bank to cash. An Import row becomes this record when the User classifies it and picks the other Account; both Accounts move on that commit — a later file is not required for it to exist. Cash is an Account like any other for a cross-Import pair. May make the source Account’s current balance negative. May carry a Raw Statement Description per Import-born Account (none, one, or two). A cross-Import pair leaves this single record: it replaces a first-leg Financial Operation and does not move balances twice.
_Avoid_: Internal transfer, Move (as the type name), one-sided Own Transfer, pair (as a record type), Term Deposit (as an endpoint), Capital Movement

**Capital Movement**:
A move of capital between one of the User’s liquid Accounts and one Term Deposit. Not a Financial Operation and not an Own Transfer. No Category. Does not affect Execution. One record changes both holdings by the same positive, non-zero amount, either Account → Term Deposit or Term Deposit → Account. Both endpoints must already exist; cash is a valid Account. A Term Deposit may have many Capital Movements; each names its own Account, so return need not use the same Account as open. Date cannot be in the future, before the Account opening date, or before the Term Deposit opening date. Hand-entry and Import create this type. Import direction follows the signed amount on the Account (debit is Account → Term Deposit; credit is Term Deposit → Account); the User picks an existing Term Deposit, not a Category. May carry one Raw Statement Description when Import-born on that Account. Create, edit, or delete may make the Account and/or the Term Deposit current balance negative; that is Negative Balance Warning, not a block.
_Avoid_: Own Transfer (for this), Transfer, Allocation, Term Deposit transfer, open and return as separate record types

**Negative Balance Warning**:
A non-blocking notice before saving a Financial Operation, Own Transfer, Capital Movement, or a Term Deposit opening snapshot that would make an Account’s or a Term Deposit’s current balance negative. Distinct from exceeding an Available Limit. A Capital Movement that would take both sides below zero shows two notices, one per side. Opening-snapshot save does not change an Account, so it is one Term Deposit notice. After save, every surface that shows that holding’s current balance marks the negative figure (same idea as an Account).
_Avoid_: Overdraft, insufficient funds (both imply a block), a second warning type for Term Deposit

**Liquidity**:
The plane of the User’s holdings as money, distinct from Budget Execution and from Agenda. Not a single unqualified total: the figures on this plane are Spendable Liquidity and Total funds.
_Avoid_: Net worth, Wealth, Portfolio, using Liquidity as one number without Spendable Liquidity or Total funds, Agenda (as this plane)

**Spendable Liquidity**:
The sum of Account current balances (bank and cash). Money not in Term Deposits. Does not include Term Deposit balances.
_Avoid_: Available Limit, Total funds, Liquidity (unqualified), Łączne środki (for this number)

**Total funds**:
Spendable Liquidity plus the current balances of Term Deposits. A Capital Movement into a Term Deposit does not change Total funds; it moves money from Spendable Liquidity onto that Term Deposit.
_Avoid_: Liquidity (unqualified), Spendable Liquidity, Net worth, Wealth

**Agenda**:
The plane of Cash Expectations that are not yet a Financial Operation, beside Liquidity and Execution. A calendar of those records, not a total and not a liquidity forecast. After settlement or delete the Cash Expectation is not on this plane. There is no cancelled or paid list.
_Avoid_: Horizon, Outlook, Calendar, Schedule, Forecast, Pipeline, Pending, Expectations (as the plane name), Liquidity (for this plane), Execution (for this plane), unpay

**Minor unit**:
An integer count of a currency’s ISO 4217 subunit. Every stored amount is a signed bigint of Minor units, never a decimal of major units. MVP amounts are PLN (scale 2); other currencies are not in the schema yet, so column and code names stay currency-neutral (`*_minor_units`).
_Avoid_: grosze (as a column or code name), cents (as a column name), złotówki in storage, float amounts

**First Run**:
Whether the User has the four start conditions: at least one Account, one Budget, one Category, and one Budget in Execution. Scoped to the User, not to a Budget year. A Cash Expectation and a Term Deposit are not start conditions. The dashboard checklist is the UI for this state (those four rows only); the User may hide that checklist while all four hold.
_Avoid_: Onboarding, wizard, tutorial, First Steps (as a code name; UI copy may say „Pierwsze kroki”)

**Import**:
The controlled creation of Financial Operations, Own Transfers, and Capital Movements from rows in a CSV or XLSX file, after the User chooses an Import format, maps or confirms columns, and reviews a preview. Not a live connection to a bank. Every Import format omits payment-instrument numbers from the live description and Counterparty at parse (Polish NRB, IBAN, card PAN including masked; leftover spaces and blank lines collapse). If description is then empty, it takes the remaining Counterparty; if both are empty the row is invalid, same as a missing description today. Already-committed Import-born live description and Counterparty are brought in line with that omit; Raw Statement Description is not. Hand-entered description and Counterparty are not omitted. Raw Statement Description stays the file row. Preview default type follows the signed amount (credit is Income, debit is Expense); the User must switch type to Capital Movement. One preview row that is capital return plus interest may commit two records in that classification: a Capital Movement (Term Deposit → Account) for the remainder and a Financial Operation (Income) for the interest the User types (and an income Category); those two are not a persisted pair. Hand-entry never creates both from one save. Import does not create a Term Deposit or a Cash Expectation. A preview row may settle an existing Cash Expectation when the User opts in on a Settlement candidate; that commit leaves one Financial Operation, not two.
_Avoid_: CSV Import (as the name of the whole flow), Statement Import, Bank Import, Integration, combined maturity (as a record type)

**Import format**:
A named layout of a bank export file: file type, where the header row is, and which columns are date, description, amount, and optionally Counterparty. MVP ships mBank (CSV), BNP Paribas (XLSX), and Other (manual CSV mapping).
_Avoid_: Integration, Connector, Bank integration, Parser profile, Preset (in domain language; UI may say „format”)

**Import duplicate**:
A preview row that is the same Import row as an already-committed Import-born Financial Operation, Own Transfer, or Capital Movement: same User, Account, date, signed file amount, and Raw Statement Description, frozen at that commit. An Own Transfer may hold two frozen identities, one per Import-born Account, so a later preview row matching either leg is an Import duplicate. A Capital Movement holds one identity, on its liquid Account. When one preview row commits a Capital Movement and an Income Financial Operation, both records receive that same file-row identity (not two identities of the split amounts). Hand-entered records (null Raw Statement Description) are never Import duplicates.
_Avoid_: Transaction duplicate, bank transaction id

**Import pair candidate**:
A later Import row that is the other Account’s view of an existing Import-born Financial Operation or Own Transfer. Distinct from Import duplicate (same Account, same frozen row) and from a Settlement candidate. Not a persisted record type; after the User opts in, commit leaves one Own Transfer. A Capital Movement is never an Import pair candidate and never a first-leg. A selected pair and a settlement opt-in cannot both apply to the same preview row.
_Avoid_: Transaction match, transfer duplicate, link, pair (as a record type)

**Settlement candidate**:
An unpaid Cash Expectation offered on Import preview, or an existing Financial Operation offered when attaching from a Cash Expectation: same User, same Account, same type family (income / expense / expense refund), exact type-aware amount in Minor units, and dates within 31 inclusive calendar days. Import preview uses this Account, file date, signed file amount, and current row type; attach uses the Financial Operation’s live fields. The User opts in; the predicate returns a set; a pick consumes that Cash Expectation in this preview. Description, Counterparty, and Category do not gate candidacy. Own Transfer and Capital Movement never qualify.
_Avoid_: auto-match, suggested pair, similar transaction, Import pair candidate, Import duplicate
