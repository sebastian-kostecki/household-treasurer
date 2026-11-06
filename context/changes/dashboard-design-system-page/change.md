---
change_id: dashboard-design-system-page
title: Dashboard design system page
status: implementing
created: 2026-11-06
updated: 2026-11-06
archived_at: null
---

## Notes

- View: `resources/js/pages/Dashboard.vue` (with `DashboardStatPanel`, `DashboardExecutionPanel`, `DashboardNotices`, `BudgetPlanSummaryStrip`), kitchen sink `/dev/ui/dashboard`.
- Reference: the `PageDashboard` card in the "C · Atrament i morski" design system artifact https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, per ADR-0045 (three measures, one "Budżet <rok>" panel with two tables and the Switch "Postęp roku", the notice card with the kind label and the value on the right).
- Contract variant: existing design system (Atrament). Tokens in `resources/css/app.css`, components in `resources/js/components/ui/*` (`Switch` already present), guarded by `npm run check:ui`. Pre-audit hard-coded scan: 0 hits in the six view files.
- New ADR for this change: 0050.
- Out of scope: `AccountMarkCompact` (the bank tile already comes from Accounts).
