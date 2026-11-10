# Period Report: how a month or a year is read by group

S-28 (FR-014, FR-015) adds the Period Report, the surface that ADR-0033 and ADR-0037 promised for Month Result. ADR-0063 puts Categories on one list across years and ADR-0069 adds Category Groups and their order. This ADR fixes what a report row is and how it is summed and ordered, before the code. It adds to ADR-0063 and ADR-0069 and changes none of their clauses.

Source. Amounts are Execution read from `financial_operations` through `MonthCategoryActuals`, for one month or for all twelve months of the Budget year. Refunds are negative expense amounts and count net; Own Transfer and Capital Movement are not in the sums. A month's result equals Month Result, a year's result equals Year Result, and a Category's year amount equals the Execution of its Budget Line. A test proves it on the write routes, so a mismatch is a bug, not a second definition.

Rows. A Category has a row when its Budget Line has at least one Financial Operation in the period, also when the net is 0 after a refund, and also when the Category or its line is archived or one-off. A Category without an operation in the period has no row. A group has a row when it has at least one such Category.

Groups and archive. A group sums all its Categories that have a row, archived ones included. An archived Category stands in its group (the current `category_group_id`, as for every report: moving a Category changes every report, ADR-0063) and is marked „zarchiwizowana”; the mark follows `archived_at` of the Budget Line of the period. This differs on purpose from the group row in the Budget, which sums only active lines and keeps archived lines in a separate section (ADR-0069). The totals per type agree: the report total equals the Budget „Razem” with „Pokaż zarchiwizowane” on.

Order. Groups and Categories without a group stand in the order of the list, the Categories of a group in the order of the list inside it, computed by `CategoryListOrder::tree` per type. The report never sorts by amount.

Share. Only expense rows have a share, for a group and for each of its Categories, against the period's expense total (the „Wydatki” figure, net of refunds). The share is an integer in tenths of a percent, computed with integers only: for a total t > 0, `sign(a) * intdiv(abs(a) * 2000 + t, 2 * t)`, which rounds half away from zero, each row on its own. A net refund gives a negative share. When the total is not positive every share is null. The shares may not add up to exactly 100,0% because of rounding, so the „Razem” row shows 100,0% only when t > 0. Income rows have no share (FR-015).

We rejected:

- a separate archive section in the report, like the Budget (a group would then show a sum of Categories that are not listed under it, and the report total would depend on a toggle);
- a denominator made of the positive amounts only (the share would not match the „Wydatki” figure on the same page, and a refund would raise the others' shares instead of lowering the total);
- sorting rows by amount (the order would differ from the Budget and the Categories list and would move when an operation is booked).
