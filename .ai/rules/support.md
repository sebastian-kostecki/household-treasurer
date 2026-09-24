---
paths:
    - app/Support/DashboardNotices.php
---

# Support

## Every Dashboard notice carries a server-decided severity

severity is exceeded or warning (App\Enums\DashboardNoticeSeverity, ADR-0032). All four current kinds are exceeded. A new warning kind needs its term and thresholds in CONTEXT.md before code. The UI groups and colours notices by severity only, never by kind.
