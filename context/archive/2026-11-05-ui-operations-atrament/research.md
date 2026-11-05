---
date: 2026-11-05T13:42:23+01:00
researcher: Claude (Opus 5.5) dla Sebastiana Kostecki
git_commit: 9057c99a234a5491d9ae3b7106bdae479822ab95
branch: development
repository: household-treasurer
topic: "Dwukierunkowy audyt widoku /operations względem design systemu „C · Atrament i morski”"
tags: [research, codebase, operations, design-system, atrament, ui]
status: complete
last_updated: 2026-11-05
last_updated_by: Claude (Opus 5.5)
---

# Research: audyt widoku /operations względem systemu „C · Atrament i morski”

**Date**: 2026-11-05T13:42:23+01:00
**Git Commit**: 9057c99 (czyste drzewo poza `context/`; `roadmap.md` zmodyfikowany, `context/changes/ui-operations-atrament/` nieśledzony)
**Branch**: development
**Wzorzec**: artefakt `https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA`, wersja 1793873124-2686. Przeczytane: `project/README.md`, README komponentów DataTable, FilterBar, Tabs, CategoryChip, Amount, MockupCAtrament; z `MockupCAtrament/preview.html` widok Operacje (linie 811-836), style `.t-ops`, `.dhead`, `.opico`, `.fchip`, `.sbox` (114, 158-173) i `byDate`/`dateLabel` (381, 435-442). Artefakt to podgląd, nie źródło wartości (`change.md`).

## Research Question

Dwukierunkowy audyt `/operations` (kod → wzorzec i wzorzec → kod): 3–5 zarzutów z `plik:linia` i wpływem na użytkownika, a do tego: decyzje z `change.md` (zakładki w `?section=`, menu „Dodaj operację”, kolumny mockupu, suma dnia na serwerze), konsumenci współdzielonych komórek, kitchen sink jako bramka, dokumenty i ADR, tłumaczenia, stan pusty i licznik „X z Y”.

## Summary

- Skan literałów kolorów z `/10x-ui` na 15 plikach widoku: **0 trafień**. Dług jest w typografii i w ręcznych przepisach panelu, nie w kolorach (C1).
- Pięć zarzutów C1–C5 (sekcja Charges): typografia i przepis panelu (C1), kolor wydatku jako status (C2, pkt 7 README, wymaga ADR), brak wiersza dnia i licznika w zakładce (C3), układ trzech paneli i pięciu przycisków jako ślad kolejności funkcji (C4), luki w straży literałów i ręcznie kopiowany sink (C5).
- **Żaden ADR nie postanowił** czerwonego wydatku, trzech osobnych list, kolejności kolumn ani zakazu menu akcji. Źródłem są kryteria zamkniętych issue #35, #19, #80 (raport z `gh issue view`, zacytowany w `context/archive/2026-11-03-operations-ui-contract/research.md:38,86,104`). Nowy ADR musi wprost powiedzieć, że odwraca te kryteria.
- Najwyższy ADR to 0037; 0036 trzyma `period-report-by-category` (`context/changes/period-report-by-category/change.md:13`); **0038 jest wolny** (brak odwołań w repo).
- Suma dnia: `OperationsListQuery::apply()` (`app/Support/OperationsListQuery.php:122-162`) dodaje tylko klauzule WHERE, więc można go użyć na świeżym zapytaniu z `GROUP BY operation_date` i `SUM(CASE WHEN type='income' THEN amount ELSE -amount END)` — ta sama konwencja co `BudgetMonthResult` (`app/Support/BudgetMonthResult.php:20-34`). Pułapki: SQLite zapisuje daty modelu jako `Y-m-d H:i:s`, więc filtrowanie po dniach strony przez `whereDate`/zakres i klucz z `toDateString()` w PHP (wzór `MonthCategoryActuals.php:40-57`).
- `OperationsController::index` ma 169 linii i jest w baseline Sloppy jako SL101 (`.sloppy-baseline.json:40-47`); dopisanie logiki może wywołać nowe SL102 (God Class). Logika sumy dnia i liczników powinna trafić do nowej klasy w `app/Support`.
- `OperationDescriptionCell` (a przez nią `OperationTypeTile`) renderuje też podgląd importu (`ImportPreviewRow.vue:6,353`), więc zmiana kafelka zmienia import.
- Wstępne decyzje z `change.md` są wykonalne; trzy pułapki dla zakładek: filtry gubią `section` (`OperationsFilters.vue:231-244`), „Wyczyść filtry” w pustym stanie też (`Operations.vue:203`), a wybrana zakładka bywa wyciszona filtrem (`OperationsListQuery.php:82-99`).

## Charges

### C1. Brakujące tokeny: typografia wierszy, kwoty i ręczny przepis panelu (kategoria: brakujące tokeny)

Style z ADR-0037 istnieją w `resources/css/app.css` (`page-title` :234, `section-title` :239, `table-header` :243, `meta` :252, `amount` :264, `amount-sum` :268, `size-control-sm` :285, `min-h-row` :290), ale widok Operacji z nich nie korzysta poza nagłówkiem kolumn (`.operations-header-row` → `table-header`, :602-604).

- `pages/Operations.vue:151` tytuł `text-lg font-semibold` zamiast `page-title`.
- `OperationsDateCell.vue:11-12` data `text-sm font-medium` + względna `text-xs`; mockup ma datę krótką w mono (`meta num`, `preview.html:832`).
- `OperationDescriptionCell.vue:55,76` druga linia `text-sm` zamiast `meta`; `.operations-row` (`app.css:606-608`) ustawia `text-sm` (14 px) zamiast `body` 13/18 i `border-border/60` zamiast `border-soft`.
- `.operations-cell-amount` (`app.css:656-659`) `font-medium tabular-nums` bez kroju mono; `FinancialOperationRow.vue:89-94`, `OwnTransferRow.vue:61-63`, `CapitalMovementRow.vue:68-70` wołają `formatMoney`/`formatFinancialOperationAmount`, które dopisują „zł” w każdej komórce (`lib/format.ts:131-142`, `:16-21`). Brakuje stopki „Kwoty w zł” (wzór `BudgetCategoryList.vue:372-374`, klucz `budget.amounts_in_zl`, `lang/pl/ui.php:192`).
- `OperationTypeTile.vue:52,59` kafelek `min-h-10 w-10 self-stretch rounded-lg`, ikona `size-5` (40 px, rozciągany na wysokość opisu) zamiast 26 px (`size-control-sm`) i ikony 14 px.
- Ręczny stary przepis panelu (ADR-0037 zostawił go „dla widoku, który go renderuje”, `context/archive/2026-11-05-budget-atrament-ui/plan.md:51`): `OperationsFilters.vue:389` `border-border/60 … rounded-2xl border p-4`, `:446` Badge `rounded-xl`, `:461,629` `border-border/60`; `OperationsActiveFilterChips.vue:256` pigułka `border-border/60 bg-background … rounded-xl … text-sm`; `PaginationLinks.vue:29` `border-border/60`.

Wpływ: kwoty nie stoją przecinkiem pod przecinkiem, a „zł” powtórzone 50 razy na stronie zabiera szerokość kolumnie opisu; pasek filtrów wygląda jak inny system niż panele obok.

### C2. Kolor wydatku jako status (kategoria: brakujące tokeny — rola koloru; pkt 7 README)

- `lib/format.ts:147-156` `financialOperationAmountClass` zwraca `text-destructive` dla każdego wydatku (dochód i zwrot `text-success`).
- `OperationTypeTile.vue:28-31` wydatek `bg-destructive/10 text-destructive`; system: wydatek `muted`, dochód `accent` + `success`, zwrot `warning-soft` + `refund`, przelew tint `transfer` (README CategoryChip, „ht-optype”).
- Reguła repo już rezerwuje `destructive` dla przekroczeń, wartości ujemnych i usuwania (`.ai/rules/js.md:8-12`, `.docs/design-system.md:45`) — wydatek tam nie występuje, więc dzisiejszy kod łamie istniejącą regułę, a źródłem czerwieni są kryteria issue #35 („success/destructive colour”).

Wpływ: lista z przewagą wydatków świeci na czerwono, więc prawdziwe przekroczenia (ostrzeżenia, ujemne saldo) tracą sygnał. Zmiana wymaga ADR-0038 i `.docs/design-system.md` przed kodem (README pkt 7, `change.md`).

### C3. Brakujący współdzielony element: wiersz dnia, licznik zakładek, licznik wyników (kategoria: brakujący współdzielony komponent; pkt 8 README)

- Lista nie grupuje po dniach: `Operations.vue:241-249` renderuje płaską listę `FinancialOperationRow`, a data powtarza się w każdym wierszu (`FinancialOperationRow.vue:49-51`). DataTable README: `ht-row--day` na tej samej siatce, data słowami po lewej, suma dnia w kolumnie kwot, `amount-sum`.
- Nie ma formatera „data słowami”: `lib/format.ts:73-125` ma tylko `formatDate` (`DD.MM.RRRR`) i `formatRelativeDate`; brak nazw dni tygodnia i miesięcy w dopełniaczu w `resources/js` i `lang` (w `lang/pl/ui.php:274-285` są tylko mianowniki). `Intl.DateTimeFormat('pl-PL', {weekday:'long', day:'numeric', month:'long'})` daje „środa, 4 listopada” w Node tego repo (sprawdzone), a `.ai/rules/js.md:16` zabrania `Intl` tylko dla kwot.
- `SegmentedControl.vue:1-45` nie ma licznika przy opcji (etykieta to tekst, :42), a README Tabs wymaga `ht-tabs__count` w mono. Konsumenci: `MonthPlan.vue:625`, `MonthPlanKindCell.vue:35`, `dev/MonthPlanSink.vue`; plik pilnuje `tests/Unit/MonthPlanHardcodedUiTest.php:16`. To ToggleGroup z `aria-pressed`, nie ARIA tabs.
- FilterBar ma licznik „X z Y”; dziś go nie ma. `operations.total` (paginator) to X; nie ma niefiltrowanej liczby Y (`hasAnyRecords` to tylko `exists()`, `OperationsController.php:189-191`).

Wpływ: żeby zobaczyć, ile wydano danego dnia, trzeba sumować w głowie; po filtrowaniu nie widać, jaka część listy została.

### C4. Przypadkowa architektura: trzy panele jeden pod drugim i pięć przycisków w tytule

- `Operations.vue:210-320` trzy `ListSurface` jeden pod drugim (operacje finansowe bez tytułu, potem Transfery własne, Ruchy kapitału), każdy z własnym paginatorem (`OperationsController.php:45-46,73-74,101-102`). Układ wynika z kolejności issue #19 (dwie listy, osobne parametry stron), #80 (trzecia kolekcja) i #35 (karta operacji bez tytułu) — `context/archive/2026-11-03-operations-ui-contract/research.md:38,104`. Po 50 operacjach transfery są pod dołem strony.
- `OperationsPageActions.vue:46-87`: „Import operacji” jako jedyny primary i cztery zarysowane „Dodaj…”; ikony transferu i ruchu kapitału są odwrócone względem kafelka (`:69` Landmark dla transferu, `:82` ArrowLeftRight dla ruchu kapitału, a `OperationTypeTile.vue:39` ArrowLeftRight to przelew własny). Zakaz menu akcji pochodzi z #35 (`…operations-ui-contract/plan.md:51-56`), nie z ADR.
- Wejście bez danych i z linku: pusty stan bez rekordów i pusty stan po filtrach istnieją (`Operations.vue:173-208`, `hasAnyRecords`), więc nie ma surowego JSON ani pustej ramki; trasa jest za `auth` (`routes/web.php:67`).

Wpływ: użytkownik nie widzi, że istnieją transfery i ruchy kapitału, dopóki nie przewinie listy; pięć równorzędnych przycisków nie mówi, która akcja jest główna.

### C5. Straż kontraktu ma luki, sink kopiuje wiersze ręcznie (kategoria: przypadkowa architektura — reguła/kontrola)

- `scripts/check-ui-literals.mjs:39-53` nie obejmuje `OperationTypeTile.vue`, `OperationsDateCell.vue`, `CompactAccountIdentity.vue`, `SegmentedControl.vue`; `ui-contract.test.ts:13-29` skanuje tylko pliki Budżetu.
- `pages/dev/OperationsSink.vue` (:176-215, :244-285, :310-345) buduje nagłówki i wiersze ręcznie z komórek, więc zmiana siatki w `FinancialOperationRow` nie przenosi się na sink; `tests/Feature/DevOperationsSinkTest.php:8-12` sprawdza tylko 404 poza `local`.
- `.ai/rules/js.md:60-63` (sekcja „Operations list”) nie mówi nic o wierszu dnia, zakładkach ani kolorze kwoty.

Wpływ: następny agent może wprowadzić literał w kafelku lub dacie bez czerwonego CI, a bramka wizualna pokazuje inny markup niż widok.

## Decisions

Z `/10x-ui` (2026-11-05, `change.md`):

1. Zakładki Operacje finansowe / Transfery własne / Ruchy kapitału (`SegmentedControl` neutral) zamiast trzech paneli; sekcja w `?section=`; zakładka wyciszona filtrem pokazuje licznik 0.
2. Jedna akcja główna „Dodaj operację” z menu (Dochód, Wydatek, Transfer własny, Ruch kapitału, z powodem wyłączenia); „Importuj” zarysowany; dialogi bez zmian.
3. Kolumny operacji finansowych jak w mockupie: Opis (kafelek typu, konto w drugiej linii) | Kategoria | Data | Kwota | akcje.
4. Suma dnia na serwerze dla całego dnia z tymi samymi filtrami, jednym zapytaniem dla dni bieżącej strony.

Rekomendacje z audytu (do `/10x-plan`, nie podjęte przez użytkownika):

5. **Kolor i znak sumy dnia jak kwoty operacji**: dodatnia `+` w `success`, ujemna `−` w `foreground` (mockup `preview.html:829`: `inc` tylko dla `sum > 0`). Nie `amountToneClass`, bo po pkt 7 ujemny dzień wydatków nie jest przekroczeniem. Zapisać w ADR-0038.
6. **Dochód i zwrot zostają `+` w `success`** (README Amount; `.docs/design-system.md:44`).
7. **„Suma dnia” definiuje ADR-0038**: dochód − wydatek, zwroty netto, bez transferów i ruchów kapitału, przy aktywnych filtrach — ta sama konwencja co Month Result (`CONTEXT.md:107-109`). Nie zmienia reguły ani terminu _Avoid_, więc wpis w `CONTEXT.md` nie jest wymagany przez `AGENTS.md`; nazwa w UI „suma dnia” bez „wynik”/„bilans”.
8. **Wiersze dnia tylko w zakładce Operacje finansowe**; transfery i ruchy kapitału w mockupie są płaskie (`preview.html:816-820`).
9. **Licznik „X z Y”** dla aktywnej zakładki: X = `total` paginatora, Y = niefiltrowana liczba tej sekcji (nowy prop z trzema `count()`).

Odroczone (z powodem):

- **D3: transfery własne znikają przy każdym aktywnym filtrze** (`OperationsListQuery.php:90-92`) — sprzeczność z PRD §11.9.1 odnotowana w `…operations-ui-contract/research.md:105`; to zmiana domeny, nie wyglądu.
- **Paleta ⌘K i skróty `N`, `/`** — README MockupCAtrament: „dodatek wymagający osobnej zmiany”.
- **Jeden dialog dochód/wydatek z wyborem typu** — odrzucone przy `/10x-ui` (decyzja 2).

## Detailed Findings

### Suma dnia na serwerze

- `IndexOperationsRequest::filters()` (`app/Http/Requests/IndexOperationsRequest.php:45-57`) buduje `App\Support\OperationsListQuery` (`fromValidated`, `OperationsListQuery.php:27-43`).
- `apply()` (`:122-162`): `q` (LIKE z ESCAPE na `description`, `counterparty`, `raw_statement_description`, :124-133), `year` (`whereHas('budget')`, :135-137), kategoria nazwa + typ (`whereHas('category')`, :139-147), `account_id` (:149-151), daty przez `whereDate` (:153-159). `term_deposit_id` nie dotyczy operacji finansowych. Brak joinów i selectów.
- Wyciszenie sekcji jest w kontrolerze (`whereRaw('0 = 1')`, `OperationsController.php:37-39`), nie w `apply()`; przy wyciszonych operacjach strona jest pusta, więc zapytanie sumy można pominąć.
- Trzeba budować świeże zapytanie: `apply()` mutuje builder, a `orderByDesc('id')` z listy (`:43-44`) psuje `GROUP BY` na PostgreSQL.
- Znak: `type` to `income`/`expense` (`app/Enums/FinancialOperationType.php`), `amount_minor_units` bigint ze znakiem; zwrot = wydatek z ujemną kwotą (`CONTEXT.md:131-133`). `SUM(CASE WHEN type = ? THEN amount_minor_units ELSE -amount_minor_units END)` z `'income'` daje zwrot jako `+`. Precedens `CASE` + `groupBy('category_id','operation_date')`: `MonthCategoryActuals.php:40-41`. SUM(bigint) na PostgreSQL wraca jako numeric/string → `(int)`.
- `operation_date` to `date` (migracja :21, cast `date` w `FinancialOperation.php:60`), indeks `(user_id, operation_date)` (:32). Na SQLite wiersze z modelu mają `Y-m-d H:i:s`, a wstawki `DB::table` (np. `NfrPerf01Test.php:163`) `Y-m-d`: filtr po dniach strony przez `whereDate` min/max, klucz wyniku `CarbonImmutable::parse(...)->toDateString()` i `+=` w PHP.
- Dzień przecięty granicą strony sumuje też wiersze z sąsiedniej strony — to zgodne z decyzją 4.
- Testy Feature z `assertInertia`: `OperationsFilterTest.php` (filtry, `suppressedSections` :763-811, walidacja przez `assertSessionHasErrors` :538, :336, :831), `OperationsPaginationTest.php` (:21, :45, :68 niezależne parametry stron, :114), `OperationsEmptyStateTest.php` (:14-77), `OperationsIdentityTest.php`, `OperationsRowRecipeTest.php`.
- NFR: `tests/Feature/Nfr/NfrPerf01Test.php` (PostgreSQL, 10 000 operacji, `GET operations` < 2 s, :66-69); wszystkie operacje mają tę samą datę (:157), więc test przejdzie przez zapytanie sumy, ale z jedną grupą.
- Sloppy: progi SL101 i SL102 w `vendor/heyosseus/sloppy/config/sloppy.php:380-406`; SL101 dla `index()` jest w baseline (klucz `Class::method`, wzrost go nie wznowi), SL102 nie — kontroler ma 198 linii i dużo współpracowników. Nowa klasa w `app/Support` (obok `OperationsFilterOptions`), jedno wywołanie z `index()`, jedno zapytanie (bez SL2xx).

### Zakładki w `?section=`

- Enum `App\Enums\OperationsListSection` (`financial_operations`, `own_transfers`, `capital_movements`) już istnieje i opisuje `suppressedSections`.
- `IndexOperationsRequest::rules()` (:21-43) nie zna `section`; regułę `nullable` + `Rule::enum` i `blankToNull` w `prepareForValidation` (:59-73) można dodać, a akcesor trzymać poza `filters()`, żeby `section` nie wpływał na `isActive()`/`toArray()`. Dziś zły parametr przekierowuje z błędami sesji (wzór testu `OperationsFilterTest.php:538`).
- Linki paginacji zachowują całe query (`withQueryString()`), więc `section` przetrwa zmianę strony (`PaginationLinks.vue:36-48` renderuje `link.url` z serwera).
- `OperationsFilters.vue:231-244` (`visit`) buduje query tylko z 8 kluczy filtrów: zmiana filtra zrzuca `section` i numery stron. „Wyczyść filtry” w pustym stanie linkuje `operationsRoute()` bez query (`Operations.vue:203`).
- `listenForOperationsListVisits` (`lib/operations-list-visit.ts:7-50`) liczy wizyty GET na ścieżce `/operations` bez względu na query; zmiana zakładki przez `router.get` ustawi `aria-busy` — zachowanie spójne z filtrami.
- Wyciszenie (`OperationsListQuery.php:82-99`): operacje finansowe tylko przy samym `term_deposit_id`; transfery przy każdym filtrze; ruchy kapitału przy roku lub kategorii. Wybrana zakładka może być wyciszona — trzeba ustalić, co wtedy pokazuje (notka `filter_suppressed_sections`, `OperationsActiveFilterChips.vue:229-246`, już istnieje).
- Liczniki zakładek: `total` trzech paginatorów jest w propsach.

### Menu „Dodaj operację”

- `components/ui/dropdown-menu/*` istnieje; najlepszy wzór `BudgetCategoryRowActions.vue:25-126` (`@select`, ikony). `DropdownMenuItem` ma style `data-[disabled]` (`opacity-50`, `pointer-events-none`).
- Brak w repo pozycji menu wyłączonej z powodem; `.ai/rules/js.md:63` wymaga widocznego powodu przez `aria-describedby`, nie `title`. Powód jako druga linia tekstu w pozycji (`meta`) spełnia to bez `aria-describedby` poza menu.
- Dostępność: `lib/operations-create-availability.ts:16-37` (transfer ≥ 2 konta, ruch kapitału ≥ 1 konto i ≥ 1 lokata, klucze powodów), test `operations-create-availability.test.ts`.
- Pusty stan bez rekordów (`Operations.vue:173-191`) ma własne „Importuj” + „Dodaj dochód”.

### Kolumny i siatka

- `.operations-grid` (`app.css:554-585`): telefon `'date amount' 'description description' 'category account' 'actions actions'`; md `6.5rem 1fr 13rem 9.5rem 7rem 4.5rem` `'date description category account amount actions'`. Transfery `.operations-grid-transfer` (:564-588).
- Mockup `.t-ops` `1fr 180px 150px 120px 26px` (`preview.html:114`): opis (kafelek, opis, konto w `small`), kategoria jako pigułka (`.tag`, `preview.html:119-120`), data `dshort` w `meta num`, kwota, `⋯`. Wiersz dnia `.dhead`: etykieta na kolumnach 1–3, suma w kolumnie kwot (`preview.html:167-170, 829`).
- Mockup transferów `.t-tr` `110px 1fr 1fr 130px`: Data | Z konta | Na konto | Kwota, stopka z objaśnieniem (`preview.html:816-820`). Aplikacja ma Data | Opis | Konta (znak banku) | Kwota | Akcje — decyzja 3 dotyczy tylko operacji finansowych.
- Akcje wiersza (`FinancialOperationActions.vue` itd.) są poza mockupem jako `⋯`; dziś to `IconAction`-y (edycja, usunięcie).

### Konsumenci współdzielonych elementów

- `OperationDescriptionCell` także w `ImportPreviewRow.vue:6,353` (`OperationsImportPreview.vue:361`, `dev/ImportPreviewSink.vue:167`); `OperationTypeTile` tylko w `OperationDescriptionCell.vue:2,50,71`. Zmiana kafelka obejmie podgląd importu — trzeba go zobaczyć na zrzucie.
- `OperationsDateCell`, `CompactAccountIdentity`: tylko trzy wiersze Operacji i `dev/OperationsSink.vue`.
- `formatFinancialOperationAmount`, `financialOperationAmountClass`: tylko `FinancialOperationRow.vue:11-12,82,89` i `dev/OperationsSink.vue:18-19,206,213`; brak testów w `format.test.ts`.
- `.operations-grid*`, `.operations-cell-*`: tylko Operacje i sink; import ma `.import-preview-grid*`.

### Kitchen sink `/dev/operations`

- `routes/web.php:42-46` (tylko `local`), `pages/dev/OperationsSink.vue`: sekcje Default (:53), Kontrakt (:151), Wiersz tytułu (:352, 4 kombinacje kont i lokat), Puste stany (:373), Stany z macierzy (:411: hover, focus-visible, disabled, error N/A, loading). Fikstury `lib/operations-fixtures.ts` z `paginate()`.

### Dokumenty do aktualizacji

- ADR: nowy `0038-…`; ADR-0035:7 odroczył pkt 7 i 8, a :9 ma wzór dopisku („Points 3 and 5 … are settled by ADR-0037”) — dopisać analogiczny dla 7 i 8. Format: angielski, `# Title`, 1–3 akapity, „This supersedes in part …”, „We rejected …” (`.docs/adr/0037-…md:3-7`). ADR do częściowego zastąpienia: brak (patrz Summary); ADR-0005:3 (surowy opis tylko w dymku) i ADR-0016:3 zostają.
- `.docs/design-system.md`: :3 (lista ADR), :17-18 (`.operations-grid`, wiersze), :24 (mono „tylko Budżet”), :44-47 (role `success`/`destructive`/`refund`/`transfer`), :59-63 (Kwoty: znak i kolor kwoty operacji, suma dnia), :78-85 (gotowe elementy, `SegmentedControl` neutral jako „ustawienie zapisywane od razu” — zakładki w URL to nowe użycie), :91 (sink), :119 (`check:ui`).
- `.ai/rules/js.md`: :11, :16, :20, :24, :37, :42, :44, :57, :60-63 (sekcja Operations list). `.ai/rules/css.md`: bez konfliktu.
- `period-report-by-category` (`plan.md:297-304`) też edytuje `.docs/design-system.md` i `.ai/rules/js.md` — możliwy konflikt przy scalaniu, nie blokuje.
- Poza zakresem, nieaktualne: `.ai/skills/openship-ui-development/SKILL.md:19,26-33` (`rounded-2xl`, `border/60`).

### Tłumaczenia

- `lang/pl/ui.php` grupa `operations` (:470-645) ma: `title`, `list_description`, `financial_operations`, `own_transfers`, `capital_movements`, `empty_*`, `add_income`, `add_expense`, `add_transfer`, `add_capital_movement`, `import_csv` („Importuj”), `import_title`, `filter_*`, kolumny, `type_*`, powody. Brak: „Dodaj operację”, etykieta zakładek, licznik „X z Y”, opis sumy dnia, „Kwoty w zł” w grupie `operations` (jest `budget.amounts_in_zl`).
- `resources/js/types/shell.ts:21-49`: `operations: Record<string, string>` — nowe klucze bez zmiany typu. Tylko locale `pl`.

## Code References

- `resources/js/pages/Operations.vue:151,173-208,210-320` – tytuł, puste stany, trzy panele.
- `resources/js/components/OperationsPageActions.vue:46-87` – pięć przycisków, ikony.
- `resources/js/components/OperationTypeTile.vue:28-31,52,59` – kolor i rozmiar kafelka.
- `resources/js/components/OperationsDateCell.vue:11-12`, `OperationDescriptionCell.vue:55,76` – typografia komórek.
- `resources/js/components/OperationsFilters.vue:231-244,389,446,461,629` – query filtrów, stary przepis panelu.
- `resources/js/components/OperationsActiveFilterChips.vue:256` – pigułka filtra.
- `resources/js/lib/format.ts:73-156` – daty, kwoty operacji, kolor kwoty.
- `resources/css/app.css:554-664` – siatka i wiersze Operacji; `:234-291` style i rozmiary ADR-0037; `:332-348` `SegmentedControl`.
- `app/Http/Controllers/OperationsController.php:29-197` – `index()`.
- `app/Support/OperationsListQuery.php:82-99,122-162` – wyciszenie, filtry.
- `app/Support/BudgetMonthResult.php:20-34`, `app/Support/MonthCategoryActuals.php:40-57` – wzorce sumy ze znakiem i grupowania po dniu.
- `scripts/check-ui-literals.mjs:39-53`, `resources/js/pages/dev/OperationsSink.vue`.

## Architecture Insights

- Kontrakt systemu jest w repo (ADR-0035 tokeny, ADR-0037 style i kontrolki); ta zmiana go rozszerza o wiersz dnia, licznik w `SegmentedControl` i rolę koloru kwoty operacji, bez nowej palety.
- Suma dnia to odczyt kwot, więc liczy ją serwer i podaje w groszach (AGENTS.md, „Charts and reports”: kwoty na serwerze w `app/Support`); kopia TS nie jest potrzebna, bo nic nie musi reagować przed zapisem.
- Nowe propsy (`section`, sumy dni, liczby niefiltrowane) zmieniają obie strony w jednym commicie z `assertInertia` (AGENTS.md, „Inertia props contract”).

## Historical Context (from prior changes)

- `context/archive/2026-11-03-operations-ui-contract/` – nazwane klasy wierszy, straż literałów, sink 7 stanów; trzy sekcje i zakaz menu akcji odroczone jako decyzje z issue #19/#35/#80 (`research.md:38,86,104`; `plan.md:13,51-56`). Status: poparte; ta zmiana świadomie odwraca układ i zakaz menu.
- `context/archive/2026-11-05-design-tokens-atrament/` – ADR-0035; pkt 7 i 8 odroczone (`0035-…md:7`). Poparte.
- `context/archive/2026-11-05-budget-atrament-ui/` – ADR-0037, globalny przepis panelu i kontrolek, `table-header`, warianty Badge, Switch; pkt 7 i 8 wyłączone (`plan.md:45`), ręczny `rounded-2xl` w `OperationsFilters.vue:389` zostawiony dla tego widoku (`plan.md:51`). Poparte.
- `context/changes/period-report-by-category/` – rezerwuje ADR-0036; prd-v2 FR-004 prowadzi z raportu do Operacji z filtrem kategorii i okresu (`context/foundation/prd-v2.md:158-159`), więc zakładki nie mogą zgubić filtrów z linku.

## Related Research

- `context/archive/2026-11-03-operations-ui-contract/research.md`
- `context/archive/2026-11-05-budget-atrament-ui/research.md`

## Open Questions

Rozstrzygnięte przez użytkownika 2026-11-05 (pytania po researchu):

1. **Wyciszona zakładka** zostaje wybrana, licznik 0; panel pokazuje pusty stan z notką o wyciszeniu (`filter_suppressed_sections`) i „Wyczyść filtry”.
2. **Zły `section`**: walidacja jak inne filtry (`Rule::enum`, przekierowanie z błędem sesji), z testem.
3. **Filtry** w panelu aktywnej zakładki, nad wierszami, jeden pasek wspólny dla wszystkich zakładek (jak mockup).
4. **Rekomendacje 5 i 6 przyjęte**: suma dnia jak kwoty operacji (`+` `success` / `−` `foreground`), dochód i zwrot `+` `success`.

Brak otwartych pytań blokujących plan.
