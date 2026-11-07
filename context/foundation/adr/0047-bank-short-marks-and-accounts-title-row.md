# Bank tiles show short text marks, and the Accounts title row keeps a conditional Switch

ADR-0045 moved the Accounts view back to the `PageAccounts` page of the "C · Atrament i morski" design system (artifact https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA) and left the values of the bank tile to the implementing change. This ADR records them, and the places where the implementation deliberately departs from the card.

The bank tile is `AccountMarkCompact`. It shows a short text mark in the `mark-label` style (JetBrains Mono 600, 9/9) on `muted` with `radius-sm`, instead of the logo. The marks are:

| Bank key | Mark |
| --- | --- |
| `alior` | ALI |
| `bnp_paribas` | BNP |
| `ing` | ING |
| `lokaty_facto` | LF |
| `mbank` | mB |
| `pekao` | PEK |
| `pko_bp` | PKO |
| `revolut` | REV |
| `santander` | SAN |

A cash Account shows "zł". An Account of the bank `other`, or with no bank, shows the `Building2` icon and no text. A mark has at most 3 characters. The list lives in `resources/js/lib/account-identity.ts` next to `bankOptions`, and a new bank gets its mark in the same commit that adds the bank. Alior is "ALI", not the single "A" of the card, because one letter does not identify a bank and breaks the uniformity of the three-letter marks.

`AccountMarkCompact` shows the mark in every use: the Accounts table, the rows of Operations, the Account select and the bank select in the Account and Term Deposit dialogs. The `sm` variant (20 px) stays for the pick lists, because the card has only the 28 px tile. The tile is `aria-hidden`, because the name of the Account or bank always stands next to it.

`AccountIdentityBlock` keeps the logo until its own view change, and with it `mark_url` and `public/marks/*`. Removing them now would widen this change beyond the Accounts view.

The `Switch` "Pokaż zarchiwizowane" stays in the title row as in ADR-0045, but it renders only when `archived_accounts_count > 0 || show_archived`, against the card, which shows it always. A switch with no archived Accounts does nothing, and `show_archived` keeps it reachable to turn the filter off.

The README of `CategoryChip` in the artifact ("logo instead of an abbreviation") contradicts ADR-0045. ADR-0045 is the deciding source: where the artifact and an ADR differ and the newer ADR settles it, the ADR wins.

The change touches no rule in `context/foundation/glossary.md` and no _Avoid_ term.

Supersedes in part ADR-0044: the 28 px logo in the bank tile, already superseded by ADR-0045. We rejected passing the marks as an Inertia prop from PHP (a second source of the list with no gain, and the dialogs have no such payload anyway), one 28 px size for every use (the pick lists would grow, the card has only the 28 px tile) and a short mark in the Accounts table only (Operations and the dialogs would show a different tile for the same bank).

Superseded in part by ADR-0058: a cash Account shows "zł" in the bank tile. It shows the `Coins` icon.
