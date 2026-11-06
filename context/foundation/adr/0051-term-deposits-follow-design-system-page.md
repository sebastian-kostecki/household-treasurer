# Term Deposits follow the PageTermDeposits page, with "zł" in each balance

ADR-0045 made the pages of the "C · Atrament i morski" design system (artifact https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA) the default for the views, and ADR-0044 and ADR-0047 applied that to the Accounts view. The Term Deposits view still shows one `StatTile` card and a list without column headers on `.holdings-list-row`. It now follows the `PageTermDeposits` page. This ADR records the layout and the places where the implementation departs from the card, which no newer ADR settles (`design-system.md`).

The table has the columns Nazwa | Bank | Data zakończenia | Saldo bieżące | Akcje, with a header row in the `table-header` style on the `.term-deposits-grid` class. Each balance goes through `formatMoney`, so it carries "zł", and the panel has no "Kwoty w zł" footer. This is the third exception to ADR-0037, next to the Operations list (ADR-0039) and the Accounts table (ADR-0044), and it supersedes ADR-0037 only for the unit in the Term Deposits table. The reason is the same as in ADR-0044: one column of amounts, no sum row, and the sums stand in the metrics panel.

Below `md` a row has two lines: the name and the balance, then the bank, the date and the actions. The card has no mobile layout, and no column disappears. A Term Deposit without an end date shows a muted "—" in the column, with the screen reader text "Bez daty zakończenia", so the column stays even.

The metrics panel is a `StatPanel` with "Suma lokat" and "Łączne środki" ("konta i lokaty"). The caption of "Suma lokat" is "W tym zarchiwizowane: …", set in the slot, and a negative amount is `destructive`. Both measures take their tone from `amountToneClass`, as in the Accounts view, although the card does not tone "Suma lokat".

The `Switch` "Pokaż zarchiwizowane" stands in the title row beside "Dodaj lokatę", with the condition of ADR-0047 extended to Term Deposits: it renders only when there are archived Term Deposits or the filter is on. The bank tile is `AccountMarkCompact` with the short mark (ADR-0047), instead of `AccountIdentityBlock`; the bank name stays as text in its own column.

The Term Deposit dialog takes the whole of ADR-0040, with "Anuluj" as `ghost` (ADR-0045), not the `outline` of the card. The warning about a negative balance stays an `Alert` `warning`. The card has an interest rate and a footnote in the panel; we leave both out, because the model has no interest field and the footnote only explains a preview.

`StatTile` and `.holdings-list-row` are removed, because nothing uses them any more.

We rejected the "Kwoty w zł" footer (one column of amounts does not need a unit line), the `Switch` under the panel as in ADR-0044 (ADR-0045 and ADR-0047 put it in the title row), hiding the Bank column on a phone (the bank is part of what identifies the row), an empty date cell (an uneven column and nothing for a screen reader) and `FormPreview` in the Term Deposit dialog (no "before → after" reading there). The change touches no rule in `context/foundation/glossary.md` and no _Avoid_ term.

Supersedes in part ADR-0037: the unit in the Term Deposits table.
