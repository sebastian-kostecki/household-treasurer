# Report charts: shadcn-vue Chart on Unovis

## Status

Accepted (S-28, `period-report-by-group`). Applies to the whole report stream: S-28, S-29 (year over year), S-30 (trend), S-31, S-32.

## Context

`.ai/rules/reports.md` makes a chart library a recorded decision. The Period Report (ADR-0070) needs a first-level bar chart over each table, and S-29 and S-30 will need more bars and lines. The app is Vue 3 with shadcn-vue (`components.json`, style new-york-v4), so the shadcn-vue `chart` component is the library the design system already knows. Its tokens `--chart-1..5` existed with the shadcn defaults (`hsl()` values) and no checked contrast. The design system forbids category colour on bars (it is identity only, in `CategoryChip`).

## Decision

- Library: the shadcn-vue `chart` component, added with the CLI into `resources/js/components/ui/chart/`, on `@unovis/vue` and `@unovis/ts`, both pinned to the exact version `1.7.1` (no caret). It also brings the `cn` package that the generated files import. npm stays the package manager.
- The generated `components/ui/chart/**` is a shadcn primitive: it is not edited for a single screen and is not on the `check:ui` list. Application code that uses it (`ReportBarChart.vue`) takes colours only from tokens, no hex and no Tailwind palette colours.
- Colours: income bars use `--success`, expense bars use `--chart-1`. `--chart-1` now has an Atrament value in hex in both themes (`#0b6f8a` light, `#2fb4d4` dark), at least 3:1 against `card`, `background` and `track` in each theme. `--chart-2..5` are unchanged and unused until S-29 and S-30.
- Category colour never paints bars. It stays in `CategoryChip`.
- A chart is `aria-hidden="true"`. The accessible form of the data is the table next to it, with the same rows in the same order, so a chart never carries data that the table lacks.
- The chart component is loaded with `defineAsyncComponent`, so Unovis is not in the bundle of other pages.
- Bar charts are horizontal, one bar per first-level row (groups and Categories without a group); rows with an amount that is not positive keep their place and have no bar.

## Consequences

- Later report slices reuse the same primitive and add series with `--chart-2..5`, after checking their contrast in both themes and recording it in the "Wykresy" section of the design system.
- Unovis adds a sizeable dependency tree, kept off other pages by the async import.
- Chart values are read from the table by screen readers and keyboard users; tooltips are not part of the contract.

We rejected:

- CSS bars (a `div` per row with a width in %): no axis, no scale shared between rows, and each later chart type would need its own code.
- Hand-drawn SVG: the same work as a library for the first chart and more for trends, with the accessibility and sizing details left to us.
- A ring (donut) chart: it hides the order of the list, makes small shares unreadable and invites one colour per Category, which the design system forbids.
