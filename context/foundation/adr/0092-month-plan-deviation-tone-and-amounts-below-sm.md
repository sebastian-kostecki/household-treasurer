# Month Plan: deviation tone by status, and the four amounts in a 2 x 2 grid below `sm`

The 1.4.3 audit (`context/changes/v1-4-3-fixes/research.md`, C5) found two things in the Month Plan. At 390 px a five- or six-figure amount (`-123 299,98`) breaks across lines in a column of about 75 px and the plan field cuts `123456,78`. And the Deviation is coloured only when it is over plan (`destructive`) or when income is above plan (`success`), so an expense below plan, the favourable case, has no colour while the Deviation of an expense above plan is red.

Deviation tone. The label "Deviation" stays and the glossary stays; the sign of the value stays actual minus plan (`MonthItemDeviation`). The tone follows the benefit, not the sign, and one module (`resources/js/lib/month-plan-deviation-tone.ts`) gives it to the row and to the summary card. The row is read from the top, the first matching case wins:

| Case (row) | Tone |
| --- | --- |
| no reading (`deviation === null`) | `muted-foreground` |
| archived Budget Line | none |
| over plan (also an annual Category over plan for the year with a negative Deviation of the month, and an irregular Category with a surplus) | `destructive` |
| expense, not over plan, Deviation `< 0` | `success` |
| expense, not over plan, Deviation `>= 0` (for example an annual Category with +21 667) | none |
| income, Deviation `> 0` | `success` |
| income, Deviation `<= 0` | none |

An archived line has no tone even when it is over plan. Until now it was red because the old helper ignored `is_archived`; the change is intended and follows the rule of `design-system.md` that an archived row is entirely `muted-foreground` without status colours.

Summary card (the past month). The same rule of benefit: for an expense a negative difference is `success` and a positive one `destructive`; for income and for put-aside a positive difference is `success` and a negative one `destructive`; `0` has no tone. `success` and `destructive` gain this meaning in the colour table of `design-system.md`; no token is added, and the amount of an expense stays `foreground`.

Layout. Below `sm` the four amounts (Approved, Plan, Actual, Deviation) stand in two columns, Approved | Plan and Actual | Deviation, from `sm` to `xl` in four columns, and from `xl` the desktop layout is unchanged. The label line stands above the value in all four cells below `xl`; the arrow of ADR-0060 and the `MonthPlanMatchActual` control of ADR-0062 stay in the label lines. The group row and the put-aside row, which have fewer amounts, get their own grid areas below `sm`. The switch follows the viewport (`sm` is a media query), so the kitchen sink `/dev/month-plan` shows the 2×2 layout in a 390 px window, not in a frame inside a wide page. This replaces in ADR-0060 the sentence "the four-column layout fits at 390 px, so the 2 x 2 grid fallback ... was not needed": it does not fit with amounts above 100 000.

Plan field. Every value the page writes into the Plan field (the start value, the fallback, "Ustaw plan na wydatki", the put-aside amount) goes through one formatter with the thousands separator (`8 500,00`). Text typed by hand stays as typed. The parser already removes white space, so nothing changes in saving.

We rejected:

- renaming the label to "left / exceeded" (the glossary term is "Deviation", and a changed term starts with the glossary);
- colouring by sign (`deviation < 0` red would paint an expense below plan as a problem);
- a new token for a favourable Deviation (it duplicates `success`);
- shrinking the amounts with a smaller font (the amounts would differ from the neighbouring tables, ADR-0058);
- a horizontal scroll of the row (a row is read as a whole, and the status stands in its last column);
- reformatting the plan text on blur (a typed "12000" is the User's text until it is saved).

Supersedes in part ADR-0060: the sentence on the four-column layout at 390 px. It changes no rule in `context/foundation/glossary.md` and no _Avoid_ term.
