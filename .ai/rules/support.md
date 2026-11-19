---
paths:
    - app/Support/DashboardNotices.php
    - app/Support/BudgetNotInExecutionNotices.php
---

# Support

## Every Dashboard notice carries a server-decided severity

severity is exceeded or warning (App\Enums\DashboardNoticeSeverity, ADR-0032). `month_plan_not_approved`, `month_pace_ahead`, `collected_not_covered` and `budget_not_in_execution` are warning (ADR-0090, ADR-0108); every other kind is exceeded. The order of appending in DashboardNotices (severity first, then kind; `budget_not_in_execution` comes from `BudgetNotInExecutionNotices` and is merged last) is the contract of the list, so there is no global sort. A new warning kind needs its term and thresholds in context/foundation/glossary.md before code. The UI groups and colours notices by severity only, never by kind.
