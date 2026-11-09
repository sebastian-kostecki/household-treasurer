# Month Plan change mode raises the plan to the actual amount

An approved Month Plan can be overrun: an expense item has an actual amount above its Current Month Plan, the Dashboard raises an `exceeded` notice (ADR-0033) and the Month Summary shows a deviation. Until now the only way to settle this was to retype every such amount by hand. This ADR lets the user match the plan to the actual amount in change mode, visibly and before saving.

Rule. When the user enters change mode (`startChanging`), each expense row that is in the plan (`in_plan`), not archived, has an amount field and whose actual amount exceeds its Current Month Plan starts with the field equal to the actual amount, provided it is within the limit of 1e12 minor units. An archived item is not raised automatically (the Dashboard ignores archived Categories, so the raise would change nothing), but it may get the button. The raise happens once, on entering change mode.

Button. „Ustaw plan na wydatki” appears in every expense row with an amount field (also a candidate to join the plan), when the amount in the field is readable and the actual amount is greater than it. It writes the actual amount into the field. It works only upwards: with the actual amount at or below the field (including negative, net of refunds) there is no button, and none when the actual amount is above 1e12, because the field would not accept it.

Manual decision wins. No mechanism undoes a lowering before saving: the automatic raise runs once, the button only on a click.

Saving. Only the Current Month Plan changes, on „Zapisz zmiany”. „Anuluj” discards the raises. The Approved Month Plan never changes.

Scope. Change mode of an approved plan only. Not the proposal, not income, not set-aside.

Marker. The `Equal` icon (`@lucide/vue`) in `muted-foreground`, with a tooltip and an `aria-label` „Plan podniesiony do wydatków”, shows when an expense row has the field equal to the actual amount and the actual amount is greater than the saved plan (after the automatic raise, after a click and after typing the same amount by hand). It adds no new meaning of a colour: the tone is carried by the arrow of ADR-0060.

Place. The button and the marker (never both) stand in the „Faktycznie” cell before the amount, below `xl` in the caption line, like the arrow in „Zatwierdzony”.

Effects. To Spend does not change (Running counts the greater of plan and actual, Bill counts the actual amount after the first positive operation). The deviation becomes 0 and the „ponad plan” reading and badge disappear; the pace drops. After saving there is no `month_item_over_plan` notice for the item and the deviation in the Month Summary is 0; the trace of the rise stays as the up arrow against the Approved Month Plan. ADR-0033 still holds: „over plan” is a fact against the Current Month Plan, and this ADR describes a deliberate raise of that plan.

This adds no Inertia prop and no token, and changes no stored data. It extends the glossary entries Current Month Plan and To Spend; no _Avoid_ term changes.

We rejected:

- raising on every edit or on blur (it would make lowering impossible);
- a layer of an „effective plan” inside `parsedAmount` (the field would show something other than what is counted);
- an automatic raise for candidates (it would silently join Categories);
- a two-way button (it would lower a Running plan in the middle of the month);
- acting in the proposal (it changes the proposal rule of ADR-0033);
- „over plan” against the Approved Month Plan with a stored raise trace (server changes without need);
- the `accent` colour (ADR-0056 limits it to Alert/Badge `ok`);
- a place in the „Plan” cell or a wider column (it squeezes the field, ADR-0060; it takes space from the category, ADR-0061).
