# Kontrakt UI widoku Operacje — Plan Brief

> Full plan: `context/changes/operations-ui-contract/plan.md`
> Research: `context/changes/operations-ui-contract/research.md`

## What & Why

Widok `/operations` przechodzi na kontrakt systemu projektowego. Dziś nagłówki kolumn i wiersze są składane z literałów (`text-[11px]`, `/[0.03]`) skopiowanych po 3 razy, więc zmiana skali podpisów czy odcienia hover nie dociera do Operacji. Pięć równorzędnych przycisków primary nie wskazuje akcji głównej. Powód wyłączenia przycisku siedzi w `title`, którego nie da się zobaczyć. Użytkownik z samymi przelewami widzi „Brak operacji finansowych” nad własną listą.

## Starting Point

Lista Operacji była pierwszą na siatce `.operations-grid`, ale została na etapie literałów w Vue, podczas gdy Budżet ma już nazwane klasy `-row` / `-header-row`. Przepis podpisu 11 px istnieje w `app.css` 4 razy. Pusty stan i filtry zależą od `hasFinancialOperations`. Operacje nie mają kitchen sinka i nie są na liście strażnika `check:ui`.

## Desired End State

Pliki widoku nie mają literałów, a nagłówki i wiersze używają `.operations-header-row` / `.operations-row`. W `app.css` jest jeden przepis podpisu (`column-caption`). Wiersz tytułu ma jedną akcję turkusową (Import) i cztery `outline` z ikonami. Pod przyciskami widać, dlaczego transfer albo ruch kapitału jest niedostępny, a czytnik ekranu odczytuje to jako opis przycisku. Pusty stan pokazuje się tylko wtedy, gdy nie ma żadnych zapisów. Paginacja ma widoczny fokus, a listy wyciszają się w czasie wizyty. Usuwanie pokazuje spinner, a opis z banku otwiera się z klawiatury. `/dev/operations` pokazuje 7 stanów, a `check:ui` łapie także `/[0.NN]`.

## Key Decisions Made

| Decyzja | Wybór | Dlaczego | Source |
| --- | --- | --- | --- |
| Klasy listy | `.operations-header-row` + `.operations-row` obok `.operations-grid`, wartości bez zmian | Wzorzec `.budget-category-*`; wartość hover jest w kontrakcie, brakowało nazwy | Research |
| Przepis podpisu | `column-caption` kanoniczny; 3 kopie → `@apply column-caption`, `.column-header` jako alias | Dwie odpowiedzi na to samo pytanie w docs i regułach | Research |
| Powód wyłączenia | Tekst pod wierszem tytułu + `aria-describedby`, bez `title` | Precedens `MonthPlanEditBar`; działa na dotyku i z czytnikiem | Plan |
| Akcja główna | „Import operacji” `default`; pozostałe `outline`, wszystkie z ikoną; pusty stan też z Importem jako główną | Cel PRD (`prd.md:36`), spójność nagłówka i pustego stanu; #35 zabrania dropdownu | Plan |
| Pusty stan i filtry | Prop `hasAnyRecords` (3× `exists()`) zastępuje `hasFinancialOperations` dla obu | Usuwa sprzeczny komunikat; daje wyszukiwanie przy ruchach kapitału | Plan |
| Strażnik | Regex `check:ui` + `/[0.NN]`, pliki Operacji na liście, `DashboardHoldings` na nazwaną klasę | Odcień nie wróci po cichu; poprawka 2 linii bez zmiany wyglądu | Plan |
| Stany | Fokus i `aria-disabled` w paginacji, `aria-busy` list, `Spinner` przy usuwaniu, dymek opisu z banku z klawiatury | Wszystkie luki z macierzy poza błędem usuwania | Plan |
| Kitchen sink | `/dev/operations` (tylko `local`), fixture TS, test 404 | Wzorzec month-plan | Research |
| Zrzuty | Jednorazowy skrypt Playwright przez `npx`, bez zależności w repo | Każda wcześniejsza zmiana UI odmówiła zależności | Research |

## Scope

**In scope:**

- `Operations.vue`, trzy wiersze, nowy `OperationsPageActions.vue`, `OperationDescriptionCell.vue`, trzy `*Actions.vue`, `PaginationLinks.vue` (wspólny)
- `app.css` (klasy Operacji, paginacji, `DashboardHoldings`, jeden przepis podpisu)
- `OperationsController` (`hasAnyRecords`) z testami Feature
- dwie funkcje w `lib/` z testami, klucz `common.loading`
- kitchen sink, strażnik, `.ai/rules/js.md`, `design-system.md`

**Out of scope:**

- jedna lista chronologiczna (D2), przelewy ukryte przy filtrach (D3), rozmiar `h1` (D1), dropdown akcji
- `ConfirmActionDialog` (D6), dialogi formularzy (D7), literały w innych widokach (D9)
- błąd usunięcia w dialogu (D11), tłumaczenie `aria-label` paginacji
- nowe zależności

## Architecture / Approach

Środowisko → kontrakt → widok → stany → utrwalenie. Klasy powstają w `app.css` i najpierw trafiają do kitchen sinka, potem do widoku. Logika szablonów (dostępność akcji z powodem, rozpoznanie wizyty listy) to funkcje w `resources/js/lib` z testami `vp test`, bo w repo nie da się testować komponentów Vue. Wiersz tytułu jest osobnym komponentem, żeby kitchen sink renderował prawdziwe przyciski w każdym stanie. Zmiana propsa idzie razem po stronie PHP, TS i `assertInertia`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Kitchen sink i punkt odniesienia | `/dev/operations`, fixture, test 404, zrzuty bazowe | Worktree bez `vendor`/`node_modules` — najpierw kroki „Fresh checkout” |
| 2. Elementy kontraktu | Klasy w `app.css`, jeden przepis podpisu, 2 funkcje `lib/` z testami | `@apply column-caption` zmienia Plan miesiąca, Budżet i tabele — oczekiwana delta zero, sprawdzić zrzutem |
| 3. Widok (C1–C5) | Nazwane klasy w widoku, `OperationsPageActions`, `hasAnyRecords`, pusty stan | Zmiana kontraktu propsów (PHP + TS + testy w jednym commicie) |
| 4. Stany z macierzy | Fokus paginacji, `aria-busy`, spinner, dymek z klawiatury, macierz 7 stanów | `PaginationLinks` jest wspólny dla ok. 9 widoków |
| 5. Utrwalenie | Strażnik `/[0.NN]`, `DashboardHoldings`, reguła, docs, `ci:check` na PostgreSQL, `sloppy` | Konflikt `app.css` z `accounts-ui-contract` przy scalaniu |

**Prerequisites:** działający stos Sail (PostgreSQL, obraz `sail-8.5/app`), kroki „Fresh checkout” w worktree, osobna baza podglądu, Chromium Playwright w `~/.cache/ms-playwright`.
**Estimated effort:** ok. 3–4 sesje w 5 fazach.

## Open Risks & Assumptions

- Zakładamy, że `aria-label` podany przy użyciu `Spinner` nadpisze zaszyty „Loading” (fallthrough Vue). Jeśli nie, potrzebna będzie decyzja o zmianie `ui/spinner`.
- Użytkownik z samymi przelewami przy aktywnym filtrze zobaczy pustą listę z komunikatem „Ukryte przy tych filtrach”. To świadome zachowanie (D3), nie wada tej zmiany.
- Opóźnienie wyciszenia list (ok. 200 ms) to szacunek; trzeba je dostroić przy ręcznym teście na dławionej sieci.
- Równoległa `accounts-ui-contract` może dodać własną klasę wiersza w `app.css`, więc nazwy trzeba uzgodnić przy scalaniu.

## Success Criteria (Summary)

- Na `/operations` od razu widać jedną akcję główną, a każdy wyłączony przycisk mówi, czego brakuje, także na telefonie i dla czytnika ekranu.
- Użytkownik z samymi przelewami lub ruchami kapitału widzi swoje listy z filtrami, bez komunikatu „Brak operacji”.
- `npm run check:ui` i `composer ci:check` przechodzą na PostgreSQL, a kitchen sink pokazuje 7 stanów.
