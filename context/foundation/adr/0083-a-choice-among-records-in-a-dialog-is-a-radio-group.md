# A choice among the User's records in a dialog is a radio group

ADR-0040 made a choice in a form dialog a `SegmentedControl` `block` with a `name` (`.ai/rules/js-dialogs.md`). That fits a fixed enumeration of a few short options (Rhythm, type). It does not fit a list of the User's own records whose length varies and whose names are the User's: the Category groups. The dialog „Zmień grupę…” of a Category row has to offer „Bez grupy” and every group of the Category's type, and a User may keep five or more groups. This ADR adds `ui/radio-group` for that case.

Rule. Choosing one item from a short list of the User's records of variable length (up to about ten) in a dialog is `RadioGroup` from `components/ui/radio-group` inside `FormField group`: the label of the field is the `aria-labelledby` of the group, every option has a visible `<label :for>` beside its `RadioGroupItem`, and the value that is submitted is an explicit `<input type="hidden" name="…">`, not the `name` of the radio. The radio works on text values: the option for „no value” carries a non-empty value of its own (reka's `RadioGroupItem` refuses `''`), and the hidden field sends `''` for it, which `ConvertEmptyStringsToNull` turns into `null` before validation (the rule `present` passes, because the field is in the request). The selection starts from the stored value on every opening, and „Zapisz” stays disabled while the choice equals it. Focus on the item is a 2 px `outline` like the other controls; colours come from the tokens (`border-input`, `primary`, `destructive`).

Boundaries. `SegmentedControl` `block` stays for fixed enumerations (ADR-0040, ADR-0041). `CategorySelect` / reka `Select` stays for long lists whose options carry an icon (merge target). `.native-select` stays for simple enumerations outside dialogs. This ADR changes nothing in the paragraph of `js-dialogs.md` about conversion flags (ADR-0041).

Example. The Category „Prąd” stands in „Opłaty stałe”. The dialog „Zmień grupę: Prąd” lists „Bez grupy”, „Zdrowie”, „Opłaty stałe” (selected) and „Podróże”. Picking „Podróże” enables „Zapisz”; the request carries `category_group_id=<id>`, and picking „Bez grupy” carries `category_group_id=` (ADR-0069: the Category goes to the end of the list of its type).

We rejected:

- `SegmentedControl` `block` (the options squeeze in the 460 px dialog from five groups on, and a long group name truncates);
- `.native-select` (the options stay hidden behind one more click in a dialog that has a single field, and the list cannot be read at a glance);
- a submenu in the „⋯” menu of the row (the assignment would be saved without a confirmation and without a way to see the current group among the others).
