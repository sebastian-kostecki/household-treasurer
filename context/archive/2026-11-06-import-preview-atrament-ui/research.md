---
date: 2026-11-06T18:09:45+01:00
researcher: Claude Code (claude-opus-5-5)
git_commit: 838ddc175e506911ba3ddb0abd355765926efa7c
branch: worktree-import-preview-atrament-ui
repository: household-treasurer
topic: "Podgląd importu, krok 2 (OperationsImportPreview.vue) względem karty PageImportPreview design systemu Atrament"
tags:
    [
        research,
        ui,
        atrament,
        import,
        OperationsImportPreview,
        ImportPreviewRow,
        ImportPairCandidateDetector,
        CsvImportPreviewValidator,
        translations,
        badge,
        amounts,
    ]
status: complete
last_updated: 2026-11-06
last_updated_by: Claude Code (claude-opus-5-5)
---

# Research: Podgląd importu, krok 2 w Atramencie

**Date**: 2026-11-06T18:09:45+01:00
**Researcher**: Claude Code (claude-opus-5-5)
**Git Commit**: 838ddc175e506911ba3ddb0abd355765926efa7c
**Branch**: worktree-import-preview-atrament-ui
**Repository**: household-treasurer

## Research Question

Potwierdzić lub odrzucić pięć zarzutów wstępnego audytu `/10x-ui` z `change.md` dla `resources/js/pages/OperationsImportPreview.vue` (z `ImportPreviewRow.vue` i `ImportPreviewSubmitBar.vue`) względem karty `PageImportPreview` w https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA; ustalić, którędy powody pary (`ImportPairCandidateDetector`) i odrzucenia wiersza (`CsvImportPreviewValidator`) trafiają do UI i jaki test Feature wyłapie angielski tekst; sprawdzić warianty `Badge` (`neutral`, `destructive-soft`) i regułę kwot z ADR-0038 dla siatki podglądu.

## Summary

Wszystkie pięć zarzutów się potwierdza; research dokłada szósty (błędny tytuł i cel „Wróć do operacji”):

- **Powody są po angielsku i nie ma ich testu.** Powód pary (`ImportPairCandidateDetector.php:191`, `:230`, data ISO) i 7 powodów odrzucenia (`CsvImportPreviewValidator.php:24`, `:28`, `:32`, `:36`, `:40`, `:44`, `:48`) powstają przy POST podglądu (krok 1), trafiają do sesji (`CsvImportSession::put`, `CsvImportController.php:111-114`) i jedynie `showPreview` (`:139`) podaje je do Inertii; wyświetla je wyłącznie `ImportPreviewRow.vue:608` i `:614`. Repo nie ma `lang/pl.json`, więc `__('English key')` zwraca klucz. Dwie asercje `invalid_reason` (`tests/Feature/CsvImportTest.php:311`, `:349`) porównują z `__()` tego samego klucza — są tautologiczne i przejdą w każdym języku; testy par (`:2843-2889`, `:3139-3186`, `:3702-3760`) nie sprawdzają `reason`.
- **Angielski jest szerszy niż ten widok.** Na sprawdzonej ścieżce kroku 2 angielskie są też błędy `rows.*` z `CsvImportController` i walidatorów importu (pokazuje je `firstRowsError`, `OperationsImportPreview.vue:402`) oraz toast po zatwierdzeniu (`CsvImportController.php:439-459`); w `app/` jest 166 wywołań `__()`/`trans_choice()` z angielskimi kluczami i żadne nie używa `ui.*`. To osobna decyzja (zarzut odroczony D1).
- **Badge:** `neutral` (`resources/js/components/ui/badge/index.ts:27`) i `destructive-soft` (`:21`) istnieją; tokenu `--over` z makiety nie ma — miękka czerwień to `destructive-soft`. Dziś podgląd używa `secondary` i pełnego `destructive` (`OperationsImportPreview.vue:265`, `:273`, `ImportPreviewRow.vue:590`, `:596`), a pełne `destructive` jest w repo tylko na tych dwóch ekranach.
- **Kwoty:** `ImportPreviewRow.vue:363` koloruje kwotę `amountToneClass`, czego `design-system.md:68` zabrania dla kwoty operacji. Reguła ADR-0038 ma gotowe helpery `formatFinancialOperationAmount` / `financialOperationAmountClass` (`resources/js/lib/format.ts:182-207`), ale przyjmują tylko `income` / `expense`; wiersz importu ma pięć typów (`import-pair-selection.ts:14`). Otwarte wybory: kolor według wybranego typu czy znaku z pliku oraz `formatMoney` (z „zł”) czy `formatMoneyAmount` + stopka „Kwoty w zł”.
- **Strażnicy:** pliki widoku są w `check:ui` (`scripts/check-ui-literals.mjs:57-59`, `:67`), ale ten skrypt nie łapie typografii sprzed Atramentu; brak `ImportPreviewHardcodedUiTest`. Sink `/dev/import-preview` ma 7 sekcji, ale tylko na poziomie wierszy i paska zatwierdzania — nie montuje samej strony (tytuł, panel, ostrzeżenia, potwierdzenie duplikatów).
- **ADR-0052** jest poprawnym numerem: 0049, 0050 i 0051 zajmują zmiany w innych worktree (settings, dashboard, term-deposits), w `context/foundation/adr` na `development` najwyższy jest 0048.

## Charges

Każdy zarzut: plik:linia, wpływ na użytkownika, status po researchu.

### C1. Angielskie powody pary i odrzucenia wiersza — **potwierdzony**

- **Gdzie powstają** (przy POST podglądu, `CsvImportController::preview`, `CsvImportController.php:45-117`):
    - powód pary: `financialOperationToCandidate` (`ImportPairCandidateDetector.php:180-196`, zdanie `:191`) i `ownTransferToCandidate` (`:209-235`, zdanie `:230`), z nazwą konta i datą `toDateString()` (ISO);
    - powód odrzucenia: `CsvImportPreviewValidator::validate()` wołany w `CsvImportController.php:92-105`; `$validated` w `:103` to wynik walidatora, nie dane z klienta.
- **Gdzie są przechowywane:** gotowy tekst w sesji (`CsvImportSession::put`, `CsvImportSession.php:17-24`, klucz `csv_import`), nie w bazie. Zmiana sposobu tłumaczenia dotyczy więc tylko nowych podglądów; podgląd zapisany przed wdrożeniem niesie stary kształt.
- **Gdzie trafiają do UI:** jedynie `showPreview` (`CsvImportController.php:119-147`, `'rows' => $import['rows']` w `:139`). `store` (`:149-354`) czyta wiersze z sesji i z kandydatów używa tylko `kind`, `id`, `account_id` (`normalizePairCandidates`, `:394-419`); `StoreCsvImportRequest` (`:22-33`) nie ma `reason` ani `invalid_reason`.
- **Konsumenci we froncie:** typy `PairCandidate.reason` (`resources/js/lib/import-pair-selection.ts:9`) i `ImportRow.invalid_reason` (`resources/js/lib/import-preview-rows.ts:17`); render w `ImportPreviewRow.vue:605-608` i `:611-614`; `hasNotes` (`:176-177`) sprawdza tylko obecność. Etykieta opcji pary `pairCandidateOptionLabel` (`ImportPreviewRow.vue:224-231`) już składa tekst po stronie klienta z `account_name`, `formatDate(date)` i `t.operations.import_pair_kind_*` — nie używa `reason`.
- **Fikstury** mają ręcznie wpisane polskie zdania (`resources/js/lib/import-preview-fixtures.ts:21`, `:76`), więc sink i testy JS nie pokazują tego, co wysyła serwer.
- **Wpływ:** pod wierszem kandydata do pary użytkownik czyta „Oszczędnościowe (2026-11-02): existing income or expense will become this own transfer.”, a pod wierszem niepoprawnym np. „Invalid or missing date.”. Wzorzec: „Oszczędnościowe (02.11.2026): istniejący dochód lub wydatek stanie się tym transferem własnym.” i „Brak daty operacji w wierszu pliku.”.
- **Dlaczego nikt nie zauważył:** `config/app.php:81` `locale = pl`, `:83` `fallback_locale = en`; `lang/` ma tylko `pl/ui.php`, `pl/pagination.php` i `vendor/backup`, brak `pl.json`. Polski trafia do UI tylko przez `UiTranslations::all()` (`app/Support/UiTranslations.php:10-20`) → prop `translations` (`HandleInertiaRequests.php:50`) → `t.<grupa>.<klucz>`.
- **Testy dziś:** `CsvImportTest.php:311` (`__('Only PLN amounts are supported.')`) i `:349` (`__('The operation date cannot be in the future.')`) są tautologiczne; testy par `:2843-2889` (financial_operation), `:3139-3186` (own_transfer), `:3702-3760` (kilku kandydatów) asertują `kind` i `account_id` (`:2884-2887`), nie `reason`. `tests/Unit/ImportPairCandidateDetectorTest.php` testuje tylko okno dat.
- **Gdzie postawić czerwony test:** `tests/Feature/CsvImportTest.php`.
    - Odrzucenie: w `test_invalid_rows_are_marked_with_reason` (`:315-353`) i `test_operation_shaped_row_with_foreign_currency_is_invalid` (`:282-313`) zastąpić `__()` oczekiwaną wartością (polski tekst albo kod powodu, zależnie od decyzji P1).
    - Para: rozszerzyć `test_cross_account_import_flags_pair_candidate_for_financial_operation` (`:2843-2889`) i bliźniaka own_transfer (`:3139-3186`). Arrange do skopiowania: `createImportFixture` (`:4024-4043`), drugie konto przez `Account::factory()`, `previewCsv` (`:4045-4059`), `store` pierwszej nogi, `previewCsv` drugiego konta, GET `operations.import.preview`. Fabryka `FinancialOperation` nie ma stanu tożsamości importu, więc pierwsza noga musi przejść przez prawdziwy podgląd i zatwierdzenie.
- **Precedens:** ADR-0043 (`context/foundation/adr/0043-dashboard-layout-follows-atrament.md:21`): zdanie powiadomienia jest składane w komponencie z pól, które payload już niesie, z tekstem w `lang/pl/ui.php` (`DashboardNotices.vue:159-177`).

### C2. Tytuł i panel podsumowania poza Atramentem — **potwierdzony**

- `OperationsImportPreview.vue:219` `h1.text-lg font-semibold`, `:222` `text-muted-foreground text-sm` zamiast `page-title` / `meta` (`resources/css/app.css:247`, `:265`). Kanoniczny wiersz tytułu po ADR-0048: `OperationsImport.vue:193-208` (`h1.page-title` `:195`, `p.meta` `:198`, `Button outline` z `ArrowLeft aria-hidden` `:202-207`).
- `:226-230` „Wróć do operacji” bez ikony; w stanie pustym `:241` ikona jest, ale bez `aria-hidden`. ADR-0048 już mówi, że krok 2 ma `outline` z `ArrowLeft`.
- `:250-301` `Card` + `CardTitle class="text-base"` + `CardDescription`; `text-base` jest na liście zakazów `ImportStartHardcodedUiTest.php:17-31`. Zastrzeżenie `:297` w `text-xs`.
- Przepis na panel tylko do odczytu: `.form-panel*` (`app.css:364-378`) jest dla formularza (szerokość 640 px, ADR-0048) i nie pasuje do panelu nad listą na pełną szerokość. Pasuje `ListSurface` ze slotem `#header` (`h2.section-title` + `p.meta`), jak `MonthSummaryCard.vue:88-97` (bez `flush`) albo `DashboardExecutionPanel.vue:77-89` (`flush`). Prop `title` w `ListSurface` renderuje `CardTitle class="text-base"` (`ListSurface.vue:46`) — nie używać. Klasy nagłówka panelu niezwiązanej z formularzem w `app.css` nie ma.
- Wpływ: tytuł kroku 2 wygląda inaczej niż krok 1 i reszta aplikacji; panel podsumowania ma inną hierarchię niż panele Atramentu.

### C3. Odznaki i kwoty niezgodne ze wzorcem — **potwierdzony, z dwiema decyzjami**

- **Warianty `Badge`** (`resources/js/components/ui/badge/index.ts:6-35`): `default`, `secondary`, `destructive` (pełne tło, `:15`), `outline`, `warning`, `destructive-soft` (`bg-destructive/10 text-destructive`, `:21`), `ok`, `brand`, `neutral` (`bg-muted text-muted-foreground`, `:27`). `destructive-soft` używa `destructive` na 10%, nie tokenu `--destructive-soft` (`app.css:134`, `:199`). Tokenu `--over` nie ma w `resources`, `context/foundation` ani `.ai`.
- **Reguły:** `.ai/rules/js.md` wymienia warianty pigułek statusu warning / destructive-soft / ok / brand / neutral / outline — bez `secondary` i `destructive`; `variant="destructive"` tylko dla prawdziwych błędów (`AlertError`) i usuwania. `design-system.md:52`: `destructive-soft` to tło pigułek odpowiedniej wagi.
- **Dziś:** `OperationsImportPreview.vue:265` `secondary`, `:273` `destructive`, `:281`, `:289` `outline`; `ImportPreviewRow.vue:582-587` `outline` (duplikat, para), `:590` `secondary` (Poprawny), `:596` `destructive` (Niepoprawny). Pełne `destructive` jako odznaka występuje w repo tylko w tych dwóch plikach.
- **Mapowanie na wzorzec:** „Poprawne / Poprawny” → `neutral`; „Niepoprawne / Niepoprawny” → `destructive-soft`; duplikat i para → `outline`. `destructive-soft` oznacza dziś w repo tylko przekroczenie (`BudgetCategoryRow.vue:93`, `CategoryMeterRow.vue:85`, `DashboardExecutionPanel.vue:141`, `DashboardNotices.vue:321`); użycie dla wiersza niepoprawnego poszerza to znaczenie — zapisać w ADR-0052.
- **Kwota dziś:** `ImportPreviewRow.vue:361-375` `amountToneClass(row.amount_minor_units ?? 0)` + `formatMoney`: wydatek czerwony z ASCII „-” (`format.ts:13`), dochód zielony bez „+”. `design-system.md:68` zabrania `amountToneClass` dla kwoty operacji; ADR-0038:3: dochód i zwrot `+` w `success`, wydatek `−` w `foreground`.
- **Helpery Operacji:** `formatFinancialOperationAmount` (`format.ts:182-193`) i `financialOperationAmountClass` (`:198-207`), użyte w `FinancialOperationRow.vue:74-89`; transfer własny i ruch kapitałowy pokazują `formatMoney` bez koloru (`OwnTransferRow.vue:56-57`, `CapitalMovementRow.vue:63-64`). Typy wiersza importu: `income | expense | refund | own_transfer | capital_movement` (`import-pair-selection.ts:14`) — `refund` trzeba zmapować (`('expense', -abs)`), a dla transferu i ruchu kapitałowego jedynym źródłem kierunku jest znak z pliku.
- **Znak z pliku a typ:** domyślny typ wynika ze znaku (`defaultRowType`, `import-preview-rows.ts:46-54`), ale select pozwala wybrać dowolny typ (`ImportPreviewRow.vue:381-408`), a serwer zapisuje `abs()` dla income/expense i `-abs()` dla refund (`ImportFinancialOperations.php:249-253`). Rozjazd jest możliwy, gdy typ przeczy znakowi (obciążenie jako dochód/zwrot, uznanie jako wydatek). Decyzja P2.
- **Jednostka:** `js.md` („Amounts go through formatMoney”) każe w komórkach tabel używać `formatMoneyAmount` + stopka „Kwoty w zł” (ADR-0037), z wyjątkami tylko dla Operacji (ADR-0039) i Kont (ADR-0044). Podgląd nie jest wyjątkiem; wzorzec pokazuje „−64,00 zł”. Decyzja P3. Komórka `.import-preview-cell-amount` (`app.css:1046-1049`) to `text-right font-medium tabular-nums`, nie styl `amount` jak `.operations-cell-amount` (`:991-994`); kolumna kwoty na `xl` ma 6.5rem (`:1022-1031`).
- Wpływ: pełna czerwień przy „Niepoprawny” i czerwone wydatki czytają się jak błędy lub przekroczenia; ten sam wydatek wygląda inaczej w podglądzie i na liście Operacji.

### C4. Ręczne bloki zamiast gotowych elementów — **potwierdzony**

- Potwierdzenie duplikatów `OperationsImportPreview.vue:387-400`: `div.flex items-center gap-2` + `Checkbox` + `Label`; `InputError` w `:401-402` bez `id` i bez `aria-describedby` na checkboksie. Przepis: `.form-check-row` (`app.css:384-386`), wzorzec użycia z błędem `ImportColumnMapping.vue:48-71` (natywny `<label for>`, `InputError :id size="xs"` pod wierszem, `aria-describedby` / `aria-invalid` na `Checkbox`). `firstRowsError` (`:402`) to błąd listy, nie checkboksa — zostaje osobno, przy liście lub pasku zatwierdzania.
- Data `ImportPreviewRow.vue:344` `text-sm font-medium`; nazwanej klasy daty brak (`.import-preview-cell-date`, `app.css:1037`, ustawia tylko obszar siatki); `tabular-nums` używany w `MonthPlanItemRow.vue:120`, `TermDeposits.vue:121`.
- Podpisy `text-muted-foreground text-xs`: `ImportPreviewRow.vue:499`, `:570`, `:606`, `:612`, `ImportPreviewSubmitBar.vue:29` → `meta text-muted-foreground` (wzorce `OperationDescriptionCell.vue:60`, `:79`, `MonthPlanItemRow.vue:97`, `:116`). `:505` `Label class="text-xs"` (kwota odsetek) → `.form-field-label` lub `FormField`.
- Checkbox wiersza `ImportPreviewRow.vue:319-327` `class="size-6 rounded-md"` — jedyne nadpisanie rozmiaru `Checkbox` w repo (baza `size-4`), bez tokenu i ADR; dopuszczalne literalnie, ale jednorazowe. Decyzja P4.
- Ostrzeżenia strony `OperationsImportPreview.vue:303-326` (`Alert variant="warning"`) i ostrzeżenia w wierszach są zgodne z `js.md` i wzorcem — **bez zmiany**.
- Pasek `ImportPreviewSubmitBar.vue:17-33`: główny przycisk „Zatwierdź import” do prawej, spinner, powód blokady przez `aria-describedby`; brak ikony `Check`, którą ma wzorzec.
- Wiersz `.import-preview-row` (`app.css:1082-1084`) ma `border-border/60` — wzorzec zakazany w `ImportStartHardcodedUiTest.php:17-31` (`border-border/N`).
- Wpływ: niespójna typografia wierszy i potwierdzenie duplikatów, którego błąd nie jest powiązany z polem (czytnik ekranu go nie łączy).

### C5. Brak strażnika i niepełny kitchen sink — **potwierdzony**

- `scripts/check-ui-literals.mjs:57-59` (trzy pliki widoku), `:65-66` (`CategorySelect`, `AccountSelect`), `:67` (`ImportPreviewSink.vue`); `resources/js/lib/import-preview-fixtures.ts` nie jest na liście i ma 4 kolory hex (`:114`, `:122`, `:133`, `:141`; fikstury Dashboardu używają `DEFAULT_CATEGORY_COLOR`, `lib/category-appearance.ts:145`). Skrypt łapie literały, nie typografię.
- Brak `tests/Unit/ImportPreviewHardcodedUiTest.php`. Wzorzec: `ImportStartHardcodedUiTest.php` (lista plików `:9-15`, zakazy `:17-31`, skaner `:36-49`, trzy testy `:51`, `:65`, `:71`; w `composer ci:check` przez `php artisan test`). Jego docblock odnotowuje dług F6 (wspólny helper strażników); piąta kopia go powiększy. Dziś pliki kroku 2 łamią te zakazy w: `OperationsImportPreview.vue:219` (`text-lg`), `:252` (`text-base`), `ImportPreviewSink.vue:131` (`text-xl`), `:138`, `:226`, `:240`, `:262`, `:271` (`text-base`); `text-xs` / `text-sm` nie są zakazane, więc strażnik kroku 2 potrzebuje dodatkowych wzorców (np. `text-(xs|sm)` w widoku, `font-semibold`, `variant="(secondary|destructive)"` przy `Badge`, `amountToneClass`).
- Sink: `routes/web.php:47-51` `Route::inertia('dev/import-preview', ...)` w `if (app()->environment('local'))` (starszy styl; nowszy to kontroler z `abort_unless(app()->isLocal(), 404)`, `Dev/ImportStartKitchenSinkController.php`). `ImportPreviewSink.vue` ma sekcje: default `:63-70`, hover `:72-78`, focus-visible `:80-86` (zwykły `Button`, nie pasek), disabled `:88-94` + `:208-222`, error `:225-237`, empty `:239-259`, loading `:261-268`, „Kontrakt” `:270-355` (`text-sm font-medium`, data ISO). Brakuje: samej strony (tytuł, panel z odznakami i zastrzeżeniem, oba ostrzeżenia, wiersz potwierdzenia duplikatów w default/focus/error), strony w stanie `processing` i tabeli macierzy jak w kroku 1 (`ImportStartKitchenSink.vue:63-96`, który montuje prawdziwą stronę przez `defineAsyncComponent`, `:15-17`).
- Testy: `tests/Feature/DevImportPreviewSinkTest.php` ma jeden przypadek (404 poza `local`); `ImportStartKitchenSinkTest.php` ma trzy (gość, 404, render z `assertInertia`). Strona `OperationsImportPreview` jest asertowana w `CsvImportTest.php:64`, `:241`, `AccountPickerTest.php:109`, `Nfr/NfrPerf03ImportPreviewTest.php:125-134`; testu komponentu strony brak.
- Wpływ: kolejny agent może przywrócić style sprzed Atramentu bez czerwonego testu, a stany strony (nie wierszy) nie mają miejsca do przeglądu.

### C6. Tytuł strony i cel „Wróć do operacji” — **nowy, potwierdzony**

- `<Head>` (`OperationsImportPreview.vue:214`), h1 (`:220`) i okruszek (`:186`) używają `t.operations.import_preview` = „Pokaż podgląd” (`lang/pl/ui.php:621`), czyli etykiety przycisku z kroku 1. Wzorzec też ma h1 „Pokaż podgląd” i panel „Podgląd importu” (`import_summary_title`, `ui.php:624`) — tytuł jest zgodny z kartą, ale brzmi jak polecenie, nie nazwa strony. Decyzja P5 (zostawić jak we wzorcu albo nowy klucz).
- „Wróć do operacji” (`import_back`, `ui.php:585`) prowadzi w kroku 2 do `CsvImportController.create.url()` (`:227`, `:240`), czyli do kroku 1; w kroku 1 ta sama etykieta prowadzi do `operationsRoute()` (`OperationsImport.vue:203`). Wpływ: użytkownik klika „Wróć do operacji” i ląduje na formularzu importu. Decyzja P5.

## Detailed Findings

### Ścieżka tłumaczeń i dat

- PHP: brak użyć `__('ui.…')` w `app/` (wyszukanie `(__|trans|trans_choice)('ui.`); 166 wywołań `__()`/`trans_choice()` używa angielskich kluczy zdaniowych. Brak helpera daty `dd.mm.rrrr` w PHP (wyszukanie `d.m.Y`, `isoFormat`, `translatedFormat` w `app/`); payloady niosą daty ISO.
- JS: `formatDate` (`resources/js/lib/format.ts:84-92`) daje `dd.mm.rrrr`; `ImportPreviewRow.vue:185-187` (`rowDate`) i `:230` (etykieta opcji pary) już go używają.
- Kandydat do pary niesie już `account_name`, `date` (ISO), `kind`, `converts_financial_operation` (`ImportPairCandidateDetector.php:183-195`, `:222-234`) — wystarczy do złożenia zdania po stronie klienta z dwóch kluczy (`financial_operation` → „istniejący dochód lub wydatek stanie się tym transferem własnym”, `own_transfer` → druga tożsamość importu dołączy do istniejącego transferu).

### Inne angielskie teksty na ścieżce kroku 2 (poza zakresem, D1)

- Błędy wierszy pokazywane przez `firstRowsError` (`resources/js/lib/import-preview-errors.ts`, render `OperationsImportPreview.vue:401-402`): `CsvImportController.php:175`, `:182`, `:188`, `:202`, `:211`, `:217`, `:229`, `:255`, `:263`, `:269`, `:293`, `:299`, `:305`, `:336`; `ImportFinancialOperations.php:74` i przekazywane z walidatorów `FinancialOperationImportValidator`, `CapitalMovementInterestImportValidator`, `OwnTransferImportValidator`, `CapitalMovementImportValidator`, `AttachImportPairOwnTransfer`, `ConvertImportPairFinancialOperation`; domyślne komunikaty Laravela dla reguł `StoreCsvImportRequest` (brak `lang/pl/validation.php`).
- Toast po zatwierdzeniu: `CsvImportController.php:439-459` (`trans_choice(':count financial operation imported.|…')`).

### Siatka podglądu

- `.import-preview-grid` (`app.css:1000-1009`, `md` `:1011-1020`, `xl` `:1022-1031`), `.import-preview-header-row` (`:1072-1080`), `.import-preview-row` (`:1082-1084`), `-duplicate` (`:1086-1088`, `bg-warning/10`), `-pair` (`:1090-1092`, `bg-pair/10`). Token `--pair` `#3b9fd8` / `#5bb6ea` (`app.css:143`, `:208`), alias `:61`; znaczenie: tylko tint wiersza (`design-system.md:51`, `.ai/rules/js-import.md`).

## Code References

- `app/Support/CsvImport/ImportPairCandidateDetector.php:191`, `:230` — angielskie zdania powodu pary z datą ISO
- `app/Support/CsvImport/CsvImportPreviewValidator.php:24-48` — 7 angielskich powodów odrzucenia
- `app/Http/Controllers/CsvImportController.php:92-114` — walidacja wierszy, detektory, zapis do sesji
- `app/Http/Controllers/CsvImportController.php:119-147` — `showPreview`, jedyne wyjście powodów do Inertii
- `resources/js/components/ImportPreviewRow.vue:605-614` — render obu powodów
- `resources/js/components/ImportPreviewRow.vue:224-231` — etykieta opcji pary składana na kliencie
- `resources/js/components/ImportPreviewRow.vue:361-375` — kwota z `amountToneClass`
- `resources/js/components/ImportPreviewRow.vue:580-598` — odznaki statusu
- `resources/js/pages/OperationsImportPreview.vue:214-301` — Head, tytuł, panel podsumowania
- `resources/js/pages/OperationsImportPreview.vue:387-402` — potwierdzenie duplikatów i błędy
- `resources/js/components/ui/badge/index.ts:6-35` — warianty `Badge`
- `resources/js/lib/format.ts:84-92`, `:182-207` — `formatDate`, helpery kwot Operacji
- `resources/js/lib/import-preview-rows.ts:9-22`, `:46-54` — model wiersza, domyślny typ
- `resources/js/lib/import-preview-fixtures.ts:21`, `:76`, `:114-141` — polskie powody w fiksturach, kolory hex
- `resources/js/pages/dev/ImportPreviewSink.vue:63-355` — sekcje sinka
- `tests/Feature/CsvImportTest.php:282-353`, `:2843-2889`, `:3139-3186`, `:4024-4059` — testy powodów i helpery arrange
- `tests/Unit/ImportStartHardcodedUiTest.php:9-71` — wzorzec strażnika
- `scripts/check-ui-literals.mjs:57-67` — lista `check:ui`
- `resources/css/app.css:364-386`, `:1000-1092` — `.form-panel*`, `.form-check-row`, siatka podglądu

## Architecture Insights

- Polski tekst w tym repo żyje po stronie klienta (`lang/pl/ui.php` → `t.*`); serwer wysyła dane, nie zdania. Powody importu są wyjątkiem z czasu sprzed tej konwencji. ADR-0043 to precedens składania zdania w komponencie z pól payloadu.
- Strażnicy widoków Atramentu to dwie warstwy: `check:ui` (literały) i `*HardcodedUiTest` (typografia sprzed Atramentu). Krok 2 ma tylko pierwszą.
- Kitchen sink kroku 1 montuje prawdziwą stronę; sink kroku 2 składa części ręcznie, więc stany strony nie są w nim widoczne.

## Historical Context (from prior changes)

- `context/archive/2026-11-04-import-preview-ui-contract/` — wcześniejszy kontrakt kroku 2. Nadal obowiązuje: token `pair` tylko jako tint; klasy `.import-preview-*` z podpisami `table-header xl:sr-only`; `ariaLabel` dla `CategorySelect`/`AccountSelect`; każdy alert wiersza ma rodzaj w `importRowHasNotes`; ostrzeżenie o duplikatach jako `Alert variant="warning"` tylko przy duplikatach, zastrzeżenie stale w panelu; powód blokady w `ImportPreviewSubmitBar` przez `aria-describedby`; wiersze startują odznaczone; podpowiedź kategorii tylko przez `suggestedCategoryId`. Zastąpione: kolor kwoty przez `amountToneClass` (Decision 2) — ADR-0038 i `design-system.md:68` go wykluczają; D4 („h1 `text-lg` to nie zarzut”) — zastąpione przez ADR-0037 (`page-title`) i ADR-0048. Odroczone: D1 (wspólna klasa wiersza w całej aplikacji) — nadal odroczone; D2 (angielskie powody serwera) — przechodzi do tej zmiany jako C1.
- `context/archive/2026-11-06-import-start-atrament-ui/` i `context/foundation/adr/0048-import-start-follows-page-import-start.md` — wzorzec tej zmiany: wiersz tytułu, `.form-check-row`, strażnik `ImportStartHardcodedUiTest`, sink montujący stronę; dług F6 (wspólny helper strażników) otwarty.

## Related Research

- `context/archive/2026-11-04-import-preview-ui-contract/research.md`
- `context/archive/2026-11-06-import-start-atrament-ui/research.md`

## Decisions for the plan

- **P1. Jak spolszczyć powody.** Rekomendacja: zgodnie z ADR-0043 — powód pary składany w `ImportPreviewRow` z pól kandydata i dwóch kluczy `t.operations.import_pair_reason_*` + `formatDate` (pole `reason` znika z payloadu); powód odrzucenia jako kod (np. `invalid_reason: 'future_date'`) mapowany na `t.operations.import_invalid_*`. Zmienia kształt propa → PHP, typy (`import-pair-selection.ts:9`, `import-preview-rows.ts:17`) i fikstury w jednym commicie z `assertInertia`; czerwone testy: Feature (`CsvImportTest.php` jak wyżej) + Vitest czystej funkcji tekstu powodu + test, że każdy kod ma klucz w `lang/pl/ui.php`. Alternatywa: tłumaczenie na serwerze przez `__('ui.operations.…')` i `format('d.m.Y')` — bez zmiany kształtu propa, ale pierwsze użycie `ui.*` w PHP i data formatowana poza `formatDate`. Podgląd zapisany w sesji przed wdrożeniem ma stary kształt — zdecydować o fallbacku (np. pokazanie surowego tekstu, gdy kod nieznany).
- **P2. Kolor i znak kwoty:** według wybranego typu (zgodne z zapisem, kolor zmienia się przy zmianie typu) czy według znaku z pliku (zgodne z wyciągiem, stały). Transfer własny i ruch kapitałowy mogą iść tylko za znakiem pliku.
- **P3. Jednostka:** `formatMoney` z „zł” (jak wzorzec i Operacje, nowy wyjątek w `js.md`) czy `formatMoneyAmount` + stopka „Kwoty w zł” (ADR-0037); oraz czy komórka kwoty dostaje styl `amount`.
- **P4. Checkbox wiersza 24 px:** usunąć nadpisanie czy zapisać w ADR-0052 jako nazwaną klasę.
- **P5. Tytuł i „Wróć do operacji”:** tytuł „Pokaż podgląd” jak we wzorcu czy nowy klucz; przycisk prowadzi do Operacji (jak etykieta i krok 1) czy etykieta zmienia się na powrót do kroku 1.
- **P6. Strażnik:** piąta kopia `*HardcodedUiTest` z wzorcami dla `text-xs`/`text-sm`, `Badge` `secondary`/`destructive`, `amountToneClass` — czy najpierw spłata F6 (wspólny helper). Plus przeniesienie fikstur na `DEFAULT_CATEGORY_COLOR` i dopisanie ich do `check:ui`.

## Deferred

- **D1. Spolszczenie pozostałych komunikatów serwera** (błędy `rows.*`, walidatory importu, toast, domyślne komunikaty Laravela) — dotyczy całej aplikacji (166 wywołań `__()`), wymaga osobnej decyzji o mechanizmie (np. `lang/pl.json` lub `lang/pl/validation.php`); nie jest częścią restylu jednego widoku.
- **D2. Wspólna klasa wiersza listy** (dawne D1 z `import-preview-ui-contract`) — nadal odroczona.

## Open Questions

- Kartę `PageImportPreview` sprawdzono w źródle artefaktu (`project/components/PageImportPreview/preview.html`, `README.md`), nie w renderze; zrzut ekranu wzorca do porównania przyda się w fazie widoku.
- Czy komunikat odrzucenia „Brak daty operacji w wierszu pliku.” ze wzorca pasuje do obu przypadków `Invalid or missing date.` (brak i niepoprawna data) — copy do decyzji w planie.
