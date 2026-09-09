# Household Treasurer

A single-user household budget and liquidity tracker: annual category plans versus real cash on bank and cash accounts. UI is Polish; this glossary is the English domain language used in code and agent docs.

## Language

**User**:
The person who signs in. Owns an isolated data space. Not shared with household members in MVP.
_Avoid_: Account (for the login identity), Customer, Member

**Account**:
A liquid holding the User owns: a bank account or a cash account. Has an opening date, opening balance, and current balance. Independent of any Budget year. A bank Account may have a Bank.
_Avoid_: Wallet, Portfel, BankAccount (unless disambiguating in a narrow type name), “account” for the User

**Bank**:
The named institution of a bank Account: mBank, BNP Paribas, or Other. Required on bank Accounts, absent on cash Accounts. Distinct from Import format (file layout of an export); Other here is not Import format Other.
_Avoid_: Import format, Connector, Integration, BankAccount (as this field’s name)

**Budget**:
The User’s plan and execution for one calendar year. At most one Budget per User per year. Lives in draft, then execution.
_Avoid_: Year, Period, Wallet

**Initial Plan**:
The planned income amounts and expense limits frozen when execution starts. Immutable afterwards.
_Avoid_: Original budget, Baseline (in UI copy)

**Current Plan**:
The live planned income amounts and expense limits. May change during execution without touching the Initial Plan.
_Avoid_: Revised budget, Forecast

**Category**:
An income or expense bucket that belongs to one Budget. Income categories have a planned amount; expense categories have a limit. A Category on one Budget is not the same Category as one with the same name on another Budget.
_Avoid_: Tag, Label, Envelope, Category template, Category kind, a Category identity that spans years

**Execution**:
Booked income and expense amounts against a Category in a Budget that has started execution.
_Avoid_: Actuals (in code names; UI may say “wykonanie”)

**Available Limit**:
Current Plan of an expense Category minus booked expenses. Not cash on Accounts.
_Avoid_: Balance, Available funds, Remaining cash

**Planned Balance**:
Income Current Plan minus expense Current Plan for one Budget. A plan-plane summary, not cash on Accounts.
_Avoid_: Balance (unqualified), Liquidity, Account balance, Available Limit. Polish UI copy: „Bilans”.

**Financial Operation**:
An income, expense, or expense refund: dated, non-zero amount, description, optional Counterparty, one Account, one Category of matching type. Affects both Account balance and Category Execution. May carry a Raw Statement Description when created by Import.
_Avoid_: Transaction (overloaded with DB transactions), Payment, Transfer (for this type)

**Expense refund**:
A Financial Operation on an expense Category with a negative amount. Not Income: money returned from a purchase belongs here, not on an income Category. Restores Account balance and Available Limit, so Execution on that Category is net spend.
_Avoid_: Income (for money returned from a purchase), refund as a fourth persisted type

**Counterparty**:
The other party of a Financial Operation: the payer on income, the payee on expense or expense refund. Optional. Absent on Own Transfer.
_Avoid_: Subject, Payee, Payer, Party (as this field’s name)

**Raw Statement Description**:
Immutable text from the Import file row that created a Financial Operation or an Import-born Account on an Own Transfer. Null when that record or leg was created by hand; an Own Transfer may hold two after a cross-Import pair. Not edited when description or Counterparty later change.
_Avoid_: raw_description, statement_description, Subject

**Own Transfer**:
A move of money between two of the User’s Accounts. No Category. Does not affect Execution. ATM withdrawal is an Own Transfer from bank to cash. An Import row becomes this record when the User classifies it and picks the other Account; both Accounts move on that commit — a later file is not required for it to exist. Cash is an Account like any other for a cross-Import pair. May make the source Account’s current balance negative. May carry a Raw Statement Description per Import-born Account (none, one, or two). A cross-Import pair leaves this single record: it replaces a first-leg Financial Operation and does not move balances twice.
_Avoid_: Internal transfer, Move (as the type name), one-sided Own Transfer, pair (as a record type)

**Negative Balance Warning**:
A non-blocking notice before saving a Financial Operation or Own Transfer that would make an Account’s current balance negative. Distinct from exceeding an Available Limit.
_Avoid_: Overdraft, insufficient funds (both imply a block)

**Liquidity**:
The current balances of Accounts and their sum. A separate plane from Budget Execution.
_Avoid_: Net worth, Wealth, Portfolio

**Minor unit**:
An integer count of a currency’s ISO 4217 subunit. Every stored amount is a signed bigint of Minor units, never a decimal of major units. MVP amounts are PLN (scale 2); other currencies are not in the schema yet, so column and code names stay currency-neutral (`*_minor_units`).
_Avoid_: grosze (as a column or code name), cents (as a column name), złotówki in storage, float amounts

**First Run**:
Whether the User has the four start conditions: at least one Account, one Budget, one Category, and one Budget in Execution. Scoped to the User, not to a Budget year. The dashboard checklist is the UI for this state; the User may hide that checklist while all four hold.
_Avoid_: Onboarding, wizard, tutorial, First Steps (as a code name; UI copy may say „Pierwsze kroki”)

**Import**:
The controlled creation of Financial Operations and Own Transfers from rows in a CSV or XLSX file, after the User chooses an Import format, maps or confirms columns, and reviews a preview. Not a live connection to a bank.
_Avoid_: CSV Import (as the name of the whole flow), Statement Import, Bank Import, Integration

**Import format**:
A named layout of a bank export file: file type, where the header row is, and which columns are date, description, amount, and optionally Counterparty. MVP ships mBank (CSV), BNP Paribas (XLSX), and Other (manual CSV mapping).
_Avoid_: Integration, Connector, Bank integration, Parser profile, Preset (in domain language; UI may say „format”)

**Import duplicate**:
A preview row that is the same Import row as an already-committed Import-born Financial Operation or Own Transfer: same User, Account, date, signed file amount, and Raw Statement Description, frozen at that commit. An Own Transfer may hold two frozen identities, one per Import-born Account, so a later preview row matching either leg is an Import duplicate. Hand-entered records (null Raw Statement Description) are never Import duplicates.
_Avoid_: Transaction duplicate, bank transaction id

**Import pair candidate**:
A later Import row that is the other Account’s view of an existing Import-born Financial Operation or Own Transfer. Distinct from Import duplicate (same Account, same frozen row). Not a persisted record type; after the User opts in, commit leaves one Own Transfer.
_Avoid_: Transaction match, transfer duplicate, link, pair (as a record type)
