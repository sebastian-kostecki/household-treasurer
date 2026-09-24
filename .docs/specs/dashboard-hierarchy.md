# Dashboard hierarchy and progressive disclosure

## Problem Statement

Dashboard shows everything at once and with equal weight: Paced month alerts as a tile grid, the First Run checklist, two Liquidity cards, one card per Account and per Term Deposit, then income and expense sections with totals, a bar, up to eight six-column meter rows, a Year Elapsed checkbox, and an order toggle. The User cannot tell in a few seconds whether anything needs attention or how much is left to spend this month. The Paced pool Month Remaining is not shown at all, Planned Balance is sent in the payload but never rendered, One-off overspend and negative holdings never reach the alert area, and the Budget area follows the shell Budget year, so a past or future year mixes today’s Liquidity with another year’s plan and silently drops alerts. On a phone, the meter row collapses into a stack of labels.

## Solution

Give Dashboard one job: “does anything need me now, and how much can I still spend this month”. Always read the current calendar year’s Budget (ADR-0031). Order the page as: First Run checklist (while incomplete), an attention zone of Dashboard notices (only when non-empty), two headline tiles (Paced pool Month Remaining; Spendable Liquidity with Total funds), expense and income sections that each show a summary and the first five Categories in budget order, and a compact holdings list. On desktop the holdings list moves into a right-hand column. Everything deeper is one click away: in-place expand for the first level, links to Budget or Operations for the rest.

Dashboard notice is a UI term, not a glossary term. Cash Expectation notices (ADR-0019) are out of scope here; the Agenda spec adds them as another notice kind.

## User Stories

1. As a User, I want Dashboard to always show the current calendar year’s Budget, whatever year the shell switcher is on, so that today’s Liquidity and alerts never mix with another year’s plan.
2. As a User on Dashboard, I want the Budget area labelled with the year it shows, so that the visible shell year control does not mislead me.
3. As a User clicking “Otwórz budżet” on Dashboard while the shell is on another year, I want the shell year to switch to the current year before Budget opens, so that I land on the plan I was just reading.
4. As a User with an incomplete First Run, I want the checklist first on the page, so that the start path is the first thing I see.
5. As a User whose First Run is complete but whose checklist is not yet hidden, I want the checklist collapsed to one line with a hide action, so that it stops taking space.
6. As a User, I want one attention zone listing what needs me, and no zone at all when nothing does, so that its presence is itself the signal.
7. As a User, I want an Account or Term Deposit with a negative current balance listed in the attention zone, so that a real cash problem is never buried in the holdings list.
8. As a User, I want every Paced Category with negative Month Remaining for the current month listed in the attention zone, most negative first, so that I see where this month’s allowance is already gone.
9. As a User, I want every One-off Category with negative Available Limit listed in the attention zone, so that overspend on a Category with no Month Remaining still reaches me.
10. As a User, I want the attention zone ordered negative holdings, then Paced Month Remaining, then One-off Available Limit, so that cash problems come before plan problems.
11. As a User, I want the attention zone to show three rows and a “Pokaż wszystkie (n)” control, where n is the total number of notices (the same count as the heading), so that a bad month does not push everything else off screen.
12. As a User clicking a Paced notice, I want Operations filtered to that Category and the current month, so that I see what spent the allowance.
13. As a User clicking a One-off notice, I want Operations filtered to that Category and the current year, so that I see what overspent the limit.
14. As a User clicking a negative Account notice, I want Operations filtered to that Account; for a negative Term Deposit, the Term Deposits list, so that each notice leads to its cause.
15. As a User, I want a headline tile with the Paced pool Month Remaining for the current month, labelled with the month name and marked when negative, so that “how much can I still spend” is one number.
16. As a User whose current year has no Budget in execution or no non-archived Paced Category, I want the Month Remaining tile absent and the Liquidity tile full width, so that I never see a meaningless zero.
17. As a User, I want a headline tile with Spendable Liquidity and Total funds as its second line, so that cash on Accounts and cash including Term Deposits stay distinct but together.
18. As a User on a phone, I want the headline tiles stacked, and side by side from a small tablet width up, so that large amounts are never truncated.
19. As a User, I want the expense section before the income section, so that the spending question is answered first.
20. As a User, I want the expense section to show its summary and the first five Categories in budget order, with the rest behind an in-place expand and a link to Budget below, so that the section stays short and matches the Budget page.
21. As a User, I want the income section to show its summary and the first five Categories in budget order, with the rest behind an in-place expand and a link to Budget below, matching the expense section.
22. As a User, I want Planned Balance shown with the Budget area’s plan totals, so that Dashboard plan totals match the glossary.
23. As a User on a phone, I want each Category row on two lines (Category and remaining amount; bar with percent and Year Elapsed mark), with Current Plan and Execution hidden below the desktop row width, so that the row is readable at 375 px.
24. As a User whose current year has no Budget, I want an empty state naming that year which opens a dialog to create that year’s Budget, while notices and holdings still work, so that a missing plan does not blank the page.
25. As a User whose current year’s Budget is still a draft, I want only its plan totals with Planned Balance, a draft badge, and a link to Budget to start execution, so that I do not see empty meters.
26. As a User, I want holdings as a compact list (bank mark, name, balance) grouped into “Konta” and “Lokaty” with a total per group, so that I scan balances without a wall of cards.
27. As a User on desktop, I want the holdings list always expanded in a right-hand column beside the Budget area, so that both planes are visible at once.
28. As a User on a phone, I want the holdings list collapsed to its heading with an item count, and the expand state remembered on this device, so that it does not push the Budget area down every visit.
29. As a User, I want the attention zone expand and Category expands to reset on every visit, so that Dashboard always opens in the same short form. Income and expense Categories stay in budget order, with no order toggle.

## Implementation Decisions

- Current year: `DashboardController@show` resolves the Budget for `CarbonImmutable::today('UTC')->year`, not `SelectedBudgetYear::resolve`. Keep the shared shell year untouched. Send that year in the payload (for example `budget_year`) even when no Budget exists, so the empty state and label can name it. Year Elapsed as-of is then always today; keep `YearElapsed::asOfDate` as is (the glossary rule stays for other surfaces).
- “Otwórz budżet”: post `budget.select-year` with the current year, then visit `budget`, the same move ADR-0019 describes for Agenda. When the current year has no Budget, the empty state opens `CreateBudgetDialog` with that year suggested. `select-year` only accepts existing years, so it stays on “Otwórz budżet” when a Budget exists.
- Notices payload: replace `pacedCategoryMonthAlerts` with one ordered `dashboardNotices` list built server-side in a Support class next to `DashboardPacedCategoryMonthAlerts` (rename or wrap it; do not duplicate the Month Remaining arithmetic). Each item carries a `kind` (`negative_account`, `negative_term_deposit`, `paced_month_remaining`, `one_off_over_limit`), display fields (name, icon/colour or bank mark), the signed amount in Minor units, and enough to build its link. Order: negative Accounts, negative Term Deposits, Paced (most negative first, then budget order), One-off (most negative Available Limit first, then budget order). Only non-archived Accounts, Term Deposits, and Categories produce notices, matching what the holdings list and meters show. Paced and One-off notices exist only when the current year’s Budget is in execution.
- Notice links (Wayfinder): Paced → `operations` with `category_name`, `category_type=expense`, `date_from`/`date_to` for the current month; One-off → same with the current year’s range; Account → `operations` with `account_id`; Term Deposit → `term-deposits.index`. Verify at implementation that Operations with those filters shows the current year regardless of shell year.
- Attention zone UI: rework `DashboardPacedMonthAlerts.vue` into a generic notices list on `ListSurface` rows (icon, label, amount), not a card grid. Heading with the total count. Three visible rows, in-place expand for the rest, not persisted. “Pokaż wszystkie (n)” uses that same total, not the hidden-row count. Amounts use `formatMoney` and destructive colour. A Paced row shows “Pozostało w miesiącu” under the amount; a One-off row shows “Ponad limit”. A negative Account or Term Deposit has no extra text label; the bank mark identifies it. (Superseded by ADR-0032: severity pills replace the heading count, and negative holdings show “Saldo ujemne”.)
- Headline tiles: add `paced_pool_month_remaining_minor_units` (from `BudgetMonthReadings::forBudget` for the current month; null when the tile should be absent) and the current month for the label. Polish copy is “:month — pozostało”, with the month name in the nominative. Spendable Liquidity and Total funds reuse existing props in one tile. Grid: one column below `sm`, two from `sm`; Liquidity tile spans both when Month Remaining is null.
- Page grid: single column on mobile. From `lg`, a two-column grid (about 2/3 and 1/3): left holds checklist, attention zone, headline tiles, expense and income sections; right holds holdings. Keep the OpenShip surface recipe (ADR-0001 / ADR-0003): grey floor, white cards, no resting shadows.
- First Run checklist: incomplete renders first as today; complete-but-not-dismissed renders one line with the existing dismiss form.
- Budget area: header shows the year and, for a draft, a badge. Reuse `BudgetPlanSummaryStrip.vue` (or its markup) for income/expense Current Plan and Planned Balance from the existing `planSummary` prop. No Budget → empty state. Draft → summary only, no Category rows, link to Budget.
- Category sections: expense before income. Dashboard-specific default visible count is 5 for both expense and income, instead of the shared `VISIBLE_CATEGORY_LIMIT` of 8. Both lists keep the Budget sequence from `Category::ordered()`; Dashboard has no order toggle. Year Elapsed checkbox stays with the section header and keeps its `localStorage` persistence. Below the list, a link to Budget.
- Mobile Category row: change the `category-meter-row` layout below `md` to two lines (chip + remaining; bar + percent + Year Elapsed mark) and hide plan/execution cells there. Keep the desktop row as today. Budget page rows must not change if they share the class; scope the change to Dashboard if they do.
- Holdings list: replace the Account and Term Deposit card grids with `ListSurface` rows using `AccountIdentityBlock` (bank mark, name, balance), two groups with group totals, negative balances marked as today. When Term Deposits exist and Accounts do not, the Konta group still shows, with “Brak kont” and no total. When there are no Term Deposits, omit the Lokaty group. When neither exists, keep the single empty card. On mobile the list is collapsible with its expand state in `localStorage` (for example `dashboard-holdings-expanded`); from `lg` always expanded.
- No deferred props; all Dashboard data stays in the single Inertia response.
- Polish copy in `lang/pl/ui.php` under `dashboard.*`: attention zone heading, show-all label, notice labels per kind, Month Remaining tile label with month, year label, draft badge, empty state, “Otwórz budżet”, holdings group titles. Glossary terms in code stay English.

## Testing Decisions

- Feature tests in `tests/Feature/DashboardTest.php`: HTTP seam, `actingAs`, `get(route('dashboard'))`, `assertInertia` on props, factories, `RefreshDatabase`. Freeze time where the current year or month matters. Do not assert Vue internals, Tailwind, or screenshots; no `pest-plugin-browser`.
- Cover: Budget area reads the current calendar year when the session shell year is another existing year; current year with no Budget sends null Budget and the current year; draft Budget sends plan summary and no pool Month Remaining; pool Month Remaining present for execution with a non-archived Paced Category, null otherwise.
- Cover notices: negative non-archived Account and Term Deposit present, archived absent; Paced negative Month Remaining present, zero or positive absent; One-off over limit present, Paced over limit not duplicated as a One-off notice; archived Categories absent; order across kinds and within kinds; no Paced or One-off notices for a draft or when no current-year Budget exists.
- Existing Paced alert tests move to the notices payload; keep their scenarios.
- Do not unit-test the notice ordering separately if the HTTP payload already shows it.

## Out of Scope

- Cash Expectation notices and Agenda (owned by `agenda-cash-expectations.md` and ADR-0019); the notice list only needs to accept another kind later.
- Month Result, Year Result, Reserve, or Year Close on Dashboard.
- Income behind Year Elapsed as a notice.
- Account or Category detail pages.
- Charts, month grids, or a second Budget workspace on Dashboard.
- Changing the shell year control or its behaviour on other pages.
- Deferred props or skeleton loading.

## Further Notes

- Glossary: `CONTEXT.md` (Spendable Liquidity, Total funds, Month Remaining, Available Limit, Planned Balance, Year Elapsed, First Run). Decision: `.docs/adr/0031-dashboard-always-shows-current-calendar-year.md`. Related: ADR-0019, ADR-0024.
- Suggested slices: (1) current-year rule, headline tiles, Planned Balance, page order and grid; (2) attention zone with the four notice kinds and links; (3) compact holdings list; (4) Category section defaults and the two-line mobile row.
