---
change_id: reports-view
title: Reports view
status: implemented
created: 2026-11-12
updated: 2026-11-12
archived_at: null
---

## Notes

UI change started from `/10x-ui reports-view`.

- **View:** `resources/js/pages/Reports.vue` (route `reports`), with its `Report*` components; kitchen sink `/dev/ui/reports` (`resources/js/pages/dev/ReportsKitchenSink.vue`, fixtures `resources/js/lib/reports-fixtures.ts`).
- **Token source:** Atrament, `resources/css/app.css` (`:root` / `.dark`, published by `@theme inline`); design notes in `context/foundation/design-system.md`. Contract variant: **existing design system**, extend it, no second palette.
- **Guards already in place:** `npm run check:ui` (`scripts/check-ui-literals.mjs`) and `tests/Unit/ReportsHardcodedUiTest.php` list all report files.

Pre-audit (2026-11-12): hard-coded scan 0 hits in templates; the charges are architecture, states and a shared component:

1. `Reports.vue:125-130`: no-budget and draft states render only `ReportEmptyState` (no `h1`, no period switch); in a draft year the "12 miesięcy" view (ADR-0081, phase-independent) is reachable only by typing `?view=last-12`.
2. `Reports.vue:18`, `ReportBarChart.vue:76-87`, `ReportTrendSummary.vue:8`: async charts without a placeholder push the table down when the chunk loads; the kitchen sink marks loading N/A.
3. `ReportsKitchenSink.vue:328-460` copies the page composition of `Reports.vue:196-272` and already drifts (no `range`, title is a `<p>` with the year title only).
4. `ReportBarChart.vue:76-80`: size constants in script (`3rem` rows, `110px` names) escape `check:ui`; to verify on a screenshot or defer.
