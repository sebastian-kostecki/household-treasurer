---
paths:
    - app/Support/DashboardNotices.php
---

# Support

## Every Dashboard notice carries a server-decided severity

severity is exceeded or warning (App\Enums\DashboardNoticeSeverity, ADR-0032). `month_plan_not_approved` and `month_pace_ahead` are warning; every other kind is exceeded. The order of appending in DashboardNotices (severity first, then kind) is the contract of the list, so there is no global sort. A new warning kind needs its term and thresholds in CONTEXT.md before code. The UI groups and colours notices by severity only, never by kind.
