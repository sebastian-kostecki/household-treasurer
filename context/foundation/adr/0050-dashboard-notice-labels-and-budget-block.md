# Dashboard notice labels, the "Budżet <year>" block layout and the Year Elapsed Switch state

ADR-0045 moved the Dashboard back to the `PageDashboard` page of the "C · Atrament i morski" design system (artifact https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA) and left several values to the implementing change. This ADR records them, and the places where the implementation deliberately departs from the card.

Notice labels. A notice card shows the kind label on top, the identity under it in `font-medium` (not the 600 of the card) and the value on the right in the severity colour. The kinds, severities, order, links and the six-card limit of ADR-0032 do not change.

| Kind | Label | Identity | Value on the right |
| --- | --- | --- | --- |
| `negative_account`, `negative_term_deposit` | Saldo ujemne | name of the Account or Term Deposit | balance with sign |
| `month_to_spend_negative` | Do wydania poniżej zera | month | amount with sign |
| `month_item_over_plan` | Ponad plan | `category_name` | amount with sign |
| `one_off_over_limit` | Ponad limit | `category_name` | amount with sign |
| `month_plan_not_approved` | Plan niezatwierdzony | month | none (empty slot) |
| `month_pace_ahead` | Tempo przed upływem dni | month | "31% · upływ dni 17%" |

An amount is formatted by `formatMoney` and keeps its sign. The identity of `month_item_over_plan` and `one_off_over_limit` is `category_name`, the Category the user has to open, not the name of the item. `month_pace_ahead` maps the payload to `percent` and `monthElapsedPercent(elapsed_day, days_in_month)`. `month_plan_not_approved` has no value in the payload, so its right slot stays empty; we do not invent a state word for it. The label, the identity and the value come from one presentation function in `@/lib/dashboard-notice-presentation`, and a new kind adds its label there, not new markup. The "Blisko limitu" kind from `Notice/preview.html` does not enter: the glossary has no such threshold and ADR-0037 keeps the Budget free of "near the limit" states.

The block "Budżet <year>". ADR-0045 calls it one panel. We read "one panel" as one zone: a title row and, as its siblings, the plan measures (`BudgetPlanSummaryStrip`: Planowane dochody, Planowane wydatki, Bilans) and the two tables Dochody and Wydatki, each a `ListSurface`. It is not one frame around them, because that would put a card in a card and force a change of `BudgetPlanSummaryStrip`, which the Budget view uses too. The title row has `h2.section-title` "Budżet <year>" on the left and, on the right, the `Switch` "Postęp roku" and "Otwórz budżet" (`outline`). The plan measures show in both phases, `execution` and `draft`. The Switch shows only in the `execution` phase, because a draft has no meters to carry the mark; a draft shows the title row without the Switch, the plan measures and the empty-state card. With no budget the view stays as it is: the empty-state card alone.

Phone layout. The card has no mobile rules, so the table follows the rule of one markup on `grid-template-areas`, with no second tree. The table switches by its own width, not by the window: each table is a size container (`.category-meter-table`), and from 38rem it shows the five columns with the header row, as in the Dashboard's main column at 1280 px. In a narrow column the "przekroczono" badge wraps under the meter instead of taking a column. A window breakpoint does not work here, because from `lg` the block sits in the narrower main column next to the Konta and Lokaty aside: with `md`, the meter clipped the badge at 1280 px and the category column collapsed at 1024 px. Below 38rem (on the phone, and in the main column at 1024 px) a row shows Kategoria, Pozostało, and the meter with its percentage and, for an exceeded Category, the word "przekroczono". Plan bieżący and Realizacja are hidden as they are now, without captions on the phone. The footer "Kwoty w zł · kreska = postęp roku (n%) · …" stands under the Wydatki table and carries the unit that the cells omit; with the mark hidden it uses the text without the mark.

The Switch state. "Postęp roku" is on by default and shows or hides the Year Elapsed mark on the meters that may carry it. The setting lives in the browser under the key `dashboard-year-elapsed` with the values `'true'` and `'false'`. It is read after the component is mounted, so the first render after SSR and hydration gives the same markup, and it is written only when the user changes the Switch. Reads and writes sit in a helper with try/catch in `@/lib`; a storage error is logged with its cause (`console.warn`, never an empty `catch`), and a failed read leaves the Switch on. The page holds the state and hands it to the block as `v-model`, so the kitchen sink shows the block in both states without touching `localStorage`.

Contradictions in the artifact. `Notice/README.md` describes a card without the value on the right, and `StatPanel/README.md` describes four measures; both contradict ADR-0045. As in ADR-0047, the ADR wins, and the artifact is not edited from the repository.

The change touches no rule in `context/foundation/glossary.md` and no _Avoid_ term, and no Inertia prop.

Supersedes in part ADR-0043: the warning card, the panel with tabs and the four measures, already superseded by ADR-0045. We rejected one frame around the whole block (a card in a card and a change of `BudgetPlanSummaryStrip` for the Budget view), the captions Plan and Realizacja on the phone (the row would grow past one line; the footer carries the unit), a state word for `month_plan_not_approved` (the payload has none, and a made-up word would be a second source of the state) and only the two labels shown on the card (an agent would invent the other five).

Amended by ADR-0066: the `one_off_over_limit` notice (Ponad limit) becomes the over-plan signal of an Even Annual Category against the year; an Irregular Category raises `month_item_over_plan` on a surplus.
