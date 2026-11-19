# Jeden dzień aplikacji (App Day) — plan implementacji

## Overview

Nowy ADR-0103 i termin słownika **App Day** ustalają, że „dziś” dla aplikacji to dzień kalendarzowy bieżącej chwili w strefie Europe/Warsaw. Serwer bierze go z jednego źródła, znaczniki czasu zostają w UTC, a przeglądarka dostaje ten dzień od serwera w `shell` i nie czyta własnego zegara do decyzji domeny. Siedem starszych ADR, które mówią „dziś w UTC” albo „rok z przeglądarki”, dostaje linię „Superseded in part by ADR-0103”. To fundament F-08 z M-5 (MS-01): wycinki S-39, S-40, S-41 i S-43 budują na tej odpowiedzi. AGENTS.md wymaga, żeby zmiana reguły zaczynała się od słownika i ADR, a dopiero potem był kod.

Zmiana dotyczy tylko dokumentów w `context/foundation/`. Kod, README i testy zmienia S-39 (`one-app-day`).

## Current State Analysis

W aplikacji działają trzy zegary, a próba przełomu roku (`context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md`, krok T2, luka 4) pokazała ich rozjazd: o 00:30 czasu polskiego 1 stycznia 2027 menu roku oznacza 2027 jako „bieżący”, a Dashboard i domyślna data dialogu operacji pokazują jeszcze 31 grudnia 2026.

- **Serwer: UTC.** `config/app.php:68` ma `'timezone' => 'UTC'` na sztywno, obraz produkcyjny `TZ=UTC` (`docker/8.5/Dockerfile.prod:15,20`). W `app/` jest 20 wywołań `CarbonImmutable::today('UTC')`, 15 reguł `before_or_equal:today` (rozwiązywane w strefie PHP, dziś UTC) i cztery odczyty inne niż reszta: `CsvImportPreviewValidator.php:21` (`CarbonImmutable::today()` bez strefy, wołane bez `$today` z `CsvImportController.php:93`), `ArchiveCategory.php:21,30` (`now()` jako `archived_at` i jako rok Budżetu), `RestoreCategory.php:28` (`now()->year`), `PreviewBalanceReconciliationRequest.php:47` (`now()->toDateString()`). W Europe/Warsaw działa tylko harmonogram kopii (`bootstrap/app.php:39,46,53`, ADR-0013).
- **Przeglądarka: dwa zegary.** 7 dialogów bierze domyślną datę z `new Date().toISOString().slice(0, 10)`, czyli z dnia UTC. Siedem innych miejsc liczy w czasie lokalnym (menu roku, paleta, dialog nowego Budżetu, preset filtra „ten miesiąc”, etykiety dni, ekran logowania, stopka strony powitalnej). Nie ma wspólnego helpera ani współdzielonego propsa „dziś”. `shell` (`HandleInertiaRequests.php:48`) idzie z całą grupą `web`, także do gościa (`ShellState.php:25-45` zwraca wtedy nulle), jako zwykła tablica przy każdej pełnej wizycie; częściowe przeładowania z `only` (`Categories.vue:181,471-478`, `BudgetCategoryList.vue:306-313`) go nie odświeżają.
- **Dokumenty.** Słownik mówi „(UTC)” w dwóch wpisach Period Report (Last twelve months, Years) i nigdzie nie definiuje, czym jest „today”. ADR-0074, 0075, 0076, 0077, 0081 i 0097 ustalają „dziś w UTC” każdy na własną rękę, ADR-0098 bierze „bieżący” rok z `new Date().getFullYear()`. README („Przełom roku”, `README.md:285-289`) opisuje obecne zachowanie: rok zmienia się o 01:00 czasu warszawskiego, a twardy termin grudnia to 31.12 23:59:59 UTC.
- **Dane.** Daty biznesowe to kolumny `date` bez godziny (np. `operation_date`, `reconciled_on`), a SQL nigdy nie pyta bazy o bieżącą datę. Znaczniki czasu to `timestamp without time zone` zapisane w strefie aplikacji (UTC). Miesiąc archiwizacji Kategorii nie jest zapisany osobno: dwie decyzje domeny wyprowadzają go ze znacznika `archived_at`, porównując go z pierwszym dniem miesiąca w UTC (`MonthPlanProposal.php:180-184`: Kategoria wypada z Propozycji, `IrregularCollected.php:168`: linia przestaje zbierać), a `CategoryListReading.php:75` pokazuje `archived_at->toDateString()` jako datę UTC.

### Key Discoveries:

- Konwencja zastępowania: linia „Superseded in part by ADR-NNNN: …” zaraz po pierwszym akapicie starszego ADR (`context/foundation/adr/0003-wallet-master-surface-recipe.md:5`, `0016-operations-list-filters-by-category-name-and-type.md:4`).
- Następny wolny numer ADR to 0103 (ostatni: `0102-sheet-load-of-2019.md`); zdublowane 0008 to osobny wycinek S-46.
- Konsumenci „dziś” w `app/Support` przyjmują `$today` jako parametr (`YearElapsed`, `MonthElapsed`, `MonthPlanProposal`, `ReportYearsRange`, `ReportTwelveMonthWindow`, `MonthPace` i inni), więc jedno źródło dnia wpina się w kontrolerach i czytnikach, bez zmiany obliczeń.
- `EntryToSpendInputs.php:45`, `HandEntryMonthRemainingInputs.php:30` i `ImportMonthRemainingContext.php:35` mają `$today ??= today('UTC')`, a wywołujący nigdy nie podają dnia, więc odczyt zegara jest ukryty w parametrze domyślnym.
- Operacje dostają już dzień z serwera (`handEntryMonthRemaining.today`, `HandEntryMonthRemainingInputs.php:83`), ale `ExpenseFormDialog.vue:439` i tak liczy domyślną datę z zegara przeglądarki.
- `BalanceReconciliationDialog.vue:48` liczy `today` w `computed` bez zależności: dialog otwarty przez północ zostaje na wczorajszym dniu.
- Zmiana czasu w Polsce następuje o 02:00 i 03:00, więc północ w Europe/Warsaw zawsze istnieje i jest jedna; doba ma wtedy 23 albo 25 godzin.
- `ADR-0031` mówi tylko „today's calendar year” bez zegara, `ADR-0087` nie ustala zegara: oba dziedziczą App Day bez edycji.

### Spis miejsc w kodzie (wejście dla S-39 `one-app-day`)

Stan z 2026-11-18 (gałąź `development`, e50f8f6). S-39 sprawdza go ponownie przed planem.

Serwer, odczyt „dziś” dla domeny:

- Kontrolery: `DashboardController.php:35`, `BudgetController.php:63`, `MonthPlanController.php:56,95,122`, `ReportController.php:53`, `CategoryController.php:38`, `CategoryListController.php:28,42`, `CategoryMergeController.php:23,29`.
- Czytniki z własnym zegarem: `SelectedBudgetYear.php:36` (rok w `shell.budgetYear`), `CategoryListReading.php:53`, `EntryToSpendInputs.php:45`, `HandEntryMonthRemainingInputs.php:30`, `CsvImport/ImportMonthRemainingContext.php:35`, `CsvImport/CsvImportPreviewValidator.php:21`.
- Akcje: `CreateListCategory.php:61`, `UpdateListCategory.php:68`, `CreateCategory.php:67`, `RestoreUserData.php:639`, `ArchiveCategory.php:21,30` (rozdzielić `archived_at` od roku), `RestoreCategory.php:28`.
- Znacznik czasu jako dzień (bez odczytu zegara, grep po `now()`/`today()` ich nie znajdzie): `MonthPlanProposal.php:180-184` i `IrregularCollected.php:168` (`archived_at` < pierwszy dzień miesiąca w UTC), `CategoryListReading.php:75` (`archived_at->toDateString()`, wyświetlane w `CategoryListRow.vue:56`).
- Walidacja: `PreviewBalanceReconciliationRequest.php:47`; `before_or_equal:today` w `Concerns/IncomeValidationRules.php:21,58`, `ExpenseValidationRules.php:21,58`, `OwnTransferValidationRules.php:17`, `CapitalMovementValidationRules.php:20`, `OperationRebuildValidationRules.php:30,69,112,150,188`, `CsvImport/FinancialOperationImportValidator.php:92,118`, `OwnTransferImportValidator.php:25`, `CapitalMovementImportValidator.php:25`.
- Poza `app/`: migracje `2026_11_10_120001_backfill_category_rhythms.php:22` i `2026_11_10_120002_drop_rhythm_flags_from_budget_lines.php:41` (historyczne, bez zmian), seedery i fabryki (`DemoHouseholdSeeder.php`, `BudgetLineFactory.php:92-93`, `CategoryFactory.php:43-44`).
- Zegar próby: `RehearsalClock.php` (`Date::setTestNow`, wejście w UTC), `RehearsalClockCommand.php:100-101` (pokazuje oba zegary).

Przeglądarka, odczyt „dziś” dla domeny:

- Domyślna data UTC: `IncomeFormDialog.vue:332`, `ExpenseFormDialog.vue:439`, `OwnTransferFormDialog.vue:182`, `CapitalMovementFormDialog.vue:222`, `TermDepositFormDialog.vue:118`, `AccountFormDialog.vue:105`, `BalanceReconciliationDialog.vue:48` (też `:max` w `:147` i `reconciliationDayIsAllowed` w `lib/balance-reconciliation.ts:19-26`).
- Rok i miesiąc lokalnie: `BudgetYearSwitcher.vue:82` (prop `currentYear` istnieje, `AppSidebar.vue:73-76` go nie podaje), `composables/useCommandPalette.ts:132`, `CreateBudgetDialog.vue:55`, `OperationsFilters.vue:180-190`, `lib/format.ts:199-215` (`formatDayLabel`, wołane z `OperationsDayRow.vue:17`).
- Strony gościa (`shell` dochodzi i do nich): `layouts/auth/AuthCardLayout.vue:18` z logiką w `lib/auth-year-progress.ts:16-24`, `pages/Welcome.vue:26`. Martwy kod: `lib/format.ts:137-180` (`formatRelativeDate`).
- Kitchen sink z żywym zegarem: `lib/operation-dialogs-fixtures.ts:27-35` (`/dev/...` dialogów).

Testy przypięte do północy UTC: `tests/Feature/YearTurnTest.php` (`test_december_can_be_approved_up_to_the_last_second_of_utc`, `test_december_cannot_be_approved_from_midnight_utc_and_january_takes_the_annual_share` i inne z `today('UTC')`), `BudgetCollectedAtYearStartTest.php:169`, `RehearsalClockTest.php:44,84`, `SelectedBudgetYearTest.php:73,82`; e2e z `new Date().getFullYear()`: `tests/e2e/categories.spec.ts:16`, `month-plan-groups.spec.ts:59`, `irregular-collected.spec.ts:58`, `import-other-format-without-header.spec.ts:16`, a `reports.spec.ts:540-548` w UTC.

## Desired End State

- `context/foundation/adr/0103-one-app-day-in-europe-warsaw.md` istnieje i ustala regułę App Day, źródło po stronie serwera, przekazanie do przeglądarki, granice (znaczniki czasu, strony gościa, DST, zegar próby, strona otwarta przez północ) i konsekwencje dla S-39.
- Słownik ma wpis **App Day**, a wpisy Last twelve months i Years mówią „App Day” zamiast „(UTC)”.
- ADR-0074, 0075, 0076, 0077, 0081, 0097 i 0098 mają po jednej linii „Superseded in part by ADR-0103: …”, która nazywa zdanie, które przestaje obowiązywać.
- Czytelnik (człowiek albo agent planujący S-39) odpowiada na pytanie „który dzień jest dziś o 00:30 1 stycznia czasu polskiego” jednym dokumentem: 1 stycznia, wszędzie.

Weryfikacja: kontrole `grep` z faz poniżej i przeczytanie ADR-0103 przez użytkownika.

## What We're NOT Doing

- Bez zmian w kodzie, konfiguracji, testach i README. `config/app.timezone`, `TZ` w Dockerze, 42 miejsca na serwerze, 14 w przeglądarce, testy północy UTC i sekcja README „Przełom roku” zmieniają się w S-39 razem z kodem, bo README i testy opisują działające zachowanie.
- Bez przestawienia całej aplikacji na Europe/Warsaw (odrzucone: zmieniłoby znaczenie zapisanych `timestamp without time zone`).
- Bez liczenia dnia w przeglądarce przez `Intl.DateTimeFormat` z `timeZone` (odrzucone: dwie implementacje tej samej reguły, zegar próby i zły zegar urządzenia).
- Bez reguły w `.ai/rules` ani kontroli Sloppy przeciw `today()` w domenie: ADR zapowiada ją jako konsekwencję, a powstaje w S-39 razem z klasą, do której reguła odsyła.
- Bez poprawek luk 2, 3 i 5 (S-40, S-41, S-43) i bez zdublowanego ADR 0008 (S-46).
- Bez edycji `context/foundation/roadmap.md`: wpis F-08 istnieje tylko w niezacommitowanej kopii w głównym checkoucie.

## Implementation Approach

Najpierw decyzja (ADR-0103 i słownik), potem porządek w starszych ADR, który na nią wskazuje. Treść ADR po angielsku, jak reszta `context/foundation/adr/` i słownika; styl jak w ADR-0075 i ADR-0098 (krótkie akapity z etykietą: „Rule.”, „Server.”, „Browser.”, „Examples.”, „Consequences.”, „We rejected:”). Przykłady w ADR liczone na chwili z próby T2.

## Phase 1: ADR-0103 i słownik

### Overview

Zapisuje decyzję: definicję App Day, źródło po stronie serwera, przekazanie do przeglądarki i granice reguły, oraz termin w słowniku.

### Changes Required:

#### 1. Nowy ADR

**File**: `context/foundation/adr/0103-one-app-day-in-europe-warsaw.md`

**Intent**: Ustala jeden dzień „dziś” dla serwera i przeglądarki w miejsce trzech zegarów, żeby S-39…S-43 rozstrzygały północ tak samo.

**Contract**: Tytuł „One App Day in Europe/Warsaw”. Akapity:

- Kontekst: trzy zegary (serwer UTC, menu roku lokalnie, dialogi w dniu UTC przeglądarki), krok T2 z `rehearsal.md` i luka 4.
- **Rule.** App Day to data kalendarzowa bieżącej chwili w strefie Europe/Warsaw. Każde „dziś”, „bieżący miesiąc” i „bieżący rok kalendarzowy” w domenie to App Day, jego miesiąc albo rok: rok Dashboardu i rok domyślny (ADR-0031, ADR-0075), miesiąc minął/bieżący/przyszły w Budżecie i Planie miesiąca, zatwierdzenie i zmiana planu, Year Elapsed, Month Elapsed, Month Pace, Collected, Opening Collected i „prognoza”, raporty („w toku”, porównanie rok do roku, 12 miesięcy, Trend, Lata i dzień cięcia), start rytmu i rok archiwizacji Kategorii oraz każda reguła „data nie później niż dziś” we wpisie ręcznym, w Imporcie, w uzgodnieniu salda i w przebudowie operacji.
- **Server.** Jedno źródło w `app/Support` (`App\Support\AppDay`, metoda `today(): CarbonImmutable` zwracająca datę Europe/Warsaw o 00:00). Kontrolery i czytniki biorą dzień z niego i przekazują `$today` dalej jak dziś; obliczenia przyjmujące `$today` się nie zmieniają. `before_or_equal:today` porównuje z datą App Day, a nie z `today` PHP (Laravel rozwiązuje `today` w regule tekstowej przez `Date::parse` w strefie aplikacji, a reguła nie przyjmuje strefy, więc S-39 zastępuje 15 reguł datą wyliczoną z `AppDay`). `today('UTC')`, `today()` i `CarbonImmutable::today()` bez strefy, `now()->year` i `now()->toDateString()` nie służą do decyzji domeny. `config/app.timezone` zostaje `UTC`: znaczniki czasu (`created_at`, `approved_at`, `archived_at`, sesje, tokeny, nazwy plików kopii) to chwile w UTC, nie App Day. Dzień chwili: gdy znacznik czasu staje się dniem albo miesiącem (porównanie z miesiącem, jak `archived_at` w Propozycji i w Collected, albo wyświetlona data), liczy się jego data w Europe/Warsaw, nie w UTC; przykład: archiwizacja 1.02.2027 00:30 w Polsce (31.01 23:30 UTC) to archiwizacja w lutym.
- **Browser.** Współdzielony props `shell.today` (`'YYYY-MM-DD'`) z tego samego źródła. Domyślne daty dialogów (operacja, przelew własny, ruch kapitału, lokata, konto, uzgodnienie salda) z ograniczeniem `max` w uzgodnieniu, „bieżący” i `createYear` w menu roku, paleta, dialog nowego Budżetu, presety „ten miesiąc”/„poprzedni miesiąc”, rok w etykietach dni oraz strony gościa (postęp roku na ekranie logowania, rok w stopce strony powitalnej; `shell` dochodzi i do nich) biorą dzień z `shell.today`, nie z `new Date()`. Reguła nie ma wyjątków. Strona otwarta przez północ zostaje przy swoim dniu do następnej pełnej wizyty Inertia (częściowe przeładowanie z `only` nie odświeża `shell`); wczorajsza data jest poprawna, a o „przyszłej” dacie i tak decyduje serwer.
- **Examples.** Chwila 31.12.2026 23:30 UTC (1.01.2027 00:30 w Polsce): App Day to 2027-01-01, Dashboard „1 stycznia 2027”, menu roku „bieżący” 2027, domyślna data dialogu 2027-01-01, operacja z wyciągu z datą 01.01 nie jest „przyszła”. Chwila 31.12.2026 22:59:59 UTC (23:59:59 w Polsce): App Day 2026-12-31, grudzień da się jeszcze zatwierdzić. Latem (CEST) 30.06 22:30 UTC to App Day 1 lipca.
- **Time changes.** Północ w Europe/Warsaw zawsze istnieje i jest jedna (zmiana czasu o 02:00 i 03:00); doba ma 23 albo 25 godzin, więc kod przesuwa dzień kalendarzowo, nigdy o 24 godziny.
- **Year turn.** Rok i miesiąc zmieniają się o 00:00 czasu polskiego. Twardy termin zatwierdzenia grudnia to 31.12 23:59:59 czasu polskiego. README „Przełom roku” i testy północy UTC zmieniają się razem z kodem (S-39), nie wcześniej.
- **Rehearsal clock.** `app:rehearsal-clock` nadal ustawia chwilę (wejście w UTC); App Day wynika z niej, a przeglądarka idzie za nią przez `shell.today` bez `page.clock`.
- **Data.** Brak migracji: daty biznesowe to kolumny `date`, a zapisane miesiące startu rytmu nie są przeliczane. Miesiąc archiwizacji nie jest zapisany, wynika z `archived_at`; istniejące znaczniki zostają, a od S-39 ich dzień czyta się w Europe/Warsaw (archiwizacja z godziny po północy czasu polskiego przechodzi na następny dzień).
- **Relation to earlier ADRs.** Zastępuje w części ADR-0074, 0075, 0076, 0077, 0081, 0097 (zdania „in UTC”) i ADR-0098 (zdanie o `new Date().getFullYear()`); ADR-0031 i ADR-0087 dziedziczą App Day bez zmian; ADR-0013 (harmonogram kopii w Europe/Warsaw) bez zmian.
- **Consequences.** S-39 (`one-app-day`) wprowadza `AppDay` i `shell.today` po spisie miejsc z planu zmiany `app-day-decision` (sekcja „Spis miejsc w kodzie”, bez ścieżki, bo folder przechodzi do `context/archive/`); props zmienia obie strony w jednym commicie z testem `assertInertia`; regułę w `.ai/rules` przeciw zegarowi w domenie dodaje S-39; S-40, S-41 i S-43 biorą dzień z `AppDay`.
- **We rejected:** UTC na serwerze z przeglądarką dopasowaną do serwera (przez godzinę zimą, dwie latem cała aplikacja jest we wczoraj, a operacja z dzisiejszą datą z banku jest odrzucana jako przyszła); cała aplikacja w Europe/Warsaw (`config/app.timezone`, `TZ`: zapisane `timestamp without time zone` przesuwają się o 1–2 godziny bez migracji, a godzina zmiany czasu jest niejednoznaczna); przeglądarka licząca Europe/Warsaw sama (dwie implementacje reguły, zegar próby wymaga `page.clock`, zły zegar urządzenia daje inny dzień niż serwer).

#### 2. Termin w słowniku

**File**: `context/foundation/glossary.md`

**Intent**: Nadaje „dziś” nazwę i definicję, do której odsyłają wszystkie wpisy mówiące o dniu, miesiącu i roku bieżącym.

**Contract**: Nowy wpis `**App Day**:` zaraz po wpisie **Budget**: data kalendarzowa bieżącej chwili w Europe/Warsaw (ADR-0103); gdziekolwiek słownik mówi „today”, „the current month” albo „the current calendar year”, chodzi o App Day, jego miesiąc albo rok; serwer liczy go w jednym miejscu, przeglądarka dostaje go od serwera i nie czyta własnego zegara do tych decyzji; znaczniki czasu (kiedy coś zapisano lub zatwierdzono) to chwile w UTC, nie App Day, a gdy chwila staje się dniem albo miesiącem, liczy się jej data w Europe/Warsaw; nie ma własnego tekstu w UI. Linia `_Avoid_: today in UTC, server date, browser date, local date`. We wpisie Period Report: „the month before today (UTC)” → „the month before today (App Day)”, „the current calendar year (UTC)” → „the current calendar year (App Day)”.

### Success Criteria:

#### Automated Verification:

- Jest dokładnie jeden ADR 0103: `ls context/foundation/adr | grep -c '^0103-'` zwraca `1`
- Słownik ma wpis App Day: `grep -c '^\*\*App Day\*\*:' context/foundation/glossary.md` zwraca `1`
- Słownik nie mówi już „(UTC)”: `grep -c '(UTC)' context/foundation/glossary.md` zwraca `0`
- ADR-0103 nazywa każdy zastępowany ADR: `for n in 0074 0075 0076 0077 0081 0097 0098; do grep -q "ADR-$n" context/foundation/adr/0103-one-app-day-in-europe-warsaw.md || echo "brak $n"; done` nic nie wypisuje
- Zmiana dotyka tylko dokumentów: `git status --porcelain --untracked-files=all | grep -v ' context/'` nic nie wypisuje

#### Manual Verification:

- Użytkownik czyta ADR-0103 i wpis App Day: reguła zgadza się z trzema decyzjami (dzień w Polsce, osobne źródło na serwerze, dzień z serwera w przeglądarce), a przykład 00:30 1 stycznia daje 1 stycznia wszędzie

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Oznaczenie starszych ADR i przekazanie do S-39

### Overview

Każdy ADR, który ustalał „dziś” sam, wskazuje na ADR-0103, a spis miejsc w kodzie jest sprawdzony na bieżącym stanie repo.

### Changes Required:

#### 1. Linie „Superseded in part”

**File**: `context/foundation/adr/0074-period-report-year-over-year.md`, `0075-default-budget-year-is-the-current-one.md`, `0076-opening-collected-on-the-budget-screen.md`, `0077-report-trend-by-month.md`, `0081-report-last-twelve-months.md`, `0097-report-years-ranking-and-chart-colours.md`, `0098-budget-year-state-in-the-menu.md`

**Intent**: Czytelnik starszego ADR od razu widzi, że jego zegar zastąpił App Day, bez szukania ADR-0103.

**Contract**: Jedna linia po pierwszym akapicie, w formie z ADR-0003:5, np. „Superseded in part by ADR-0103: „today” is the App Day (the date in Europe/Warsaw), not the date in UTC; the rest stands.” Dla każdego nazwa zdania, które przestaje obowiązywać:

- 0074: „in UTC like `is_in_progress`” (okres w toku i dzień porównania);
- 0075: „„Today” is in UTC, like `ReportController`”;
- 0076: „the year of today (UTC, as the rest of the page)” przy „prognoza”;
- 0077: „the month of today (UTC, like ADR-0074)”;
- 0081: „the month before today (UTC, like ADR-0074 and ADR-0077)”;
- 0097: „the current calendar year (UTC)” i „The cut day D is today's day in UTC”;
- 0098: „„bieżący” means the calendar year (`new Date().getFullYear()` in the browser, as `createYear` does today)” → rok App Day z `shell.today`.

Treść ADR poza tą linią zostaje bez zmian.

#### 2. Sprawdzenie spisu dla S-39

**File**: `context/changes/app-day-decision/plan.md` (sekcja „Spis miejsc w kodzie”)

**Intent**: S-39 planuje na spisie zgodnym z kodem w chwili zamknięcia tej zmiany.

**Contract**: Liczby z kontroli automatycznych poniżej zgadzają się ze spisem; jeśli kod się zmienił (merge do `development`), spis i liczby w „What We're NOT Doing” są poprawione (ADR-0103 nie podaje liczb).

### Success Criteria:

#### Automated Verification:

- Dokładnie siedem ADR wskazuje na 0103: `grep -l 'Superseded in part by ADR-0103' context/foundation/adr/*.md | grep -v '/0103-' | wc -l` zwraca `7`, a lista to 0074, 0075, 0076, 0077, 0081, 0097, 0098
- Spis serwera aktualny: `grep -rn "today('UTC')" app | wc -l` zwraca `20`, `grep -rn 'before_or_equal:today' app | wc -l` zwraca `15` (albo spis poprawiony do nowych liczb)
- Spis przeglądarki aktualny: `grep -rln 'toISOString().slice(0, 10)' resources/js | grep -v test | wc -l` zwraca `7` (albo spis poprawiony)
- Zmiana dotyka tylko dokumentów: `git status --porcelain --untracked-files=all | grep -v ' context/'` nic nie wypisuje

#### Manual Verification:

- Użytkownik czyta linię „Superseded in part” w ADR-0098 i ADR-0097: każda mówi, które zdanie przestaje obowiązywać, a reszta ADR stoi

---

## Testing Strategy

Zmiana nie ma kodu, więc nie ma testów. Kontrole faz to `grep` na plikach dokumentów (formatter `vp fmt` pomija `context/**`, `vite.config.ts:87-97`). Testy reguły App Day (granica 22:59:59/23:00:00 UTC zimą i 21:59:59/22:00:00 UTC latem, dzień zmiany czasu, `shell.today` z `assertInertia`, dialogi o 00:30 czasu polskiego, archiwizacja Kategorii o 00:30 1. dnia miesiąca w Propozycji i w Collected) należą do S-39.

### Manual Testing Steps:

1. Przeczytać ADR-0103 i odpowiedzieć z niego: który dzień jest dziś o 31.12.2026 23:30 UTC? (2027-01-01)
2. Przeczytać wpis App Day i wpis Period Report w słowniku: oba mówią App Day, żaden nie mówi UTC.
3. Otworzyć ADR-0075 i ADR-0098: linia „Superseded in part by ADR-0103” stoi po pierwszym akapicie.

## References

- Roadmapa: F-08 i S-39 w `context/foundation/roadmap.md` (kopia z 2026-11-18 w głównym checkoucie, niezacommitowana)
- Próba przełomu: `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md` (T2, luka 4), `research.md:260` (strefa odłożona do osobnej decyzji)
- Wcześniejsza uwaga o dacie UTC w dialogach: `context/archive/2026-11-05-operation-dialogs-atrament/reviews/impl-review.md:116`
- Konwencja ADR: `context/foundation/adr/0003-wallet-master-surface-recipe.md:5`, `0075-default-budget-year-is-the-current-one.md`, `0098-budget-year-state-in-the-menu.md`
- Kod: `config/app.php:68`, `app/Support/SelectedBudgetYear.php:36`, `app/Support/RehearsalClock.php`, `bootstrap/app.php:39-53`, `resources/js/components/BudgetYearSwitcher.vue:82`, `resources/js/components/IncomeFormDialog.vue:332`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: ADR-0103 i słownik

#### Automated

- [x] 1.1 Jest dokładnie jeden ADR 0103 — 64ea4cc
- [x] 1.2 Słownik ma wpis App Day — 64ea4cc
- [x] 1.3 Słownik nie mówi już „(UTC)” — 64ea4cc
- [x] 1.4 ADR-0103 nazywa każdy zastępowany ADR — 64ea4cc
- [x] 1.5 Zmiana dotyka tylko dokumentów — 64ea4cc

#### Manual

- [ ] 1.6 Użytkownik potwierdza ADR-0103 i wpis App Day (przykład 00:30 1 stycznia daje 1 stycznia wszędzie)

### Phase 2: Oznaczenie starszych ADR i przekazanie do S-39

#### Automated

- [x] 2.1 Dokładnie siedem ADR wskazuje na 0103 — 9b5c354
- [x] 2.2 Spis serwera aktualny — 9b5c354
- [x] 2.3 Spis przeglądarki aktualny — 9b5c354
- [x] 2.4 Zmiana dotyka tylko dokumentów — 9b5c354

#### Manual

- [ ] 2.5 Użytkownik potwierdza linie „Superseded in part” w ADR-0098 i ADR-0097
