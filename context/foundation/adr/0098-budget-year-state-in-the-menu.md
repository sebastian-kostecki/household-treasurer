# The year menu shows the state of each year

ADR-0087 made „Rok budżetu” a menu button with a flat list of years. The list says nothing about a year: the user cannot tell the current calendar year from a draft Budget, and with 2020–2027 the old years crowd the ones in use. The Atrament `Sidebar` card (variant B) answers with state marks, groups, a caption under „Dodaj rok” and a dark menu. This ADR extends ADR-0087; everything it does not mention (empty state, pending state, the shared dialog, `budgetYearMenu` as the one source of logic) stays.

Rule. The state of a year has two independent marks. „bieżący” means the calendar year (`new Date().getFullYear()` in the browser, as `createYear` does today). „szkic” means `BudgetPhase::Draft`. Both are shown together when both hold; a year in execution that is not the current one has no mark.

Button. The selected year is set in JetBrains Mono (`font-mono`). The pills of the selected year sit before the chevron, right-aligned: „bieżący” with an outline of `sidebar-border` and text of `sidebar-foreground`, „szkic” in `warning` on `warning-soft`. Classes `.sidebar-year-*` in `resources/css/app.css`, no new tokens.

Menu. With 4 or more years the radio group has two headings, „Ostatnie” (the 3 newest years) and „Starsze” (the rest), both inside one `DropdownMenuRadioGroup` so one value covers both and the keyboard moves through them without a break. With 3 years or fewer the list is flat and has no headings. Each row keeps the check on the selected year (background `sidebar-accent`), a „szkic” pill for a draft and a „bieżący” note for the current year. Under the separator „Dodaj rok N” carries the caption „szkic z planem roku N−1”: N is the highest year + 1 and the plan source is the newest earlier Budget, always N−1 (ADR-0068), so the caption is always true. In the empty state there is no source and no caption. The menu is dark like the ⌘K palette: a class on `DropdownMenuContent` (background `sidebar`, text `sidebar-strong`, border `sidebar-border`, `shadow-dialog`), never an edit of `components/ui`.

Accessibility. Names stay as in ADR-0087: the button „Rok budżetu 2026” (`aria-labelledby` of the label and the year), the radio item „2026”, the item „Dodaj budżet na rok 2028” (`aria-label`). The state marks and the caption are descriptions: the pills and notes of a row sit in an `aria-hidden` element with an `id` that the item names in `aria-describedby`; the pills of the button are described by `aria-describedby` on their wrapper; the caption is a `small` with an `id` in the `aria-describedby` of „Dodaj rok”.

Data. `shell.budgetYears` is `list<{year, phase}>` ascending by year, with `phase` `'draft' | 'execution'` (`BudgetYearEntry` in `resources/js/types/shell.ts`). This replaces the sentence of ADR-0087 that `shell.budgetYears` does not change. `shell.budgetYear` is unchanged, and so is `SelectedBudgetYear::resolve` (ADR-0075). `budgetYearMenu` returns the groups, the state of each year, the selected entry, `createYear` and its source year; the command palette still reads only `createYear`.

Consequences. The prop changes on both sides (PHP array and `defineProps<>` type) in one commit with an `assertInertia` test. Consumers that only need the year numbers (`CreateBudgetDialog`, `Dashboard`) map the entries. The kitchen sink at `/dev/ui/sidebar` shows one current year in execution, a selected draft that is not current, a current year that is a draft, 3 years or fewer with the open menu and no headings, 2020–2027 with the open menu and groups, no Budget and pending. The landing preview (ADR-0057) draws the static button with the „bieżący” pill. The palette item „Dodaj rok budżetu” gets no caption.

We rejected:

- a separate `draftBudgetYears` list (two lists to reconcile, and every new state would be one more list);
- „draft wins” on the button or everywhere (the card treats the states as independent, and in January the current year would vanish behind „szkic”);
- a permanent „Ostatnie” heading with 3 years or fewer (a heading that separates nothing);
- the state in the accessible name (changes the name contract of ADR-0087 and the E2E helper `tests/e2e/support/budget-year.ts`).
