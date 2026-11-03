# Macierz stanów sidebara

Zrzuty w `context/changes/sidebar-ui-contract/screenshots/after-phase-4/`. Desktop 1440 px, mobile 390 px (prefiks `mobile-`). Każdy zrzut istnieje w wariancie `-light` i `-dark`; w tabeli `{st}` = `expanded` albo `collapsed`, a `{t}` = `light` albo `dark`.

| Stan | Gdzie pokazany | Plik zrzutu albo N/A |
| --- | --- | --- |
| default | Aplikacja (`/accounts`) i kitchen sink `/dev/ui/sidebar` (ramki z latami); menu lat otwarte | `desktop-{st}-{t}.png`, `menu-open-{st}-{t}.png`, `mobile-sheet-{t}.png`, `mobile-menu-open-{t}.png` |
| empty | Ramki bez lat w kitchen sinku; w aplikacji stan wymuszony w przeglądarce (`shell.budgetYears = []`) | `desktop-{st}-empty-years-{t}.png` |
| loading | N/A: powłoka nie ma deferred props; jedyne oczekiwanie to `pending` przełącznika roku, pokazane jako disabled | N/A |
| error | Toast po przycisku „Pokaż błąd wyboru roku” w kitchen sinku oraz po prawdziwym nieudanym wyborze roku (rok bez budżetu) | `toast-error-{t}.png`, `after-failed-select-{st}-{t}.png` |
| hover | Aplikacja: pozycja menu i przełącznik roku | `hover-nav-{t}.png`, `hover-switcher-{st}-{t}.png` |
| focus-visible | Aplikacja: przełącznik roku fokusowany klawiszem Tab (pierścień `sidebar-ring`) | `focus-switcher-{st}-{t}.png` |
| disabled | Aplikacja: przełącznik roku podczas POST przy spowolnionym żądaniu (`disabled` + `aria-busy`) | `disabled-switcher-{st}-{t}.png`, `mobile-disabled-{t}.png` |
