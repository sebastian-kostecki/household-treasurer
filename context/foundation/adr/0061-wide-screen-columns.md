# Columns that grow on a wide screen

On a wide window the Dashboard and the Budget left empty space: the right column of the Dashboard stayed at 300 px, the amount columns of the execution table were fixed at 5 rem next to a category column and a meter that did not share the rest, and in the Budget only the category column grew (`repeat(4, 6.5rem) minmax(6rem, 8rem)`). There was no rule for `2xl` and no maximum width of the content in the sidebar layout. This ADR lets the columns share a wider window and adds neither.

Aside column. `--aside-width` is `clamp(18.75rem, 22vw, 26rem)`: 300 px at a 1280 px window and at most 416 px. It stays a dimension token without a `.dark` variant.

Weights, not maxima. Columns grow through `fr` weights, not through fixed maxima. A grid first stretches `minmax(a, b)` up to `b` and only then hands the rest to `1fr`, so fixed maxima would take the width from the category column at 1280 px. The proportions below are starting values, to be confirmed on screenshots.

- Execution table of the Dashboard, from 38rem: category `minmax(8rem, 1.4fr)`, three amount columns `minmax(5rem, 0.5fr)` each, meter `minmax(9rem, 1.2fr)`. On a wide screen the meter grows fastest after the category.
- Budget, from `xl`: category `minmax(0, 3fr)`, amount columns `minmax(6.5rem, 1fr)`, meter `minmax(6rem, 1.5fr)`. The draft grid is unchanged.

Requirement. At 1280 px the category column is no narrower than today: about 236 px in the Budget and about 176 px in the execution table. A change that narrows it fails the screenshots.

No `max-width` of the content and no `2xl` breakpoint are added.

This supersedes in part ADR-0043 (the "fixed width" of `--aside-width`). The 38rem breakpoint of the execution table (ADR-0050) stays. It changes no rule in `context/foundation/glossary.md` and no _Avoid_ term, adds no Inertia prop and no colour token.

We rejected a maximum width of the content (it would leave the same empty strips at the sides), a `2xl` breakpoint (a second layout to maintain for a few percent of windows) and fixed maxima on the amount columns (they take the width from the category at 1280 px).
