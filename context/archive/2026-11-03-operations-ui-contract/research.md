---
date: 2026-11-03T16:22:11+01:00
researcher: Sebastian Kostecki (z Claude)
git_commit: 101a9e49896aa9dc8ffe30b39c940078a40e9be4
branch: worktree-operations-ui-contract
repository: household-treasurer
topic: "Dwukierunkowy audyt UI widoku /operations — zarzuty i decyzje kontraktu"
tags: [research, ui, design-system, operations, Operations.vue, FinancialOperationRow, OwnTransferRow, CapitalMovementRow, OperationsFilters, app.css]
status: complete
last_updated: 2026-11-03
last_updated_by: Sebastian Kostecki (z Claude)
---

# Research: dwukierunkowy audyt UI widoku /operations

**Date**: 2026-11-03T16:22:11+01:00
**Researcher**: Sebastian Kostecki (z Claude)
**Git Commit**: 101a9e49896aa9dc8ffe30b39c940078a40e9be4
**Branch**: worktree-operations-ui-contract (baza: `development` @ 317b07f)
**Repository**: household-treasurer

## Research Question

Dwukierunkowy audyt widoku `/operations` (`Operations.vue` + wiersze `FinancialOperationRow`, `OwnTransferRow`, `CapitalMovementRow`, panel `OperationsFilters`). Zweryfikować i rozwinąć 5 zarzutów z audytu wstępnego `/10x-ui` (`change.md` → „Wstępny audyt”) i zapisać je z plik:linia i wpływem pod `## Charges`. Ustalić:

1. który z dwóch identycznych przepisów nagłówka kolumny (`column-caption` / `.column-header`) jest kanoniczny i czy `/[0.03]` jest zarzutem, skoro wartość jest w kontrakcie;
2. jak repo tłumaczy wyłączoną akcję i jaki wzorzec wiersza tytułu obowiązuje;
3. czy trzy osobne sekcje i stan pusty to decyzja, czy przypadek;
4. macierz 7 stanów dla widoku;
5. który z istniejących wzorców kitchen sinka i strażnika literałów przyjąć.

## Summary

Pięć zarzutów się potwierdziło, ale trzy zawęziły dowody:

- **Zarzut 2 (hover wiersza):** wartość `foreground/3%` jest w kontrakcie (`.docs/design-system.md:17`), a trzy wcześniejsze zmiany odrzuciły ją jako zarzut (dashboard `research.md:30,50`, accounts `research.md:33,84`, budget `research.md:116` — deferred). Zarzutem pozostaje brak **nazwy**: ten sam ciąg klas jest skopiowany w 3 wierszach tego widoku, podczas gdy Budżet trzyma go w nazwanej klasie `.budget-category-row` (`app.css:371-373`). Wartość się nie zmienia.
- **Zarzut 4 (nagłówek strony):** `h1 text-lg` nie jest dryfem Operacji — mają go wszystkie sprawdzone strony domenowe (`Budget.vue:190`, `MonthPlan.vue:516`, `Accounts.vue:106`, `TermDeposits.vue:108`, `OperationsImport.vue:199`, `OperationsImportPreview.vue:209`), sprzeczny jest dokument (`design-system.md:23`). Ta część → deferred (jak month-plan D3). Zostaje hierarchia przycisków, ograniczona decyzją z issue #35: osobne kontrolki, bez dropdownu akcji.
- **Zarzut 5 (sekcje i pusty stan):** trzy sekcje z osobnymi paginatorami i karta bez tytułu to decyzje (issue #19, #35, #80) → deferred. Zostaje realna wada: użytkownik z samymi przelewami własnymi / ruchami kapitału widzi „Brak operacji finansowych” nad własnymi listami i traci filtry; przypadek nie jest testowany.

Odpowiedzi na pytania:

1. **`column-caption` jest kanoniczny** (dashboard-ui-contract, `plan.md:23,57,169`). `.column-header` powstał równolegle w month-plan-ui-contract bez wiedzy o nim. Merge `e2d6f05` cofnął dodatkowo `@apply column-caption` w `.list-surface-table thead th`. Dziś ten sam przepis 11 px jest w `app.css` 4 razy (`:199`, `:233`, `:267-268`, `:375-377`), a dokumenty wskazują obie klasy (`design-system.md:17` vs `:18`, `.ai/rules/js.md:37` vs `:42`).
2. **Wyłączona akcja:** `title` na wyłączonym przycisku występuje w repo tylko w `Operations.vue:146,159` i nie działa (`disabled:pointer-events-none`, `ui/button/index.ts:7`). Precedensy: widoczny tekst obok wyłączonego przycisku (`MonthPlanEditBar.vue:47-54`, month-plan C5), powód w placeholderze wyłączonego selekta (`ExpenseFormDialog.vue:565-573`), ukrycie akcji (`Budget.vue` `v-if` na `can_*`). **Wiersz tytułu:** każdy z czterech sprawdzonych widoków domenowych (Budget, MonthPlan, Accounts, TermDeposits) ma w wierszu tytułu co najwyżej jeden przycisk primary; Operacje mają pięć.
3. **Sekcje — decyzja; pusty stan dla „tylko przelewy” — przypadek** (szczegóły w C5 i D2).
4. **Macierz 7 stanów** — tabela w „Detailed Findings”. Luki: disabled (C3), loading listy, focus-visible paginacji, error usuwania; brak kitchen sinka.
5. **Kitchen sink:** wzorzec month-plan (`Route::inertia` w `if (app()->environment('local'))` + fixture TS + test 404). **Strażnik:** dopisać pliki do `scripts/check-ui-literals.mjs` (biegnie w `composer.json:70`); rozszerzenie regexu o `/[0.NN]` złamie dziś `DashboardHoldings.vue:142,203`, które są na liście FILES.

## Charges

Format: kategoria, kotwica, dowód, wpływ na użytkownika, kierunek poprawki. Status planu nadaje `/10x-plan`.

### C1 — Brakujące tokeny: nagłówek kolumn listy bez nazwy, przepis 11 px w czterech kopiach

- **Status:** fixed (faza 3; klasy i funkcje: faza 2, stany: faza 4).

- **Kotwica (widok):** `resources/js/pages/Operations.vue:232`, `:272`, `:305` — cały wiersz nagłówka złożony ręcznie: `operations-grid … border-border/60 text-muted-foreground hidden border-b pb-2.5 text-[11px] font-medium tracking-wider uppercase md:grid`, skopiowany 3×.
- **Kotwica (źródło):** `resources/css/app.css:199-201` `@utility column-caption`; `:233-235` `.column-header` (identyczny `@apply`); `:267-268` `.list-surface-table thead th` (ten sam przepis literalnie); `:375-377` `.budget-category-header-row` (ten sam przepis literalnie + `hidden border-b pb-2.5`).
- **Dowód kierunku źródło → widoki:** `column-caption` ma 4 konsumentów (`BudgetPlanSummaryStrip.vue:21`, `DashboardCategorySection.vue:86`, `DashboardHoldings.vue:132,193`) + kitchen sink dashboardu; `.column-header` ma 1 widok (`MonthPlan.vue:733-749`) + `MonthPlanSink.vue`. W plikach widoku Operacje nie ma żadnej z tych klas (przeszukane 19 plików widoku, wg agenta audytu źródła).
- **Dowód dokumentacji:** `.docs/design-system.md:17` każe używać `column-caption` „nigdy ręcznie złożonej z `text-xs … uppercase`”, `:18` wymienia `.column-header`; `.ai/rules/js.md:37` vs `:42` — ta sama sprzeczność.
- **Wpływ na użytkownika:** nagłówki kolumn Operacji nie pójdą za zmianą skali podpisów w kontrakcie; każdy agent czytający reguły dostaje dwie odpowiedzi na to samo pytanie i tworzy trzecią kopię.
- **Kierunek:** nazwana klasa wiersza nagłówka `.operations-header-row` w `app.css` (wzorzec `.budget-category-header-row`) z `@apply column-caption`; trzy pozostałe kopie przepisu w `app.css` sprowadzić do `@apply column-caption` (przywraca intencję `e4b3733`); w dokumentach jedna nazwa.

### C2 — Brakujący token: wiersz listy Operacji bez nazwanej klasy

- **Status:** fixed (faza 3; klasy i funkcje: faza 2, stany: faza 4).

- **Kotwica:** `resources/js/components/FinancialOperationRow.vue:49`, `OwnTransferRow.vue:27`, `CapitalMovementRow.vue:26` — identyczny ciąg `border-border/60 hover:bg-foreground/[0.03] gap-y-3 border-b py-3 text-sm transition-colors last:border-b-0 md:gap-y-0`.
- **Dowód:** wartość hover jest w kontrakcie (`design-system.md:17` „hover `foreground/3%`”) i trzy zmiany odmówiły uznania jej za zarzut (dashboard `plan.md:45`, accounts `research.md:33,84`, budget `research.md:116`). Precedens nazwy istnieje: `.budget-category-row` (`app.css:371-373`) i `.list-surface-table tbody tr` (`:271-273`) trzymają ten sam literał w źródle tokenów, nie w widoku. Żaden ze strażników nie łapie formy `/[` (`scripts/check-ui-literals.mjs` wzorzec `-\[[0-9.]+(px|rem)\]`; wg agenta także `MonthPlanHardcodedUiTest`).
- **Wpływ na użytkownika:** trzy typy wierszy na jednej stronie mogą się rozjechać (już się rozjechały w innych widokach: `Accounts.vue:157`, `TermDeposits.vue:159`, `DashboardHoldings.vue:142,203`, `ImportPreviewRow.vue:266` mają warianty tego ciągu); hover nie jest strojony z warstwy tokenów.
- **Kierunek:** `.operations-row` w `app.css` obok `.operations-grid` (wzorzec `.budget-category-row`); wartość `foreground/[0.03]` bez zmian. Wspólna klasa „wiersz listy” dla całej aplikacji — deferred (D9).

### C3 — Przypadkowa architektura: powód wyłączenia przycisku jest niewidoczny

- **Status:** fixed (faza 3; klasy i funkcje: faza 2, stany: faza 4).

- **Kotwica:** `resources/js/pages/Operations.vue:144-168` — „Dodaj transfer” (`:disabled="accounts.length < 2"`) i „Dodaj ruch kapitału” (`accounts.length === 0 || termDeposits.length === 0`) z powodem w `:title`.
- **Dowód:** `resources/js/components/ui/button/index.ts:7` daje `disabled:pointer-events-none`, więc natywny `title` nie pojawi się po najechaniu; wyłączony `<button>` wypada z kolejności Tab, a `title` nie trafia do drzewa dostępności jako opis. `title=` na wyłączonym przycisku występuje w `resources/js` tylko w `Operations.vue:146,159` (wg agenta stanów, grep repo). `IconAction` też nie opakowuje przycisku w `span` (`IconAction.vue:41-64`), więc nie jest gotowym rozwiązaniem. Teksty istnieją: `lang/pl/ui.php:490,501`.
- **Wpływ na użytkownika:** osoba z jednym kontem (albo bez lokaty) widzi wyszarzony przycisk i nie ma żadnej drogi — myszą, klawiaturą, czytnikiem, dotykiem — by dowiedzieć się, dlaczego.
- **Kierunek:** nośnik widoczny lub dostępny. Precedensy w repo: tekst obok wyłączonej kontrolki (`MonthPlanEditBar.vue:47-54`, month-plan C5), powód w samej kontrolce (`ExpenseFormDialog.vue:565-573`), ukrycie akcji (`Budget.vue` na `can_*`). Wybór → Decyzja 4.

### C4 — Hierarchia wiersza tytułu: pięć równorzędnych przycisków primary

- **Status:** fixed (faza 3; klasy i funkcje: faza 2, stany: faza 4).

- **Kotwica:** `resources/js/pages/Operations.vue:136-174` — pięć `<Button>` w wariancie domyślnym (primary, turkus); „Dodaj transfer” (`:153`) i „Dodaj ruch kapitału” (`:167`) bez ikon, pozostałe z `Plus` / `Upload`.
- **Dowód:** sprawdzone wiersze tytułu mają co najwyżej jeden przycisk primary: `Budget.vue:196-263` (jeden warunkowy primary + `DropdownMenu` `outline`), `MonthPlan.vue:530-535` (jeden warunkowy), `Accounts.vue:105-110` i `TermDeposits.vue:107-112` (jeden). `design-system.md` tabela kolorów: `primary` = „akcja, zaznaczenie”. **Ograniczenie:** issue #35, kryterium 21: „Add income / expense / transfer / import stay four separate controls; no … actions dropdown” — zgrupowanie w menu „Dodaj” odwraca tę decyzję.
- **Wpływ na użytkownika:** żadna akcja nie jest główna; na 390 px pięć przycisków `h-9` zawija się w ok. 3 rzędy turkusu nad filtrami (szacunek z długości etykiet, nie mierzone), lista zaczyna się niżej, a dwa przyciski bez ikon wyglądają na inną rodzinę.
- **Kierunek:** zachować pięć osobnych kontrolek (#35), zróżnicować warianty (jeden `default`, reszta `outline`) i ujednolicić ikony. Który przycisk jest główny — Decyzja 5 (wybór produktowy). Rozmiar `h1` → D1.

### C5 — Przypadkowa architektura: pusty stan i filtry zależą tylko od dochodów/wydatków

- **Status:** fixed (faza 3; klasy i funkcje: faza 2, stany: faza 4).

- **Kotwica:** `resources/js/pages/Operations.vue:178` (`OperationsFilters v-if="hasFinancialOperations"`), `:189` (`IndexEmptyState v-if="!hasFinancialOperations"`), `:266`, `:299` (sekcje przelewów i ruchów kapitału z własnym `v-if` na `total > 0`); `app/Http/Controllers/OperationsController.php:189` (`'hasFinancialOperations' => $user->financialOperations()->exists()`); tekst `lang/pl/ui.php:454` „Brak operacji finansowych”.
- **Dowód:** przy `hasFinancialOperations = false` i `ownTransfers.total > 0` renderują się jednocześnie stan pusty (z CTA „Dodaj dochód” / „Import”) i lista przelewów, a filtry znikają. Przypadek nie jest testowany: `tests/Feature/CapitalMovementTest.php:372` buduje takiego użytkownika, ale nie sprawdza `hasFinancialOperations` (wg agenta decyzji); `OperationsFilterTest` pinuje `hasFinancialOperations` (`:45,500,511`).
- **Wpływ na użytkownika:** ekran mówi „brak operacji” nad listą operacji — sprzeczny komunikat; użytkownik z samymi przelewami nie ma wyszukiwarki ani zakresu dat.
- **Kierunek:** warunek „pierwsze uruchomienie” oprzeć na istnieniu dowolnego z trzech rodzajów zapisów (zmiana kontraktu propsów: PHP + TS razem i `assertInertia`, `AGENTS.md:34-36`), a stan pusty pokazać tylko wtedy, gdy wszystkie trzy są puste. Czy filtry dla takiego użytkownika mają sens, skoro każdy aktywny filtr ukrywa przelewy (`OperationsListQuery.php:45-54,82-99`) — Decyzja 6.

### Deferred (poza zakresem tej zmiany, z powodem)

_Status D1–D11: deferred._

- **D1 — Rozmiar `h1` na stronach domenowych** (`text-lg` w 7 widokach vs `design-system.md:23` `text-xl`). Rozstrzygnąć dokumentem albo kodem dla wszystkich stron naraz; odroczone już w month-plan D3, budget `research.md:115`, accounts Open Q1.
- **D2 — Spłaszczenie trzech list w jedną chronologiczną.** Issue #19 („No … merged chronological ledger”, osobne parametry stron), #80 (ruchy kapitału jako trzecia kolekcja „pagination like Own Transfers”), #35 („Financial Operations list shell has no duplicate page title” — stąd karta bez tytułu `Operations.vue:226-230`). Nie zakazuje tego ADR ani spec, ale to decyzja domenowa, nie wizualna (`CONTEXT.md:127-153` — trzy różne typy zapisów).
- **D3 — Przelewy własne ukryte przy każdym aktywnym filtrze** (`OperationsListQuery.php:45-54,90-92`, test `OperationsFilterTest.php:449`) vs PRD §11.9.1 (`.docs/prd.md:477`: filtrowanie „operacji finansowych, transferów własnych i Capital Movements … po tekście, roku, koncie, lokacie i zakresie dat”). Sprzeczność domenowa, nie UI; komunikat „Ukryte przy tych filtrach” już istnieje (`OperationsActiveFilterChips.vue:234-247`).
- **D4 — Kolor paska postępu Inertia** `color: '#0b7285'` (`resources/js/app.ts:25`): literał hex poza widokiem, globalny.
- **D5 — Role tabeli na listach siatkowych** (budget impl-review F1, „`.operations-grid` has the same compromise”) — dotyczy wszystkich list siatkowych naraz.
- **D6 — `ConfirmActionDialog` w `FinancialOperationActions` / `OwnTransferActions` / `CapitalMovementActions`** — odroczone w accounts-ui-contract (`research.md:50-55,87`); wspólny komponent powstaje tam.
- **D7 — `InputError` bez `id` / `aria-describedby` w dialogach formularzy** (`InputError.vue:8-12`; np. `IncomeFormDialog.vue:330-453`) — dialogi są poza widokiem tej zmiany.
- **D8 — Ręcznie zbudowane powierzchnie w filtrach:** karta na korzeniu `Collapsible` (`OperationsFilters.vue:389`) i pigułka chipa (`OperationsActiveFilterChips.vue:256`) zamiast `Card` / `Badge`. Tokeny są poprawne (0 trafień skanu); zamiana wymaga zmiany struktury komponentu reka `Collapsible`, bez zysku dla użytkownika w tej zmianie.
- **D9 — Wspólna klasa wiersza listy dla całej aplikacji** i te same literały w innych widokach (`Accounts.vue:157`, `TermDeposits.vue:159`, `DashboardHoldings.vue:142,203`, `ImportPreviewRow.vue:266`, nagłówek `OperationsImportPreview.vue:301`). Zasada „jeden widok na zmianę”; klasy z C1/C2 będą gotowe do przyjęcia.
- **D11 — Błąd usunięcia w dialogu (403/404/500)** — usunięcie nie ma walidacji; błąd serwera pokazuje domyślny modal błędu Inertia. Komórka `error` w macierzy 7 stanów ma „N/A” z tym powodem.
- **D10 — Powiązanie wierszy kont/lokat z `operations?account_id=`** (dashboard `plan.md:42`) — funkcja, nie kontrakt UI.

## Detailed Findings

### Macierz 7 stanów (stan obecny)

| Stan | Dziś (kotwica) | Luka |
| --- | --- | --- |
| default | `.operations-grid` w `ListSurface` (`app.css:456-519`, `ListSurface.vue:24`); kwoty przez `financialOperationAmountClass` (`FinancialOperationRow.vue:84`); nagłówki literalne (C1) | C1, C2, C4 |
| hover | wiersz `foreground/[0.03]` (C2); akcje `IconAction` ghost `hover:bg-accent` (`ui/button/index.ts:20`), usuń `hover:bg-destructive/10` (`IconAction.vue:33`); paginacja `hover:bg-muted/60` (`PaginationLinks.vue:42`) | wiersze nie są klikalne — hover to tylko rytm, nie afordancja (zgodne z kontraktem listy) |
| focus-visible | przyciski nagłówka i akcji: pierścień `ring-ring/50` 3 px (`ui/button/index.ts:7`); `IconAction` z `aria-label` + Tooltip (`IconAction.vue:39-70`); filtry: `Input`, `.native-select` (`app.css:205`) | linki paginacji bez pierścienia 3 px — tylko bazowy outline (`PaginationLinks.vue:31-46`, `app.css:187`); wyłączony link paginacji (`href="#"`, `pointer-events-none`) osiągalny Tabem, bez `aria-disabled` (`PaginationLinks.vue:34,43`); dymek surowego opisu banku na nieosiągalnym z klawiatury `div` (`OperationDescriptionCell.vue:44-47`) |
| disabled | dwa przyciski nagłówka z `title` (C3); potwierdzenie usunięcia `:disabled="processing"` (`FinancialOperationActions.vue:80-86` i odpowiedniki) | C3 |
| error | usunięcie przez `<Form>` w `Dialog`; sukces = flash toast (`lib/flashToast.ts:6-15`, `Sonner.vue:84-87`); akcje usuwania nie rzucają walidacji (`ManageFinancialOperation`, `ManageCapitalMovement` wg agenta) | nieudane usunięcie (403/404/500) nie daje żadnej informacji w dialogu — formularz czyta tylko `processing` |
| empty | dwa `IndexEmptyState`: pierwsze uruchomienie (`Operations.vue:189-206`) i brak wyników filtrów (`:208-224`) z „Wyczyść filtry” | C5; stan pusty bez powierzchni karty (Accounts ma kartę, `Accounts.vue:112-139`) — niespójność między widokami, nie wada tego widoku |
| loading | wizyty filtrów: `Spinner` w polu wyszukiwania + `aria-busy` na panelu (`OperationsFilters.vue:233-238,390,410-413`); paginacja: `Link` z `preserve-scroll`, tylko globalny pasek NProgress (`PaginationLinks.vue:36`, `app.ts:24-26`); `ui/skeleton` istnieje, nieużywany | lista nie sygnalizuje zajętości przy paginacji i filtrowaniu (brak `aria-busy` na sekcjach); przycisk potwierdzenia usunięcia bez `Spinner` (precedens `MonthPlanEditBar.vue:63-74`); `Spinner` ma zaszyte angielskie `aria-label="Loading"` (`ui/spinner/Spinner.vue`) |

Mobile (390 px): wiersz ma 4 linie (`date amount` / `description` / `category account` lub `account` / `actions`, `app.css:456-472`), nagłówek kolumn jest `hidden md:grid`. Podpisów mobilnych nad wartościami, o których mówi `design-system.md:17`, nie ma — w tym widoku etykiety są zbędne (data, kwota, chip kategorii i znak konta są samoopisowe); nie traktuję tego jako zarzutu.

### Kierunek źródło → widoki

- Importy z `@/components/ui` w `Operations.vue`: tylko `Button` (`:37`). Wiersze nie importują nic z `ui/`; składają się z komponentów domenowych (`OperationsDateCell`, `OperationDescriptionCell`, `CategoryChip`, `CompactAccountIdentity`, `*Actions` → `IconAction`).
- Skan 10x (z dodaną alternatywą `/\[0\.[0-9]+\]`) na 19 plikach widoku: 6 trafień w 4 plikach (C1, C2), 0 w pozostałych 15 (wg agenta audytu źródła, lista plików w jego raporcie). `node scripts/check-ui-literals.mjs` na tych plikach: 3 trafienia (tylko `text-[11px]`).
- Klasy `.operations-*` (`app.css:456-519`) mają dokładnie 4 konsumentów: `Operations.vue` i trzy wiersze.

### Kierunek widok → źródło

| Literał / duplikat | Plik:linia | Powinno pokryć |
| --- | --- | --- |
| `text-[11px] font-medium tracking-wider uppercase text-muted-foreground` | `Operations.vue:232,272,305` | `column-caption` (`app.css:199`) w `.operations-header-row` |
| `border-b pb-2.5 hidden md:grid` (wiersz nagłówka ×3) | jw. | `.operations-header-row` (wzorzec `.budget-category-header-row`, `app.css:375`) |
| `hover:bg-foreground/[0.03] … py-3 text-sm transition-colors last:border-b-0` | `FinancialOperationRow.vue:49`, `OwnTransferRow.vue:27`, `CapitalMovementRow.vue:26` | `.operations-row` (wzorzec `.budget-category-row`, `app.css:371`) |
| 5× `Button` default | `Operations.vue:136-174` | wiersz tytułu wg `Budget.vue` (jeden primary) |

## Decyzje dla planu

### Decyzja 1 — klasy listy: `.operations-header-row` + `.operations-row`, jeden przepis podpisu

- W `@layer components` obok `.operations-grid` (`app.css:456`): `.operations-header-row { @apply column-caption border-border/60 hidden border-b pb-2.5; }` (+ `md:grid` przez `@variant md`, jak `.operations-grid` w `:474`) i `.operations-row { @apply border-border/60 hover:bg-foreground/[0.03] gap-y-3 border-b py-3 text-sm transition-colors last:border-b-0; }` (+ `md:gap-y-0`).
- Przepis 11 px sprowadzić do jednego źródła: `.column-header`, `.list-surface-table thead th` i `.budget-category-header-row` → `@apply column-caption …`. Oczekiwana delta wizualna w Month Plan, Budżecie i tabelach: zero (przepisy są identyczne — zweryfikować zrzutem `/dev/month-plan` i `/dev/kitchen-sink/budget`).
- `.column-header` zostaje jako alias (zmiana nazwy w `MonthPlan.vue` to inny widok); dokumentacja i `.ai/rules/js.md:42` mają wskazać `column-caption` jako kanoniczny.
- `@apply column-caption` wewnątrz `@layer components` działa, bo to `@utility` (`app.css:195-198`, `e4b3733` już tak robił).

### Decyzja 2 — strażnik literałów: `scripts/check-ui-literals.mjs`

- Trzy formy istnieją: skrypt Node z listą FILES (dashboard; wpięty w `composer.json:70`), `tests/Unit/MonthPlanHardcodedUiTest.php` (month plan), `resources/js/ui-contract.test.ts` z `BUDGET_VIEW_FILES` (budżet). Reguła `.ai/rules/js.md:38` mówi „add newly cleaned views to scripts/check-ui-literals.mjs” — to jest ścieżka udokumentowana.
- Dopisać do FILES: `Operations.vue`, trzy wiersze, `OperationsFilters.vue`, `OperationsActiveFilterChips.vue` i nową stronę kitchen sinka.
- **Wybór dla planu:** dodanie alternatywy `/\[0\.[0-9]+\]` do regexu skryptu złapałoby C2 w przyszłości, ale dziś złamie `DashboardHoldings.vue:142,203` (na liście FILES). Albo poprawić je w tej zmianie (2 linie, klasa z D9 nie istnieje — potrzebna byłaby lokalna klasa), albo zostawić regex bez zmian i opisać to w regule.

### Decyzja 3 — kitchen sink: wzorzec month-plan

- Trzy wzorce istnieją (kontroler z `abort_unless` dla `/dev/ui/*`, `Route::inertia` bez nazwy dla budżetu, `Route::inertia` w `if (app()->environment('local'))` dla `/dev/month-plan`, `routes/web.php:22-39`). Najnowszy i opisany w `design-system.md:68-70` to month-plan: strona `resources/js/pages/dev/OperationsSink.vue` (lub `OperationsKitchenSink.vue`), fixture `resources/js/lib/operations-fixtures.ts`, test Pest 404 w `testing` (wzorzec `DevMonthPlanSinkTest`).
- Wiersze są samodzielnymi komponentami z typowanymi propsami (`FinancialOperationRowData`, `OwnTransferRowData`, `CapitalMovementRowData`), więc sink renderuje je bez `setLayoutProps` i dialogów `Operations.vue`. Stany nagłówka (disabled) i pustych stanów pokazać na wydzielonych fragmentach.
- Zrzuty: jednorazowy skrypt Playwright (npx, bez zależności w repo) przy 1280 i 390 px do `context/changes/operations-ui-contract/screenshots/`. Brak testów zrzutów w repo i każda wcześniejsza zmiana odmówiła dodania zależności (dashboard `plan.md:48`, budget `plan.md:56`, month-plan `plan.md:57`, sidebar `plan.md:50`); spec zakazuje asercji klas (`.docs/specs/app-shell-openship-visual-language.md:73`).

### Decyzja 4 — nośnik powodu wyłączenia (C3): wybór

Opcje, wszystkie bez nowych zależności:

- **A. Tekst pomocniczy pod wierszem tytułu** (`text-xs text-muted-foreground`, tylko gdy któryś przycisk jest wyłączony) + `aria-describedby` na przycisku. Precedens: `MonthPlanEditBar.vue:47-54`. Działa na dotyku i z czytnikiem; dodaje linię tekstu.
- **B. Tooltip na opakowaniu `span` z `tabindex="0"`** i `aria-describedby`. Nie działa na dotyku; nowy wzorzec w repo.
- **C. Przycisk aktywny, powód w dialogu** (dialog pokazuje komunikat i link do dodania konta/lokaty). Najbardziej odkrywalne, ale zmienia przepływ i dialogi (poza zakresem).

Rekomendacja: **A** — jedyny wariant z precedensem, czytelny na 390 px i dla czytnika.

### Decyzja 5 — który przycisk jest główny (C4): wybór produktowy

Ograniczenie #35: pięć osobnych kontrolek, bez dropdownu. Do decyzji uczestnika: który przycisk dostaje `default`, a które `outline`. Kandydaci: „Dodaj wydatek” (najczęstszy ręczny wpis w budżecie domowym — wniosek, brak danych o użyciu) albo „Import operacji” (główna ścieżka wg CTA stanu pustego `Operations.vue:195-204`, gdzie primary jest „Dodaj dochód”, a import `outline` — co samo jest niespójne z nagłówkiem). Wszystkie pięć z ikoną 16 px (`design-system.md` „Gotowe elementy”: ikony `@lucide/vue` 16 px w kontrolkach).

### Decyzja 6 — pusty stan dla „tylko przelewy / ruchy kapitału” (C5): wybór

- Minimalnie: nowy prop (np. `hasAnyRecords`) liczony w `OperationsController` z trzech `exists()`; stan pusty pierwszego uruchomienia przy `!hasAnyRecords`. Wymaga zmiany PHP + TS + `assertInertia` w `OperationsFilterTest` (pinuje `hasFinancialOperations`), `AGENTS.md:34-36`.
- Filtry dla takiego użytkownika: pokazać (wyszukiwanie i daty działają na ruchach kapitału przez `applyToCapitalMovements`, `OperationsController.php` ~`:97-99`), ale przelewy znikną przy każdym filtrze (D3). Albo zostawić filtry przy `hasFinancialOperations` i naprawić tylko komunikat. Wybór należy do uczestnika; rekomendacja: pokazać filtry przy `hasAnyRecords` — komunikat „Ukryte przy tych filtrach” już tłumaczy znikanie przelewów.

## Code References

- `resources/js/pages/Operations.vue:134-174` — wiersz tytułu, 5 przycisków, `title` na wyłączonych
- `resources/js/pages/Operations.vue:178-224` — filtry i dwa stany puste na `hasFinancialOperations`
- `resources/js/pages/Operations.vue:226-330` — trzy `ListSurface`, nagłówki `:232,272,305`
- `resources/js/components/FinancialOperationRow.vue:49`, `OwnTransferRow.vue:27`, `CapitalMovementRow.vue:26` — ciąg klas wiersza
- `resources/js/components/PaginationLinks.vue:31-46` — linki paginacji bez pierścienia fokusu
- `resources/js/components/IconAction.vue:38-70` — tooltip na `Button` bez opakowania
- `resources/js/components/ui/button/index.ts:7` — `disabled:pointer-events-none`, pierścień fokusu
- `resources/js/components/MonthPlanEditBar.vue:47-54` — precedens tekstu obok wyłączonej akcji
- `resources/css/app.css:199-201,233-235,267-273,371-377,456-519` — przepisy podpisu, wierszy i siatki Operacji
- `app/Http/Controllers/OperationsController.php:189` — `hasFinancialOperations`
- `app/Support/OperationsListQuery.php:45-61,82-99` — `isActive`, `omitsCapitalMovements`, `suppressedSections`
- `scripts/check-ui-literals.mjs:10-20` — lista FILES; `composer.json:70` — wpięcie w CI
- `routes/web.php:22-39` — trzy wzorce kitchen sinków
- `lang/pl/ui.php:454,490,501` — teksty stanu pustego i powodów blokady

## Architecture Insights

- Kontrakt list (`design-system.md:17`, `.ai/rules/js.md:32`): jeden markup na siatce z `grid-template-areas`, nagłówek `hidden md:grid`. Budżet doszedł do pełnej formy (nazwane `-row` i `-header-row` w `app.css`), Operacje — pierwsza lista z tym wzorcem — zostały na etapie literałów w Vue.
- Równoległe gałęzie UI dodają klasy o tym samym przepisie pod różnymi nazwami (`column-caption` / `.column-header`) i merge potrafi cofnąć konsolidację (`e2d6f05`). Przy scalaniu tej zmiany sprawdzić `app.css` względem `accounts-ui-contract` (też celuje w hover wiersza).
- Strażnicy literałów są trzy i każdy obejmuje inny zbiór plików; żaden nie łapie `/[0.NN]`.

## Historical Context (from prior changes)

- `context/archive/2026-11-03-dashboard-ui-contract/plan.md:23,43,57,169` — `column-caption` jako jedna klasa podpisów; przyjęcie w Operacjach (`Operations.vue:232,272,305`) odroczone do osobnej zmiany. **Poparte** (to jest ta zmiana).
- `context/archive/2026-11-03-dashboard-ui-contract/plan.md:45`, `context/changes/accounts-ui-contract/research.md:33,84` — hover `foreground/[0.03]` nie jest zarzutem, bo to konwencja. **Częściowo poparte:** wartość — tak; brak nazwy w widoku — nadal zarzut (C2).
- `context/archive/2026-11-03-budget-view-ui/research.md:116` — literały kontraktu list odroczone, bo zmiana dotyka wszystkich list. **Poparte** dla zmiany wartości; nazwanie klasy wiersza Operacji jej nie zmienia.
- `context/archive/2026-11-03-month-plan-ui-contract/research.md:141` (D1) — te same nagłówki Operacji odroczone; `:143` (D3) — rozmiar `h1`; `:146` (D6) — wspólny `PageHeader`. **Poparte**; D3 i D6 pozostają odroczone (tu D1).
- `context/changes/accounts-ui-contract/research.md:142` — pierwsza wzmianka o duplikacie `.column-header`, bez decyzji. Rozstrzygane tu (Decyzja 1).
- Issue #19, #35, #80 (GitHub) — IA strony Operacji: osobne listy i paginatory, osobne CTA, karta operacji bez tytułu. **Poparte** (#35 kryt. 21 zweryfikowane `gh issue view 35`); część „no filters” z #35 zastąpiona późniejszymi filtrami (#84).

## Related Research

- `context/archive/2026-11-03-month-plan-ui-contract/research.md` — wzorzec Charges / Decyzje, kitchen sink, strażnik
- `context/archive/2026-11-03-dashboard-ui-contract/research.md` — `column-caption`, `check:ui`
- `context/archive/2026-11-03-budget-view-ui/research.md` — klasy `-row` / `-header-row`, `ui-contract.test.ts`
- `context/changes/accounts-ui-contract/research.md` — równoległa zmiana, hover wiersza, `ConfirmActionDialog`

## Open Questions

1. Decyzja 4 — nośnik powodu wyłączenia (rekomendacja A).
2. Decyzja 5 — który z pięciu przycisków jest główny.
3. Decyzja 6 — filtry i stan pusty dla użytkownika bez dochodów/wydatków (rekomendacja: `hasAnyRecords`).
4. Decyzja 2 — czy rozszerzyć regex `check:ui` o `/[0.NN]` (i poprawić `DashboardHoldings.vue:142,203`).
5. Stany loading/error do zakresu fazy stanów: `aria-busy` sekcji przy wizytach, `Spinner` w potwierdzeniu usunięcia, informacja o nieudanym usunięciu, pierścień fokusu i `aria-disabled` w `PaginationLinks` (komponent współdzielony z innymi listami — zmiana dotknie ich wyglądu fokusu, co jest zamierzone).
6. Niezweryfikowane bezpośrednio (z raportów agentów): linie `ManageFinancialOperation` / `ManageCapitalMovement` (brak walidacji przy usuwaniu), `MonthPlanHardcodedUiTest` regex, szacunek 3 rzędów przycisków na 390 px (nic nie było renderowane).
