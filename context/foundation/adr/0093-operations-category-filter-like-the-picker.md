# The Operations category filter is a `CategorySelect` in list order

ADR-0069 left the Operations filter and the command palette flat, while the operation dialogs show Category Groups as labels above their Categories. The filter is a native select with two `optgroup` blocks sorted by name, so the same Categories stand in a different order and without groups, archived Categories look like active ones, and a Polish name is sorted bytewise ("Żywność" after "Transport"). The 1.4.3 audit (C3) asked for the filter to look like the picker.

Rule. The category filter of Operations is `CategorySelect` with:

- the option "Wszystkie kategorie" first (empty choice, like `AccountSelect`);
- two sections below it, Dochody and Wydatki, income first;
- in each section the Categories in the order of the Categories list (`ReportCategoryTree::ofType`, the reader that already feeds `CategoryListOrder::tree()`), with the group labels of ADR-0069 above their Categories and a Category without a group between groups where the list puts it, each as `CategoryChip`;
- the suffix "(zarchiwizowana)" after the name when the Category is archived (below).

The server sends `{id, name, type, icon, color, group, is_archived}` per Category in that order; `name` stays the plain name (the active filter chip and the tests read it) and the component appends the suffix.

Archive (P1). `is_archived` is read from the Budget Line of the selected year; with no year, from the newest line that has operations (the filter lists only Categories with operations, so a newer line without operations, archived or not, does not count); when the Category has no line (a selected Category without operations), from `categories.archived_at`. This is the rule of the reports (ADR-0074). A Category keeps its place in the list, with no separate archive section. Example: "Transport" archived in 2026 is `false` for `year=2025`, `true` for `year=2026` and `true` with no year.

Scope. This replaces in ADR-0069 only the clause that the Operations filter stays flat; the command palette stays flat. The chip of the active filter is unchanged and has no suffix. The component gets optional props (`allOptionLabel`, `archivedSuffix`, `sectionLabels`); without them the dialogs, the merge dialog and the import row render as before.

We rejected:

- keeping the native select and only sorting it (it cannot show a chip, a group label or a suffix);
- a separate archive section at the end (the order of the list is one sequence, ADR-0069, and an archived Category would move away from its neighbours);
- `BudgetLine::scopeOrdered` as the order (it moves archived lines to the end);
- the suffix inside the server `name` (the filter chip and `OperationsFilterTest` expect the plain name);
- making the palette hierarchical too (it is a search, not a pick list).

It changes no rule in `context/foundation/glossary.md` and no _Avoid_ term.
