# A plate under the bank logo, and Term Deposit pick lists with the avatar

ADR-0054 put every bank logo on a `muted` tile and wrote that the contrast was checked in both themes "with no known limitation". The 1.4.3 audit (C4) measured the transparent Revolut logo (`#000000`) on the dark-theme `muted` (`#19243a`) at 1.35:1, so it is invisible in the dark theme. The same ADR kept the three Term Deposit selects (the Operations filter, `CapitalMovementFormDialog`, the import row) native "because they need a payload change that is not worth it", so a Term Deposit has an avatar in tables and no avatar in a pick list, unlike an Account.

Plate. A new token `--bank-mark-plate`, published as `bg-bank-mark-plate`: in `:root` the value of `--muted` (`#e9edf2`), in `.dark` a light surface (`#e9edf2`). The plate stands only under the logo branch of `AccountAvatar` (`mark_url` present and not failed). The short mark (`mark-label`, `text-foreground`, light in the dark theme), `Coins` and `Building2` stay on `bg-muted`, because a light plate would hide them. The logo is `object-contain` as before, and JPEG logos (mBank, BNP Paribas) carry their own background.

Contrast of the logos on the plate, measured (WCAG 2.x, `#e9edf2` in both themes):

| Logo | Colour | Contrast |
| --- | --- | --- |
| Revolut | `#000000` | 17.86:1 |
| Lokaty Facto | `#00569D` | 6.34:1 |

Both are above 3:1 in the light and in the dark theme. On the dark `muted` the Revolut logo had 1.35:1.

Term Deposit pick lists. The three native selects become `AccountSelect` (a reka `Select`) with `AccountAvatar` `sm` and `type: 'bank'`. The three payloads (`termDeposits` of Operations, `filterTermDeposits`, `termDeposits` of the import) get `bank` and `mark_url` from one function, `AccountIdentity::termDepositIdentity()`. The filter keeps "Wszystkie lokaty" and the archived suffix like the Account filter, and the chip of an active Term Deposit filter shows an `xs` avatar before the name. Field names and chosen values do not change. `AccountSelect` submits its value through a hidden input, so the browser no longer checks `required` on the Capital Movement dialog (the server error on `term_deposit_id` stops it), and a disabled import row sends an empty `rows[i][term_deposit_id]`, which `StoreCsvImportRequest` accepts.

Supersedes in part ADR-0054: the sentence that the Term Deposit selects stay native, the rejection of "a white tile with a new token", and the sentence "Contrast: ... with no known limitation". The rest of ADR-0054 stands.

We rejected:

- a plate under every branch (the short mark would be light on light in the dark theme);
- inverting the logo with a CSS filter (it changes the brand colours of Lokaty Facto);
- a white `card` plate (the token has a name and a meaning, and `card` changes with the surface it sits on);
- a second dark logo file per bank (more files for one bank each).

It changes no rule in `context/foundation/glossary.md` and no _Avoid_ term.
