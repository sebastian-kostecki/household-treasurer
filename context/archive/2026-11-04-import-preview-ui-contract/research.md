---
date: 2026-11-04T06:51:51+01:00
researcher: Claude (Opus 5.5) z Sebastianem Kosteckim
git_commit: 47a3cc40850a2a2bc01009c29aaff5c1de638047
branch: development
repository: household-treasurer
topic: "Kontrakt UI podglądu importu CSV (OperationsImportPreview + ImportPreviewRow): zarzuty, kierunki, zależności"
tags: [research, ui-contract, 10x-ui, import, OperationsImportPreview, ImportPreviewRow, app.css, design-system]
status: complete
last_updated: 2026-11-04
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje użytkownika do Open Questions 1–4 (sekcja Decisions)."
---

# Research: Kontrakt UI podglądu importu CSV

**Date**: 2026-11-04T06:51:51+01:00
**Researcher**: Claude (Opus 5.5) z Sebastianem Kosteckim
**Git Commit**: 47a3cc40850a2a2bc01009c29aaff5c1de638047
**Branch**: development
**Repository**: household-treasurer

## Research Question

Zweryfikować pięć zarzutów z wstępnego audytu `/10x-ui import-operations-table` (zapisanych w `change.md`) dla widoku `resources/js/pages/OperationsImportPreview.vue` + `resources/js/components/ImportPreviewRow.vue`: dowody `plik:linia`, wpływ na użytkownika, istniejące tokeny/komponenty/wzorce repo, które powinny je pokryć, decyzje z wcześniejszych przebiegów UI oraz testy i strażnicy, których dotyczy zmiana. Wynik: `## Charges` jako wejście do `/10x-plan`.

## Summary

- Wszystkie pięć zarzutów z audytu potwierdzone na HEAD `47a3cc4`. Dwa okazały się poważniejsze niż w audycie: ostrzeżenie „ujemne Do wydania” w wierszu **nie renderuje się w żadnym** stanie (martwy UI, nie przypadek brzegowy), a zatwierdzenie podglądu zawierającego choć jeden niepoprawny wiersz najpewniej kończy się niewidocznym błędem walidacji `rows.N.type` (wniosek z kodu, niezweryfikowany w runtime).
- Widok jest jedynym miejscem, na które wcześniejsze przebiegi jawnie odroczyły literały: Operacje D9 (`ImportPreviewRow.vue:266`, `OperationsImportPreview.vue:301`), Dashboard (`column-caption`, `amountToneClass` → `ImportPreviewRow.vue:296+`), Plan miesiąca D1 (nagłówek `:301`).
- Repo ma wszystko, czego widok potrzebuje, poza jedną decyzją kolorystyczną: `column-caption`, wzorzec `.operations-header-row` / `.operations-row`, `ui/input`, `IndexEmptyState`, `Alert variant="warning"`, wzorzec powodu blokady z `aria-describedby`, kitchen sink wzorca „C” i `check:ui` w CI. Otwarta decyzja: token tła wiersza „kandydat na parę” (dziś `bg-sky-500/10`, a `transfer` jest zastrzeżony dla kafelków typów).
- `npm run check:ui` na dwóch plikach widoku: 9 trafień (`text-[11px]` ×7, `hover:bg-foreground/[0.03]` ×1, `bg-sky-500/10` ×1). Pliki nie są na liście `FILES` (`scripts/check-ui-literals.mjs:9-51`), więc CI ich nie sprawdza.

## Detailed Findings

### Źródło → widoki (tokeny i klasy, które widok mógłby czytać)

- `column-caption` to jedyny przepis podpisu kolumny (`resources/css/app.css:199-201`; `.docs/design-system.md:18`). Widok go nie używa — 7 ręcznych `text-[11px]` (`OperationsImportPreview.vue:301`; `ImportPreviewRow.vue:296,309,324,337,370,508`).
- Nazwane klasy wiersza listy w `app.css`, każda z `hover:bg-foreground/[0.03]` w `@apply`: `.list-surface-table tbody tr` (271-273), `.budget-category-row` (371-373), `.dashboard-holdings-row` (491-493), `.holdings-list-row` (496-498), `.operations-header-row` / `.operations-row` (504-519). Siatka importu ma `.import-preview-grid` i klasy komórek (567-631), ale **brak** klasy nagłówka i wiersza.
- `.native-select` istnieje (`app.css:204-206`); `.native-input` nie istnieje w `resources/`, `app/`, `tests/`, `.docs/` — jedyne użycie to `ImportPreviewRow.vue:461`.
- Tokeny statusu: `warning` (`app.css:46-47,119-120,162-163`) i `transfer` (`app.css:50,122,165`). `transfer` ma jednego konsumenta — `OperationTypeTile.vue:42` — a `.docs/design-system.md:34` zastrzega go: „Nie używaj do: czegokolwiek poza typem operacji”.
- Komponenty repo importowane przez widok: 9 w stronie (`ListSurface`, `Alert`, `Badge`, `Button`, `Card*`, `Checkbox`, `Label`, `InputError`, `ImportPreviewRow`), 7 w wierszu (`AccountSelect`, `CategorySelect`, `OperationDescriptionCell`, `Alert`, `Badge`, `Checkbox`, `Label`). Brakujące, choć istnieją: `ui/input` (`components/ui/input/Input.vue`), `IndexEmptyState` (`components/IndexEmptyState.vue:2-5,18-23`).

### Widok → źródło (literał i to, co powinno go pokryć)

| Literał | Miejsce | Powinno pokryć |
| --- | --- | --- |
| `text-[11px] font-medium tracking-wider uppercase` (nagłówek) | `OperationsImportPreview.vue:301` | nowa `.import-preview-header-row` z `@apply column-caption` (wzorzec `.operations-header-row`, `app.css:504-506`) |
| `text-muted-foreground text-[11px] xl:hidden` (podpis mobilny) ×6 | `ImportPreviewRow.vue:296,309,324,337,370,508` | `column-caption` (+ `xl:sr-only` zamiast `xl:hidden`, wzorzec `BudgetAmountCell.vue:17-24`) |
| `border-border/60 hover:bg-foreground/[0.03] border-b py-3 …` | `ImportPreviewRow.vue:266` | nowa `.import-preview-row` (wzorzec `.operations-row`) |
| `bg-sky-500/10` | `ImportPreviewRow.vue:269` | token roli — decyzja otwarta (zob. Open Questions) |
| `native-input` (klasa bez definicji) | `ImportPreviewRow.vue:461` | `<Input>` z `components/ui/input` (wzorzec `ExpenseFormDialog.vue:594-601`) |
| `rowAmountClass` (lokalny „znak → kolor”) | `lib/import-preview-rows.ts:212-218`, konsument `ImportPreviewRow.vue:322` | `amountToneClass` (`lib/amount-tone.ts:1-13`) lub typowy kolor listy Operacji (`lib/format.ts:139-148`) — decyzja otwarta |

### Ostrzeżenia w wierszu i na stronie

- **To-spend alert jest martwy.** `hasNotes` (`ImportPreviewRow.vue:157-164`) ma 5 członów, bez `toSpendWarning`; alert (`:551`) siedzi w `<div v-if="hasNotes">` (`:533`). Dla każdego wiersza, dla którego `toSpendWarning !== null`, wszystkie 5 członów jest fałszywych:
  - wymaga wiersza poprawnego, zaznaczonego, typu income/expense/refund z kategorią (`lib/import-to-spend-warnings.ts:32-68`) → człon „invalid” fałszywy;
  - `wouldMakeAccountNegative` zwraca `false` dla typu innego niż `own_transfer` (`lib/import-preview-rows.ts:199`) i `matchingPairCandidate` zwraca `null` dla typu innego niż `own_transfer` (`lib/import-pair-selection.ts:152-158`) → dwa człony fałszywe;
  - ostrzeżenie powstaje tylko dla miesiąca z planem (`lib/entry-to-spend-warning.ts:174-178`), a dla miesiąca z planem strona podaje `monthRemainingWarnings = undefined` (`OperationsImportPreview.vue:346-350`, `monthHasPlan` w `entry-to-spend-warning.ts:81-90`) → dwa człony miesięczne fałszywe.
  - Skutek: ostrzeżenie zaprojektowane w zmianie `plan-based-entry-warnings` nigdy nie dociera do użytkownika importu. Test `lib/import-to-spend-warnings.test.ts` pokrywa tylko czystą funkcję; żaden test nie renderuje `ImportPreviewRow`.
- **Alert duplikatów jest bezwarunkowy** (`OperationsImportPreview.vue:273-281`, wariant domyślny). Treść (`lang/pl/ui.php:570-571`) to stałe zastrzeżenie o granicach wykrywania duplikatów, ale tytuł „Możliwe duplikaty” brzmi jak wynik — przy `duplicateRowCount === 0` wprowadza w błąd. Alert par (`:283-291`) jest warunkowy, też w wariancie domyślnym. `.ai/rules/js.md:24` wymaga `variant="warning"` dla ostrzeżeń nieblokujących, w tym „import duplicates”. `ui/alert/index.ts` ma wariant `warning`.

### Przycisk „Importuj” i błędy zatwierdzenia

- `canSubmit` (`OperationsImportPreview.vue:116-136`) blokuje przycisk (`:374`) w 4 warunkach: `processing`; zaznaczony duplikat bez potwierdzenia (`:113-119`); `selectedRowCount === 0` — stan początkowy, bo `initialSelection` ustawia `selected: false` (`lib/import-preview-rows.ts:57,74`); dowolny zaznaczony wiersz nie przechodzi `rowSelectionValid` (`lib/import-preview-rows.ts:230-252`: przelew bez konta, lokata bez wyboru, błędny podział odsetek, brak kategorii). Dla żadnego z 4 warunków strona nie pokazuje tekstu powodu. Repo ma wzorzec: widoczny `text-xs text-muted-foreground` + `aria-describedby` (`OperationsPageActions.vue:24-25,63-76`; `.docs/design-system.md:62`; `.ai/rules/js.md:62`).
- **Prawdopodobny błąd zatwierdzenia (wniosek z kodu, bez weryfikacji w runtime):** wiersz niepoprawny renderuje ukryte `rows[N][index]` i `rows[N][selected]` (`ImportPreviewRow.vue:283-292`), ale `<select name="rows[N][type]">` tylko gdy `row.valid` (`:341`). `rows.*.type` jest `required` (`app/Http/Requests/StoreCsvImportRequest.php:25`, `prepareForValidation` ustawia `null` przy braku). Strona wyświetla wyłącznie `errors.duplicate_warning_acknowledged` i `errors.rows` (`:370-371`), więc błąd `rows.N.type` byłby niewidoczny — przycisk „nic nie robi”. Testy feature (`tests/Feature/CsvImportTest.php:256-297`) wysyłają ręczne payloady tylko z poprawnymi wierszami. Komunikaty z kontrolera to klucze `__()` po angielsku; w `lang/pl` są tylko `pagination.php` i `ui.php`.

### Dostępność kontrolek wiersza

- Bez dostępnej nazwy (brak `<Label for>` i `aria-label`): checkbox `row-selected-N` (`ImportPreviewRow.vue:275`), select typu (`:340`), select pary (`:386`), select lokaty (`:427`). `CategorySelect` / `AccountSelect` (reka `Select`) nazywają się tylko treścią triggera (placeholder albo wybrana wartość); `aria-label` nie dociera do triggera, bo wrappery nie mają `inheritAttrs: false` i nie deklarują takiego propa (`CategorySelect.vue:48-74`, `AccountSelect.vue:48-72`). Jedyny `<Label for>` w wierszu: kwota odsetek (`:450-455`).
- Podpisy mobilne to `<span>` z `xl:hidden` — na `xl` znikają również dla czytnika ekranu. Precedens: `md:sr-only` w `BudgetAmountCell.vue:17-24`, `sr-only` w `CategoryMeterRow.vue:74`; `:aria-label` dla pola bez etykiety (`budget-view-ui/plan.md:300`).
- `ui/input` przekazuje `id`, `name`, `inputmode`, `placeholder`, `aria-*` do `<input>` (brak `inheritAttrs: false`, `Input.vue:6-19`) i stylizuje `aria-invalid` (`:29`).

### Kolor kwoty

- `rowAmountClass` (`lib/import-preview-rows.ts:212-218`): `< 0` → `text-destructive`, inaczej `text-success` — zero i `null` (wiersz walutowy, `CsvImportController.php:473-480`) na zielono; zero trafia do UI jako wiersz niepoprawny (`CsvImportPreviewValidator.php:47-48`). Narusza `.docs/design-system.md:45` („Zero nigdy nie jest zielone. Nie pisz lokalnych helperów”).
- Lista Operacji koloruje według **typu** (`FinancialOperationRow.vue:79-93` → `lib/format.ts:121-148`), a przelewy i ruchy kapitału bez koloru (`OwnTransferRow.vue:61-63`, `CapitalMovementRow.vue:68-69`). Podgląd importu koloruje według znaku z wyciągu niezależnie od wybranego typu.

### Stany puste i ładowanie

- Pusty podgląd jest osiągalny: parser zwraca `[]` dla pustego pliku (`app/Support/CsvImport/CsvImportParser.php:45-47`), `preview()` odfiltrowuje wiersze bez daty/kwoty (`CsvImportController.php:82-90,473-480`) i zapisuje pustą tablicę bez błędu (`:110-115`). Widok pokazuje wtedy goły `<p>` pod nagłówkiem kolumn (`OperationsImportPreview.vue:326-331`) oraz stały alert duplikatów; `IndexEmptyState` nie jest użyty. Brak testu `has('rows', 0)`.
- Wejście z linku bez sesji: `showPreview()` przekierowuje do `operations.import.create` (`CsvImportController.php:124-126`) — poprawne, bez zarzutu.
- Ładowanie: jedyny sygnał przy zatwierdzaniu to `:disabled="processing"` (`:374`); Operacje dodały spinner z `:aria-label="t.common.loading"` (`operations-ui-contract/plan.md:330`).

### Testy i strażnicy, których dotyczy zmiana

- `tests/Feature/CsvImportTest.php` (90 testów) i `tests/Feature/AccountPickerTest.php:109-111` sprawdzają komponent Inertia i propsy, nie klasy — restyl ich nie złamie, zmiana propsów tak.
- `resources/js/ui-contract.test.ts`: regex HARDCODED (`:10-11`) obejmuje tylko `BUDGET_VIEW_FILES` (`:13-29`); test martwego wiązania checkboxa obejmuje wszystkie `.vue`, wyjątek `pages/OperationsImport.vue` (`:33`, issue #186) — nie dotyczy podglądu.
- `scripts/check-ui-literals.mjs` + `npm run check:ui` uruchamiane przez `composer ci:check` (`composer.json:66-74`) w `.github/workflows/tests.yml`. Brak hooka pre-commit. Po dodaniu plików widoku do `FILES` wszystkie 9 obecnych literałów oblałoby kontrolę (regex obejmuje `/\[0?\.[0-9]+\]`).
- Brak testów zrzutów ekranu i Playwrighta w repo; poprzednie przebiegi robiły zrzuty jednorazowym `npx -p playwright@…` (1280 i 390 px) do `context/changes/<id>/screenshots/` (`operations-ui-contract/plan.md:81-85`).

## Charges

Każdy zarzut: plik:linia, wpływ na użytkownika, kierunek poprawki (do decyzji w `/10x-plan`).

- **C1 [status: fixed (faza 4, klasy z fazy 3)] — Brakujący token: podpisy i nagłówek kolumn.** `OperationsImportPreview.vue:301`; `ImportPreviewRow.vue:296,309,324,337,370,508`. Wpływ: zmiana skali podpisów w systemie nie dociera do importu; na `xl` podpisy znikają też dla czytnika (`xl:hidden`). Kierunek: `.import-preview-header-row` (`@apply column-caption border-border/60 hidden border-b pb-2.5`, `grid` od `xl`) w `app.css` obok `.import-preview-grid`; podpisy mobilne `column-caption xl:sr-only`. Zamyka Operacje D9 (część nagłówka), Dashboard (część `column-caption`) i Plan miesiąca D1.
- **C2 [status: fixed (faza 4, klasy z fazy 3; token `pair` z fazy 3)] — Brakujący token: wiersz i tinty statusu.** `ImportPreviewRow.vue:266` (hover-literał), `:269` (`bg-sky-500/10`, bez wersji dark, poza systemem). Wpływ: kolor oznaczający „to pewnie przelew własny” nie podlega tokenom i nie przełącza się z motywem; hover nie da się dostroić na warstwie tokenów. Kierunek: `.import-preview-row` wzorem `.operations-row`; tint pary jako token roli w `:root`/`.dark` + `@theme inline` (decyzja: nowy token vs. rozszerzenie roli `transfer` — zob. Open Questions); tint duplikatu `bg-warning/10` zostaje (`.ai/rules/js.md:24`).
- **C3 [status: fixed (faza 4)] — Brakujący współdzielony komponent: pole odsetek.** `ImportPreviewRow.vue:456-463` (`class="native-input"` bez definicji). Wpływ: „Kwota odsetek” to surowy input przeglądarki — bez ramki, focus ringa i stanu `aria-invalid`, odstaje od każdego pola w dialogach. Kierunek: `<Input>` z `components/ui/input` (wzorzec `ExpenseFormDialog.vue:594-601`).
- **C4 [status: fixed (faza 4; stan empty w fazie 5)] — Przypadkowa architektura: ostrzeżenia martwe albo stałe.** `ImportPreviewRow.vue:157-164,533,551` (to-spend nigdy nie renderuje); `OperationsImportPreview.vue:273-281` (alert duplikatów bezwarunkowy, tytuł sugeruje wynik), `:273,283` (wariant domyślny zamiast `warning`). Wpływ: użytkownik nie dowiaduje się, że import zrobi ujemne „Do wydania”, a jednocześnie zawsze widzi „Możliwe duplikaty”. Kierunek: dodać `toSpendWarning` do `hasNotes` (+ test komponentu lub test czystej funkcji wyciągniętej z `hasNotes`); alert duplikatów warunkowy na `duplicateRowCount > 0` w `variant="warning"`, zastrzeżenie o granicach wykrywania jako tekst pomocniczy (np. w opisie karty podsumowania); alert par w `variant="warning"`.
- **C5 [status: fixed (faza 4/5)] — Stany i dostępność: blokada bez powodu, kontrolki bez nazw, kolor kwoty.** `OperationsImportPreview.vue:116-136,374` (4 warunki blokady, zero komunikatów); `ImportPreviewRow.vue:275,340,386,427` + `CategorySelect`/`AccountSelect` (brak dostępnej nazwy); `lib/import-preview-rows.ts:212-218` (zero i `null` na zielono). Wpływ: użytkownik nie wie, czemu nie może zatwierdzić importu (w stanie początkowym przycisk jest szary od wejścia); czytnik ogłasza „pole wyboru” bez nazwy w każdym wierszu. Kierunek: powód blokady jako widoczny tekst + `aria-describedby` (wzorzec `OperationsPageActions.vue:63-76`); `aria-label` z nazwą kolumny i datą/opisem wiersza na checkboxie i selectach natywnych; prop `ariaLabel` (lub `aria-labelledby`) w `CategorySelect`/`AccountSelect` przekazany do `SelectTrigger`; `rowAmountClass` zastąpiony przez `amountToneClass` lub kolor typu (decyzja).
- **C6 [status: fixed (faza 2)] — Przypadkowa architektura: niewidoczny błąd zatwierdzenia z wierszami niepoprawnymi (wniosek z kodu).** `ImportPreviewRow.vue:283-292,341`; `StoreCsvImportRequest.php:25,47`; `OperationsImportPreview.vue:370-371`. Wpływ: plik z jednym niepoprawnym wierszem prawdopodobnie nie daje się zaimportować, a strona nie pokazuje żadnego błędu. Kierunek: najpierw test feature (POST z wierszem niepoprawnym, `selected=0`, bez `type`) potwierdzający 422; poprawka po stronie serwera (`type` wymagany tylko dla `selected=true`, np. `required_if:rows.*.selected,true`) albo klienta (nie wysyłać niezaznaczonych/niepoprawnych wierszy); oraz wyświetlanie `errors['rows.*']`. To błąd funkcjonalny — kandydat na osobną zmianę, jeśli plan ma zostać czysto wizualny.
- **Stany do pokrycia w macierzy (nie osobne zarzuty):** empty — `IndexEmptyState` zamiast `<p>` (`OperationsImportPreview.vue:326-331`), bez nagłówka kolumn i bez alertów, z akcją powrotu do wgrywania; loading — spinner w przycisku „Importuj” z `aria-label`; focus-visible — checkbox, selecty, `Input`; disabled — checkbox wiersza niepoprawnego, select lokaty bez kandydatów, przycisk; error — `InputError` dla `rows`, `rows.N.*`, `duplicate_warning_acknowledged`.

### Kandydaci na deferred

Status po zmianie: D1–D4 bez zmian (deferred). Zmiana zamyka odroczenia z wcześniejszych przebiegów: Operacje D9 (część importu: `ImportPreviewRow.vue:266`, `OperationsImportPreview.vue:301`), Dashboard (`column-caption`, `amountToneClass` dla widoku importu) i Plan miesiąca D1 (nagłówek `:301`).

- **D1 [status: deferred] — Wspólna klasa wiersza listy dla całej aplikacji** (kontynuacja Operacje D9): zmiana zostaje przy `.import-preview-row`; wspólna klasa to osobna zmiana obejmująca 6 istniejących klas wiersza.
- **D2 [status: deferred] — Angielskie komunikaty serwera** dla błędów importu (`CsvImportController.php:175-306,335-338`, brak `lang/pl.json`): dotyczy tłumaczeń backendu, nie widoku.
- **D3 [status: deferred] — Ekran wgrywania pliku** (`OperationsImport.vue`, w tym issue #186): poza zasadą „jeden widok na zmianę”.
- **D4 [status: deferred] — Hierarchia nagłówka strony** (`OperationsImportPreview.vue:209` `text-lg`): rozstrzygnięta repo-wide w `.docs/design-system.md:24` jako `text-lg` dla stron domenowych — bez zarzutu.

## Code References

- `resources/js/pages/OperationsImportPreview.vue:116-136` — `canSubmit`, 4 warunki blokady
- `resources/js/pages/OperationsImportPreview.vue:273-291` — alerty duplikatów (bezwarunkowy) i par
- `resources/js/pages/OperationsImportPreview.vue:298-353` — `ListSurface`, nagłówek kolumn `:301`, pusty stan `:326-331`
- `resources/js/pages/OperationsImportPreview.vue:346-350` — `monthRemainingWarnings` tylko dla miesięcy bez planu
- `resources/js/pages/OperationsImportPreview.vue:370-374` — `InputError` i przycisk „Importuj”
- `resources/js/components/ImportPreviewRow.vue:157-164` — `hasNotes` bez `toSpendWarning`
- `resources/js/components/ImportPreviewRow.vue:265-271` — kontener wiersza: hover-literał, `bg-warning/10`, `bg-sky-500/10`
- `resources/js/components/ImportPreviewRow.vue:275-292` — checkbox bez nazwy, ukryte `index`/`selected`
- `resources/js/components/ImportPreviewRow.vue:340-366` — select typu, renderowany tylko dla `row.valid`
- `resources/js/components/ImportPreviewRow.vue:456-463` — `native-input` bez definicji
- `resources/js/components/ImportPreviewRow.vue:533-600` — notatki i alerty wiersza
- `resources/js/lib/import-preview-rows.ts:212-218` — `rowAmountClass`
- `resources/js/lib/import-to-spend-warnings.ts:19-86` — warunki ostrzeżenia to-spend
- `resources/css/app.css:199-201` — `column-caption`; `:504-519` — wzorzec `.operations-header-row` / `.operations-row`; `:567-631` — `.import-preview-grid`
- `app/Http/Requests/StoreCsvImportRequest.php:21-34,40-60` — reguły i `prepareForValidation`
- `scripts/check-ui-literals.mjs:9-51` — lista `FILES` (bez widoku importu)

## Architecture Insights

- Kontrakt repo: wartości w `:root`/`.dark` → `@theme inline`; powtarzalne układy jako nazwane klasy w `@layer components` (siatka + komórki + nagłówek + wiersz per lista); `column-caption` jako `@utility`, żeby dało się go `@apply`. Widok importu ma siatkę i komórki, ale brakuje mu nagłówka i wiersza — to jedyna lista z niepełnym zestawem.
- Kitchen sinki mają trzy wzorce (`routes/web.php:25-28` kontroler + `abort_unless(isLocal)`; `:33-35`; `:42-45` `Route::inertia` w bloku `environment('local')`). Operacje wybrały wzorzec „C” z nienazwaną trasą, bo nazwana trasa tylko-lokalna łamie `types:check` w CI przez Wayfindera (`operations-ui-contract/plan.md:73`; `budget-view-ui/plan.md:66`); fikstury w `resources/js/lib/<view>-fixtures.ts`; test 404 poza local (`tests/Feature/DevOperationsSinkTest.php`). `ImportPreviewRow` jest samodzielnym komponentem z `v-model:selection`, więc nadaje się do sinka bez `setLayoutProps`.
- Strażnicy: `.ai/rules/js.md` (sekcja UI per przebieg, Operacje `:59-62`), `.docs/design-system.md`, `scripts/check-ui-literals.mjs` w `composer ci:check`. `CLAUDE.md` pomijany (blok 10x-cli nadpisywany).

## Historical Context (from prior changes)

- `context/archive/2026-11-03-operations-ui-contract/research.md:70,111` — C2 nazwała wiersz per widok (`.operations-row`), wspólna klasa odroczona (D9) wraz z literałami `ImportPreviewRow.vue:266` i `OperationsImportPreview.vue:301` — **wspiera** C1/C2 tej zmiany.
- `context/archive/2026-11-03-operations-ui-contract/research.md:166-174` — powód blokady jako widoczny tekst + `aria-describedby` (opcja A; tooltip odrzucony, nie działa na dotyku) — **wspiera** kierunek C5.
- `context/archive/2026-11-03-dashboard-ui-contract/research.md:84,118` — podpis mobilny z `ImportPreviewRow.vue:296` podany jako wzorzec mobilnego podpisu; `column-caption` i `amountToneClass` odroczone na widok importu — **częściowo**: wzorzec układu tak, ale literał `text-[11px]` jest dziś zarzutem.
- `context/archive/2026-11-03-month-plan-ui-contract/research.md:70,141,172` — nagłówek `:301` odroczony (D1), brak klasy nagłówka dla `.import-preview-grid` — **wspiera** C1.
- `context/archive/2026-11-02-plan-based-entry-warnings/` — źródło ostrzeżeń to-spend / month-remaining w imporcie; C4 pokazuje, że ostrzeżenie to-spend nie dociera do UI wiersza.
- `context/archive/2026-11-03-operations-ui-contract/plan.md:81-85,61` — zrzuty jednorazowym Playwrightem przez `npx`, Playwright w repo poza zakresem.

## Related Research

- `context/archive/2026-11-03-operations-ui-contract/research.md`
- `context/archive/2026-11-03-dashboard-ui-contract/research.md`
- `context/archive/2026-11-03-month-plan-ui-contract/research.md`
- `context/archive/2026-11-03-accounts-ui-contract/research.md`
- `context/archive/2026-11-03-term-deposits-ui-contract/research.md`

## Decisions (2026-11-04, Sebastian)

Odpowiedzi na Open Questions 1–4, podjęte po zapisaniu badania:

1. **Tło „kandydat na parę” (C2):** nowy token roli (np. `--pair`) w `:root` i `.dark` + `--color-pair` w `@theme inline`, wpis w `.docs/design-system.md`. Zakaz dla `transfer` (`.docs/design-system.md:34`) zostaje bez zmian.
2. **Kolor kwoty (C5):** `amountToneClass` (znak z wyciągu; zero bez koloru). `rowAmountClass` do usunięcia.
3. **C6 w tej zmianie:** najpierw test feature potwierdzający 422, potem poprawka (`type` wymagany tylko dla zaznaczonych wierszy) i wyświetlanie błędów `rows.N.*`.
4. **Nazwy dostępne dla reka `Select` (C5):** opcjonalny prop `ariaLabel` w `CategorySelect` / `AccountSelect`, przekazany do `SelectTrigger`; dialogi bez zmian (dalej `<Label for>`).

## Open Questions

Wszystkie cztery rozstrzygnięte — zob. `## Decisions`.

1. **Token tła „kandydat na parę” (C2).** (a) nowy token roli, np. `--pair` / `--info` w `:root`/`.dark` + `@theme inline`, wpis w `.docs/design-system.md`; (b) rozszerzyć rolę `transfer` o „sugestię przelewu własnego” i użyć `bg-transfer/10` — wymaga zmiany zakazu w `.docs/design-system.md:34`; (c) zrezygnować z tła i zostawić sam `Badge` „Kandydat na parę”. Decyzja produktowa/systemowa dla planu.
2. **Kolor kwoty w podglądzie (C5).** `amountToneClass` (znak z wyciągu, zero bez koloru) czy kolor według wybranego typu jak lista Operacji (`lib/format.ts:139-148`, transfery bez koloru)? Drugi jest spójniejszy z listą, ale zmienia się przy zmianie typu w wierszu.
3. **Zakres C6.** Czy błąd zatwierdzenia z wierszami niepoprawnymi naprawić w tej zmianie (po potwierdzeniu testem), czy otworzyć osobną zmianę i tu tylko wyświetlać `errors['rows.*']`?
4. **Nazwy dostępne dla reka `Select` (C5).** Dodać prop `ariaLabel` do `CategorySelect`/`AccountSelect` (dotyka komponentów współdzielonych z dialogami) czy użyć `aria-labelledby` na id nagłówka kolumny / podpisu mobilnego?
