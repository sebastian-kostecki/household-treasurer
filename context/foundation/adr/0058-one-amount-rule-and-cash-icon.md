# One amount rule for every table, and a Coins icon for cash

Amounts in tables followed three policies. The Budget, the Month Plan and the Dashboard show a number without the unit and say "Kwoty w zł" in the panel footer (ADR-0037). Operations (ADR-0039), Accounts (ADR-0044), Term Deposits (ADR-0051) and the import preview (ADR-0052) each carried "zł" in every cell under an exception to ADR-0037. A few rows had "zł" with no ADR behind it at all: the plan and available-limit captions of `MonthPlanItemRow.vue`, the rows of `MonthYearSummary.vue` and the annual line of `MonthSummaryCard.vue`; and `DashboardHoldings.vue` had cells without the unit and no footer. The same amount therefore looked different from view to view, and an agent had to remember four exceptions. From now on there is one rule and no exception.

A "table" is every list in `ListSurface` whose rows stand on a grid: the cells, the day rows, the sum rows and the captions inside a row (available limit, annual line, the annual summary of `MonthSummaryCard`). An amount in a table goes through `formatMoneyAmount`, or through the signed variant without the unit, in the `amount` style (`amount-sum` in a sum row), and the panel footer says "Kwoty w zł". The formatters that returned "zł" for rows, `formatFinancialOperationAmount` and `formatOperationDaySum`, have no caller outside table rows, so they now return the number without the unit and no variant with the unit is kept.

The unit footer is a rule for every table, with these placements:

- Operations has the footer in each of the three sections. In own transfers and capital movements, whose footer already explains the section, the unit and the explanation stand in one `meta` line separated by " · ".
- Accounts, Term Deposits, the import preview and `DashboardHoldings` get the footer.
- The footer of the budget execution panel on the Dashboard (`dashboard.execution_footer*`) already carries the unit and stays.

"zł" stays where an amount is not a table cell: in measures (`StatPanel`), in dialogs and the `money` variant of `FormField`, in alert cards, in the command palette and in running text. Those places have no footer to carry the unit.

Cash. The `AccountAvatar` of a cash Account shows the `Coins` icon everywhere, also when the Account has a `mark_url`, because "zł" is the unit of the amount and not a mark of the Account, and with the unit leaving the cells it would read as a stray unit in a tile. `accountShortMark` returns no text for cash. The other fallbacks of ADR-0054 stay: the short bank mark, `Building2` for the bank `other` or no bank.

This supersedes in part ADR-0039 ("zł" in each amount and no "Kwoty w zł" footer in Operations), ADR-0044 ("zł" in each balance of Accounts), ADR-0051 ("zł" in each balance of Term Deposits), ADR-0052 ("zł" in the cell of the import preview, the fourth exception to ADR-0037), ADR-0047 (a cash Account shows "zł") and ADR-0054 (a cash Account shows "zł" instead of `Coins`). ADR-0037 stands as it was written, without the exceptions. It changes no rule in `context/foundation/glossary.md` and no _Avoid_ term, and it adds no token and no Inertia prop.

We rejected "zł" in every cell (it clutters the columns and differs from the Budget, where the rows are the widest) and a footer only in tables with several amount columns (it leaves two policies, and the next table would have to guess which one it belongs to).
