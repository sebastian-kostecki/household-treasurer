# Follow-ups from impl-review (2026-11-13)

- `tests/e2e/irregular-collected.spec.ts:55-58` (`openCurrentMonthPlan`): the sidebar year picker is a menu with `menuitemradio` items since `v1-4-0-fixes` (p5), so `getByRole('button', { name: '2026' })` finds nothing and the spec fails there. Not caused by rhythm-hints; the new Rhythm assertion (`:87`) runs before and passes. Fix the step in its own change (see `tests/e2e/sidebar-year-refresh.spec.ts` for how the menu is driven).
