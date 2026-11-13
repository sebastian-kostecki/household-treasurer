---
paths:
    - app/Support/DashboardNotices.php
---

# Support

## Every Dashboard notice carries a server-decided severity

severity is exceeded or warning (App\Enums\DashboardNoticeSeverity, ADR-0032). `month_plan_not_approved`, `month_pace_ahead` and `collected_not_covered` are warning (ADR-0090); every other kind is exceeded. The order of appending in DashboardNotices (severity first, then kind) is the contract of the list, so there is no global sort. A new warning kind needs its term and thresholds in context/foundation/glossary.md before code. The UI groups and colours notices by severity only, never by kind.
