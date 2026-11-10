# Month Remaining is a catch-up reading of leftover Paced Current Plan, not Available Limit

A calendar month of a Budget year can show Month Remaining for Paced expense Categories (one Category, or the Paced pool). Available Limit stays annual Current Plan minus booked Execution. A One-off Category has no Month Remaining and no target month: it is a flag only, live like Current Plan (including copy onto a new Budget). Execution uses leftover catch-up with floor toward −∞; the pool floors summed leftover once, not the sum of per-Category figures. Future months of that year show the current-month allowance, not a tighter live re-pace.

We rejected treating the month figure as Available Limit, an even split of Current Plan, a second monthly Budget, and putting this decision into ADR 0018 (that ADR is unpaid Cash Expectation vs Available Limit). Exact arithmetic stays in the Month Remaining spec tickets.

Superseded in part by ADR-0033: the rejection of a second monthly Budget no longer stands, because a Month Plan now sits beside the annual Budget. Month Remaining stays only for past months without a Month Plan.

Amended by ADR-0066: the Month Remaining pool is Fixed Charge + Running, and “a One-off Category has no Month Remaining” becomes “Even Annual and Irregular have no Month Remaining”; the Paced / One-off flag is replaced by a Rhythm of the list Category.
