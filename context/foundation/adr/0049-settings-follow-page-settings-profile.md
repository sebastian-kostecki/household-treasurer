# Settings follow the PageSettingsProfile page

ADR-0045 sent the Dashboard and Accounts back to the pages of the "C · Atrament i morski" design system (artifact https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA). The settings views (Profile, Security, Appearance) are the next place where the app and the system differ: they still use `Heading` on the grey floor, a vertical icon navigation with `bg-muted` and bare form blocks. The page `PageSettingsProfile` is the reference, as it stands on 2026-11-06. Where this ADR departs from the card, it says so. `design-system.md` asks for an ADR before any departure from a page, and this is that ADR.

Layout. The settings layout renders a visible `h1` in the `page-title` style with a one-line description under it, instead of `Heading`; the hidden `h1` of each settings page goes away, so a page has one `h1`. The sub-navigation is a list of links with `aria-current="page"` on the active item, and the active item looks as on the card. From `lg` the layout is a grid of the navigation (200 px, token `--settings-nav-width`) and the content (up to 640 px, token `--settings-content-width`). Below `lg` the navigation is a horizontal row of links above the panels. The card does not describe a phone, so this is our decision.

Panel. A settings section is `SettingsPanel`, the recipe of ADR-0037 for a panel: `card`, a full `border`, `radius-lg`. It has a header bar with the title in `section-title` and a `meta` line, then the content, and an optional footer with a top border and the action on the right. `Heading` stays only in the two-factor and passkeys sections of Security, until their own change.

Fields. `FormField` (ADR-0040) is used in the settings panels too, not only in dialogs. The `Form` wraps the whole panel, so the action in the footer submits it.

Danger zone. The deletion of the account is the first place that needs a stronger recipe than the existing `Alert` `destructive`. The `Alert` gets the variant `danger`: background `destructive-soft`, transparent border, the icon and the title in `destructive`, the description in `foreground`. `destructive` on `destructive-soft` measures 5.03:1 in the light theme and 6.82:1 in the dark theme; `foreground` on `destructive-soft` is above 14:1. The `Button` gets the variant `outline-destructive`: the `outline` recipe with the text in `destructive`; it opens the confirmation. In the confirmation dialog the confirm button stays the filled `destructive`. Both are system roles for actions that delete, not a style of one screen.

`DeleteUser` goes through `ConfirmActionDialog`, with the password in a `FormField` and the focus returned to the field after a server error. For that, `ConfirmActionDialog` emits a new event `error`.

"Anuluj" in `ConfirmActionDialog` is `ghost`, so ADR-0045 is now in place for every confirmation. The form dialogs keep their current button until the change that implements ADR-0045 for them.

Theme. The theme choice is `SegmentedControl` with `tone="neutral"` (a setting saved at once), labels "Jasny", "Ciemny", "Systemowy", no icons because the card has none, and `aria-label` "Motyw".

Texts. The texts of the settings views live in `lang/pl/ui.php` under `settings.*`. The server messages (toasts) are in `lang/pl.json`. `lang/pl/validation.php` holds only `current_password` for now; the other validation messages fall back to the English of the framework until the i18n change.

Scope. Two-factor authentication and passkeys are deferred. In Security the password panel therefore stands next to two sections on the grey floor, for the time being.

Supersedes in part the sentence "`Heading` służy sekcjom ustawień" in `design-system.md` (not an ADR), and ADR-0040 in the part "Anuluj" in `ConfirmActionDialog` (already superseded by ADR-0045, here implemented). We rejected extending `ListSurface` with a title in the bar (it changes six views), reusing the existing `Alert` `destructive` and the filled `Button` `destructive` for the danger zone (they depart from the card), a vertical navigation list on a phone (it pushes the form down) and a full `validation.php` in this change.

The change touches no rule in `context/foundation/glossary.md` and no _Avoid_ term.
