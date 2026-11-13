# The Budget year is a menu button, not segments

ADR-0055 turned „Rok budżetu” in the sidebar into segments: every year with a Budget in ascending order plus one „<highest + 1> +”, three per row, and accepted that the list grows without limit. ADR-0053 had predicted the cost. With the years 2022–2027 the segments take three rows of the sidebar, the old years are rarely used, and „+” sits among the years as if it were one of them. This ADR replaces the „Year in segments” paragraph of ADR-0055 and the segments rule of `.ai/rules/js-sidebar.md`.

Rule. Under the `SidebarGroupLabel` „Rok budżetu” the switcher is one full-width menu button that shows the selected year and a chevron (class `.sidebar-year-trigger` on `--sidebar-*` tokens: text `sidebar-strong`, hover and open `sidebar-accent`). It opens a `DropdownMenu` (the pattern of `NavUser`) with a `DropdownMenuRadioGroup` of the years, unique and newest first, the selected one checked, then a `DropdownMenuSeparator` and one item „Dodaj rok N” (icon `Plus`, `aria-label` „Dodaj budżet na rok N”) with N = highest year + 1. The menu separates choosing from creating. The button is named by the label and the year (`aria-labelledby` of both) and carries `aria-busy` while a year switch is pending.

Empty state. With no Budget there is nothing to choose: the menu is replaced by a single button „Dodaj rok <current year>” that opens the new-budget dialog directly.

Pending state. While a year switch runs, the button has `aria-busy` and `aria-disabled`, and neither choosing a year nor „Dodaj rok” does anything; the error toast and the `destination` of Reports and Trend stay as before.

Data. `shell.budgetYears` and `shell.budgetYear` do not change; the dialog is still the one shared instance from `useCreateBudgetDialog`. The logic lives in `lib/budget-year-menu.ts` (`budgetYearMenu`), which the command palette reads for its „Dodaj rok budżetu” year as well. The landing preview draws a static button instead of segments.

Consequences. Replaces `lib/budget-year-segments.ts` and the `.sidebar-year-segments` / `.sidebar-year-segment` classes. The kitchen sink at `/dev/ui/sidebar` shows one year, many years (2022–2027), no Budget, pending and the open menu.

We rejected:

- segments kept with a cap on the visible years (hides the older years behind another control and keeps „+” among them);
- a native `select` (cannot hold „Dodaj rok” under a separator and does not follow the Atrament menu).
