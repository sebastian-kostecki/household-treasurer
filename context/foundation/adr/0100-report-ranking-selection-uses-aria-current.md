# The selected Category of the Report Ranking is marked with `aria-current`, not `aria-selected`

ADR-0097 (the paragraph „Category Panel”, line 23) says that in the Year view a click on a Category name selects it in the Ranking and that the selected row carries `aria-selected`. S-35 (`report-ranking-and-category-panel`) builds that panel and finds that the attribute cannot be used as written. This ADR narrows ADR-0097 on that one point and changes nothing else in it: the Ranking, the panel, the colours and the sort stay as ADR-0097 decided, and its text is not edited.

Why not `aria-selected`. The attribute is supported only on the roles `row` (inside a `grid` or `treegrid`), `option` and `tab`. Each of them belongs to a composite widget (`grid`, `listbox`, `tablist`) that the ARIA Authoring Practices want to be driven by arrow keys, with one tab stop. The Ranking cannot be such a widget: in the Groups mode it holds the group toggles (`aria-expanded` buttons), which may not stand inside a `listbox` or a `tablist`, and its rows are `div`s on a `grid-template-areas` layout (ADR-0084), not table rows. A `grid` would also take the name button out of the natural Tab order that the Month and last-12 Rankings already have.

Rule. In the Year view the name of a Category is a `<button type="button">`, not a link, with `aria-controls` pointing at the id of the Category Panel. The button of the shown Category has `aria-current="true"`, the others have no `aria-current`, and the row of the shown Category has the `secondary` background. The rows stay `div`s without ARIA roles of a table, a grid or a list box, and the Tab order is the document order. A row that exists only through the comparison is selectable too. In the Month and the last twelve months the name stays a link to Operations (ADR-0081, S-31), without a panel. In the narrow layout, where the panel opens as a sheet, no row is marked while the sheet is closed. The selection lives in the address as `?category=<id>` and changes with a client visit that keeps the whole address, so it needs no `aria-live`: the panel is a labelled `aside` (or the sheet dialog) that the button controls.

Consequences. `ReportRankingRow` carries `aria-current` and `aria-controls`; `resources/js/ui-contract.test.ts` fails on `aria-selected` in that file. The E2E tests of S-35 assert `aria-current="true"` on the name button. If the Ranking ever becomes a real `listbox` or `grid` with arrow-key navigation, a new ADR returns to `aria-selected`.

Rejected:

- `aria-selected` on the row `div` (an unsupported attribute on a generic element: assistive technology ignores it, and a validator flags it);
- `role="option"` rows in a `listbox` (the group toggles cannot be its children, and it demands arrow-key navigation and a single tab stop);
- `role="grid"` with `role="row"` (the same keyboard model, and it turns the whole Ranking into a table widget for what is a list with one choice);
- `role="tab"` for the names (the panel is not one of several tab panels, and the group toggles cannot sit in a `tablist`);
- `aria-pressed` on the name button (it says „toggled”, a state the user can switch off, while a selection moves from one name to another).
