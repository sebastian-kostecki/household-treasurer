# The Category description is also shown, read-only, in the Budget row

ADR-0067 says the description of a Category is shown only on „Kategorie”. A Budget row carries the name and nothing that says what the Category is for, and the User reads the description there when deciding on a plan. This ADR replaces only that sentence of ADR-0067 ("shown only on „Kategorie”"); every other rule of ADR-0067 stands.

Rule. The description is shown on „Kategorie” and, read-only, under the name in the Budget row, in the same form as on „Kategorie”: one `meta` line in `text-muted-foreground`, truncated to one line, the full text in `title` (class `.budget-category-description` on the recipe of `.categories-description`). It is still never in a selector (`CategoryChoiceOptions`, `MonthPlanJoinCandidates`), never in the Month Plan or the Dashboard, and it is edited only on „Kategorie”.

Row. The line under the name has up to two parts: „zebrane startowe X” (ADR-0076) and the description, joined by „ · ”. With any part the chip is `lg` with the `#subtitle` slot; without one it stays small. This is the second exception to the small chip of the Budget (ADR-0060), next to ADR-0076. The archived row has the line in the tone of the rest of the row.

Data. Each row of `incomeCategories` and `expenseCategories` carries `description` (`string|null`), read through the eager-loaded `$line->category`; the PHP array shape and the TypeScript row type change in the same commit. The kitchen sink at `/dev/kitchen-sink/budget` shows a description alone, with „zebrane startowe”, a long one and an archived row with one.

Amended: ADR-0067 (the sentence „shown only on „Kategorie””), ADR-0060 (the small chip in the Budget).

We rejected:

- a tooltip on the name only (hidden on touch screens, and the User would not learn that a description exists);
- the description in the plan column or a second line of its own (a third kind of row height next to ADR-0076).
