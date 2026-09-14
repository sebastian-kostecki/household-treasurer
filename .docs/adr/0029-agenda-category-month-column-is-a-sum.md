# Agenda Category month column on Budget is one sum, not a list

The Budget month cell for an Agenda Category shows a single figure: the sum of unpaid Cash Expectations and booked Financial Operations whose own date falls in the viewed month (same membership as ADR 0027). An empty month is zero on Budget. Row detail (expected vs booked, dates, descriptions, links) stays on Agenda and the operations list. Dashboard month view already used this sum; Budget matched it for density on a twelve-month grid.

We superseded ADR 0027’s per-row list in the Budget cell. Membership, overdue non-roll, and create/edit/settle on Agenda are unchanged.
