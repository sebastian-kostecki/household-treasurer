# Ten sam dzień w menu, na Dashboardzie i w dialogach (S-39) — plan implementacji

## Overview

S-39 wprowadza regułę App Day z ADR-0103 do kodu. Serwer bierze „dziś” z jednej klasy `App\Support\AppDay` (data bieżącej chwili w Europe/Warsaw), przeglądarka dostaje ten dzień w współdzielonym propsie `shell.today` i nie czyta własnego zegara. Dzięki temu użytkownik o 00:30 1 stycznia 2027 (31.12.2026 23:30 UTC) widzi w menu roku, na Dashboardzie i jako domyślną datę w dialogach operacji, przelewu własnego, ruchu kapitału, lokaty, rachunku i uzgodnienia salda ten sam dzień: 2027-01-01. Dwa strażniki statyczne (PHP i JS) pilnują, żeby trzeci zegar nie wrócił.

To gwiazda przewodnia M-5 (MS-01, luka 4 z próby przełomu). S-40, S-41 i S-43 biorą dzień z `AppDay`.

## Current State Analysis

Aplikacja ma trzy zegary (ADR-0103, `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md`, krok T2): serwer liczy „dziś” w UTC, menu roku, paleta, dialog nowego Budżetu, presety filtrów, etykiety dni, ekran logowania i stopka strony powitalnej biorą czas lokalny przeglądarki, a siedem dialogów bierze dzień UTC przeglądarki (`new Date().toISOString().slice(0, 10)`).

Spis miejsc z planu F-08 (`context/archive/2026-11-18-app-day-decision/plan.md`, „Spis miejsc w kodzie”, stan e50f8f6) sprawdzony na `863a422` (2026-11-19): liczby się zgadzają, po S-42 przesunęły się tylko linie w `FinancialOperationImportValidator.php` (dziś 120 i 146).

Serwer:

- 20 × `CarbonImmutable::today('UTC')`: kontrolery `DashboardController.php:35`, `BudgetController.php:63`, `MonthPlanController.php:56,95,122`, `ReportController.php:53`, `CategoryController.php:38`, `CategoryListController.php:28,42`, `CategoryMergeController.php:23,29`; czytniki `SelectedBudgetYear.php:36`, `CategoryListReading.php:53`, `EntryToSpendInputs.php:45`, `HandEntryMonthRemainingInputs.php:30`, `CsvImport/ImportMonthRemainingContext.php:35` (trzy ostatnie jako `$today ??=`); akcje `CreateListCategory.php:61`, `UpdateListCategory.php:68`, `CreateCategory.php:67`, `RestoreUserData.php:639`.
- Inne odczyty zegara dla domeny: `CsvImport/CsvImportPreviewValidator.php:21` (`CarbonImmutable::today()` bez strefy, wołane bez `$today` z `CsvImportController.php:94`), `ArchiveCategory.php:21,30` (jedno `now()` jako `archived_at` i jako rok Budżetu), `RestoreCategory.php:28` (`now()->year`), `PreviewBalanceReconciliationRequest.php:47` (`now()->toDateString()`).
- Znacznik czasu jako dzień: `MonthPlanProposal.php:184` i `IrregularCollected.php:168` porównują `archived_at` z pierwszym dniem miesiąca w UTC, `CategoryListReading.php:75` pokazuje `archived_at->toDateString()` (wyświetlane w `CategoryListRow.vue:56`).
- 15 × `before_or_equal:today`: `Concerns/IncomeValidationRules.php:21,58`, `ExpenseValidationRules.php:21,58`, `OwnTransferValidationRules.php:17`, `CapitalMovementValidationRules.php:20`, `OperationRebuildValidationRules.php:30,69,112,150,188`, `CsvImport/FinancialOperationImportValidator.php:120,146`, `OwnTransferImportValidator.php:25`, `CapitalMovementImportValidator.php:25`. Laravel rozwiązuje tekst `today` w strefie aplikacji (UTC), a komunikat błędu jest domyślnym angielskim tekstem Laravela („…before or equal to today.”); `lang/pl/validation.php` ma tylko dwa wpisy.
- Pozostałe `now()` w `app/` to znaczniki czasu (`created_at`, `approved_at`, `archived_at` kont i lokat, nazwy plików kopii, `LegacyCategoryConversion` `$migratedAt`) i zostają w UTC.
- Świadomy wyjątek: `Backup/HealthyBackupWasFoundNotification.php:23` (`CarbonImmutable::now('Europe/Warsaw')->isMonday()`) to harmonogram kopii z ADR-0013, nie decyzja domeny; zostaje bez zmian, a strażnik PHP nie łapie `now('…')` ze strefą.

Przeglądarka:

- Domyślna data UTC: `IncomeFormDialog.vue:332`, `ExpenseFormDialog.vue:439`, `OwnTransferFormDialog.vue:182`, `CapitalMovementFormDialog.vue:222`, `TermDepositFormDialog.vue:118`, `AccountFormDialog.vue:105`, `BalanceReconciliationDialog.vue:48` (`computed` bez zależności, używany w `:max` i w `reconciliationDayIsAllowed`, `lib/balance-reconciliation.ts:19-26`).
- Rok i miesiąc lokalnie: `BudgetYearSwitcher.vue:82` (prop `currentYear` istnieje, `AppSidebar.vue:83-86` go nie podaje), `composables/useCommandPalette.ts:132`, `CreateBudgetDialog.vue:55`, `OperationsFilters.vue:179-185`, `lib/format.ts:199-215` (`formatDayLabel` z domyślnym `today = new Date()`).
- Strony gościa: `layouts/auth/AuthCardLayout.vue:18` (`authYearProgress(new Date())`, `lib/auth-year-progress.ts:16`), `pages/Welcome.vue:26`.
- Martwy kod: `lib/format.ts:137-177` (`formatRelativeDate`, brak wywołań w `resources/js`).
- Kitchen sink z żywym zegarem: `lib/operation-dialogs-fixtures.ts:27-40` (`/dev/ui/dialogs` i `/dev/operations`).

`shell` (`HandleInertiaRequests.php:48`, `ShellState::forRequest`) idzie do każdej strony grupy `web`, także do gościa i do tras `dev/...` (`routes/web.php:40-48`). Typ TS: `resources/js/types/shell.ts:14-19`. Komponenty stale zamontowane w layoucie czytają `shell` tylko przez `useShell()` (`composables/useTranslations.ts:12`, reguła `.ai/rules/js-sidebar.md`).

Testy przypięte do północy UTC: `tests/Feature/YearTurnTest.php` (`test_december_can_be_approved_up_to_the_last_second_of_utc` o 23:59:59, `test_december_cannot_be_approved_from_midnight_utc_and_january_takes_the_annual_share` o 00:00:00, docblock „the year changes by the UTC clock”), `BudgetCollectedAtYearStartTest.php:172,176` („the forecast mark ends exactly at 1 January” o 23:59:59 i 00:00:00 UTC). `today('UTC')` do wyliczenia oczekiwań mają jeszcze `MonthSummaryTest`, `ChangeMonthPlanTest`, `SeedPreviousYearCommandTest`, `MonthPaceTest`, `MonthToSpendTest`, `RehearsalClockTest`. Vitest: `format.test.ts` i `auth-year-progress.test.ts` podają `Date`.

## Desired End State

- `App\Support\AppDay::today()` jest jedynym źródłem „dziś” dla domeny na serwerze, a `AppDay::of($chwila)` jedyną drogą od znacznika czasu do dnia.
- Każda strona (zalogowana, gościa, `dev/...`) dostaje `shell.today` (`'YYYY-MM-DD'`), a cały kod w `resources/js` bierze dzień, miesiąc i rok tylko z niego.
- Przy chwilach 31.12.2026 22:59:59 / 23:00:00 UTC i 30.06.2027 21:59:59 / 22:00:00 UTC `shell.today`, rok Dashboardu i `shell.budgetYear` dają ten sam App Day (test `assertInertia`).
- Operacja z datą App Day jest przyjmowana o 00:30 czasu polskiego, a data jutrzejsza jest odrzucana z komunikatem „Data nie może być w przyszłości.”.
- `composer ci:check` czerwieni się, gdy w `app/` albo `resources/js` pojawi się odczyt zegara poza `AppDay` i zegarem próby.
- README „Przełom roku” mówi o północy w Polsce i terminie grudnia 31.12 23:59:59 czasu polskiego; `.ai/rules` ma regułę App Day.

Weryfikacja: gate z testami PostgreSQL na każdej fazie, ręczne sprawdzenie w przeglądarce na zegarze próby w fazie 4.

### Key Discoveries:

- Konsumenci porównują `$today` jako chwilę z `CarbonImmutable::create($rok, $mies, 1)` w strefie aplikacji (`MonthPlanProposal.php:180-184`, `IrregularCollected.php:166-168`). Dlatego `AppDay` zwraca datę z Warszawy o 00:00 **w strefie aplikacji (UTC)**, nie północ w Europe/Warsaw (szczegóły w Critical Implementation Details).
- Klasy w `app/Support` przyjmują `$today` jako parametr (`YearElapsed`, `MonthElapsed`, `MonthPlanProposal`, `ReportYearsRange`, `ReportTwelveMonthWindow`, `MonthPace` i inni), więc zmiana dotyczy tylko miejsc, które czytają zegar, a nie obliczeń.
- `before_or_equal` w `app/` występuje tylko w 15 regułach „data nie później niż dziś”, więc wpis `before_or_equal` w `lang/pl/validation.php` nie zmieni innego komunikatu.
- `tests/Unit` w Pest nie jest związany z `Tests\TestCase` (`tests/Pest.php:20-22` wiąże tylko `Feature`), więc test `AppDay` z `travelTo` i `config` leży w `tests/Feature`, a strażniki skanujące pliki w `tests/Unit` (precedens `tests/Unit/MonthPlanHardcodedUiTest.php`).
- `.ai/rules/js-auth.md` mówi, że blok roku na ekranie logowania liczy się „not from an Inertia prop”; ADR-0103 każe brać go z `shell.today`, więc to zdanie zmienia się razem z kodem.
- `tests/Feature/AppShellTest.php` już sprawdza `shell` przez `assertInertia`; tam trafia test `shell.today`.
- Test `_known_gap` w `SelectedBudgetYearTest.php:82` (sesja trzyma 2026 w styczniu) należy do S-40 i zostaje bez zmian.

## What We're NOT Doing

- Bez zmiany `config/app.timezone` i `TZ` w Dockerze: znaczniki czasu zostają chwilami w UTC (ADR-0103, odrzucona opcja).
- Bez liczenia strefy w przeglądarce (`Intl.DateTimeFormat` z `timeZone`) i bez `page.clock` (ADR-0103, odrzucona opcja).
- Bez odświeżania `shell.today` na stronie otwartej przez północ: zostaje do następnej pełnej wizyty Inertia (ADR-0103).
- Bez zmian w `tests/e2e`: pięć speców liczy rok z zegara runnera (`categories.spec.ts:16`, `month-plan-groups.spec.ts:59`, `irregular-collected.spec.ts:58`, `import-other-format-without-header.spec.ts:16`, `reports.spec.ts:540`). Trzy specy-bramki poprawia S-44 (`e2e-in-ci`, plan testów §5 i §6.3), pozostałe dwa zostają; strażnik JS skanuje tylko `resources/js`.
- Bez poprawek luk 2, 3 i 5: rok w sesji po przełomie (S-40, test `_known_gap` zostaje), przypomnienia przed przełomem (S-41), „Lata” w pierwszych dniach stycznia (S-43).
- Bez zmian w historycznych migracjach (`2026_11_10_120001_backfill_category_rhythms.php:22`, `2026_11_10_120002_drop_rhythm_flags_from_budget_lines.php:41`), seederach i fabrykach: nie są decyzją domeny w działającej aplikacji, strażnik PHP skanuje tylko `app/`.
- Bez migracji danych: daty biznesowe to kolumny `date`, a istniejące `archived_at` od tej zmiany czyta się w Europe/Warsaw (ADR-0103, „Data”).
- Bez nowego terminu słownika i nowego ADR: App Day i ADR-0103 już istnieją.

## Implementation Approach

Od środka na zewnątrz, w kolejności zależności: najpierw jedno źródło dnia na serwerze i wszystkie odczyty dla domeny, potem reguły „nie później niż dziś” ze strażnikiem PHP (dopiero gdy `app/` jest czyste), potem `shell.today` po obu stronach w jednym commicie z przeniesieniem przeglądarki i strażnikiem JS, na końcu dokumenty, reguły i próba na zegarze. Każda faza kończy się zielonym gate'em i osobnym commitem `feat(one-app-day): …` (faza 4: `docs(one-app-day): …`). Między fazami 1 a 3 serwer liczy już dzień w Polsce, a przeglądarka jeszcze własnym zegarem; to stan tylko na gałęzi, scalanej po fazie 4.

Testy idą według planu testów (ryzyko #9, §6.1, §6.2): `travelTo` na granicach polskiej północy, nigdy na północy UTC; zmiana testów przypiętych do północy UTC to zmiana wymagania (ADR-0103), nie strojenie asercji.

## Critical Implementation Details

**Kształt wartości `AppDay`.** `today()` i `of()` zwracają `CarbonImmutable` z datą kalendarzową Europe/Warsaw o 00:00 w strefie aplikacji (`config('app.timezone')`, czyli UTC), nie chwilę „północ w Warszawie” (23:00 UTC dnia poprzedniego). Wtedy `->year`, `->month`, `->toDateString()` dają dzień w Polsce, a porównania z `CarbonImmutable::create($rok, $mies, 1)` działają jak dotąd. Wersja z północą w Europe/Warsaw wyglądałaby poprawnie w `->toDateString()`, ale `lessThan($firstDay)` w Propozycji i w Collected dałby wynik o dzień przesunięty. Zegar bierz z `CarbonImmutable::now()`, żeby `travelTo` i zegar próby (`Date::setTestNow`) działały bez zmian:

```php
public static function of(DateTimeInterface $instant): CarbonImmutable
{
    $date = CarbonImmutable::instance($instant)->setTimezone(self::ZONE)->toDateString();

    return CarbonImmutable::parse($date, config('app.timezone'));
}
```

**Reguła walidacji z datą.** `before_or_equal:today` zamienia się na datę z `AppDay` (np. `Rule::date()->beforeOrEqual(AppDay::today())` albo `'before_or_equal:'.AppDay::today()->toDateString()`; przed wyborem sprawdź API w `search-docs` dla zainstalowanego Laravela). Nie używaj `today Europe/Warsaw` w tekście reguły: daje 23:00 UTC dnia poprzedniego i odrzuca sam App Day (ADR-0103, „Server”).

## Phase 1: `AppDay` i dzień na serwerze

### Overview

Jedno źródło dnia w `app/Support`, wszystkie odczyty „dziś” dla domeny i wszystkie przejścia od znacznika czasu do dnia przechodzą na `AppDay`, a testy granic czasu biegną na polskiej północy.

### Changes Required:

#### 1. Klasa `AppDay`

**File**: `app/Support/AppDay.php` (nowy, `php artisan make:class`)

**Intent**: Jedno miejsce, które wie, że „dziś” to dzień w Polsce (ADR-0103, „Server”), z PHPDoc odsyłającym do ADR-0103.

**Contract**: `final class AppDay` ze stałą `ZONE = 'Europe/Warsaw'` i dwiema metodami statycznymi: `today(): CarbonImmutable` (= `of(CarbonImmutable::now())`) oraz `of(DateTimeInterface $instant): CarbonImmutable` (data chwili w Europe/Warsaw o 00:00 w strefie aplikacji, kształt z Critical Implementation Details).

#### 2. Odczyty „dziś” w kontrolerach, czytnikach i akcjach

**File**: `app/Http/Controllers/DashboardController.php`, `BudgetController.php`, `MonthPlanController.php`, `ReportController.php`, `CategoryController.php`, `CategoryListController.php`, `CategoryMergeController.php`; `app/Support/SelectedBudgetYear.php`, `CategoryListReading.php`, `EntryToSpendInputs.php`, `HandEntryMonthRemainingInputs.php`, `CsvImport/ImportMonthRemainingContext.php`; `app/Actions/CreateListCategory.php`, `UpdateListCategory.php`, `CreateCategory.php`, `RestoreUserData.php`, `RestoreCategory.php`

**Intent**: Każde z 20 wywołań `CarbonImmutable::today('UTC')` i `now()->year` w `RestoreCategory.php:28` bierze dzień z `AppDay::today()`; wartości przekazywane dalej jako `$today` się nie zmieniają.

**Contract**: Brak zmiany sygnatur; parametry domyślne `$today ??= …` w trzech czytnikach wołają `AppDay::today()`.

#### 3. Rok archiwizacji w `ArchiveCategory`

**File**: `app/Actions/ArchiveCategory.php`

**Intent**: Rozdziela znacznik czasu od dnia: `archived_at` zostaje chwilą `now()` (UTC), a rok Budżetu, w którym archiwizuje się pozycję, to rok `AppDay::today()`. Kategoria archiwizowana 31.12.2026 23:30 UTC (1.01.2027 00:30 w Polsce) archiwizuje pozycję w Budżecie 2027.

**Contract**: Docblock klasy mówi „the current calendar year (App Day)”.

#### 4. Znacznik czasu jako dzień

**File**: `app/Support/MonthPlanProposal.php:184`, `app/Support/IrregularCollected.php:168`, `app/Support/CategoryListReading.php:75`

**Intent**: Dzień archiwizacji Kategorii to jej data w Polsce (ADR-0103, „The day of an instant”): archiwizacja 1.02.2027 00:30 w Polsce (31.01 23:30 UTC) to archiwizacja w lutym, więc Kategoria jest jeszcze w Propozycji lutego i jej pozycja zbiera w lutym, a lista Kategorii pokazuje 2027-02-01.

**Contract**: Porównanie `AppDay::of($archivedAt)->lessThan($firstDay)` zamiast `$archivedAt->lessThan($firstDay)`; w `MonthPlanProposal.php:184` to `categories.archived_at`, w `IrregularCollected.php:168` to `budget_lines.archived_at` pozycji (ładowane w `:232-236`), stemplowane przez `ArchiveCategory` tą samą chwilą. Prop `archived_at` w liście Kategorii to `AppDay::of(...)->toDateString()` (bez zmiany typu, `string|null`).

#### 5. Testy

**File**: `tests/Feature/AppDayTest.php` (nowy), `tests/Feature/YearTurnTest.php`, `tests/Feature/BudgetCollectedAtYearStartTest.php`, `tests/Feature/SelectedBudgetYearTest.php`, `tests/Feature/MonthPlanProposalTest.php`, `tests/Feature/IrregularCollectedTest.php`, `tests/Feature/CategoriesPageTest.php`, `tests/Feature/ArchiveCategoryTest.php`, `tests/Feature/DashboardTest.php`; `MonthSummaryTest`, `ChangeMonthPlanTest`, `SeedPreviousYearCommandTest`, `MonthPaceTest`, `MonthToSpendTest`, `RehearsalClockTest`

**Intent**: Przypina regułę na granicach polskiej północy i zmienia wymaganie w testach przypiętych do północy UTC (ADR-0103, plan testów §6.2).

**Contract**:

- `AppDayTest`: `travelTo` 2026-12-31 22:59:59 UTC → `2026-12-31`, 23:00:00 UTC → `2027-01-01`; 2027-06-30 21:59:59 UTC → `2027-06-30`, 22:00:00 UTC → `2027-07-01`; dni zmiany czasu (2027-03-27 23:00:00 UTC → `2027-03-28`, 2027-10-30 22:00:00 UTC → `2027-10-31`); wynik ma godzinę 00:00 i strefę aplikacji; `of()` dla 2027-01-31 23:30:00 UTC → `2027-02-01`.
- `YearTurnTest`: grudzień da się zatwierdzić o 2026-12-31 22:59:59 UTC, a od 23:00:00 UTC nie da się, a styczeń bierze roczny udział; nazwy testów mówią o czasie polskim (np. `…_up_to_the_last_second_of_polish_time`, `…_from_polish_midnight_…`), docblock klasy mówi o zmianie roku o północy w Polsce, `today('UTC')` → `AppDay::today()`.
- `BudgetCollectedAtYearStartTest`: znacznik prognozy kończy się między 22:59:59 a 23:00:00 UTC 31 grudnia.
- `SelectedBudgetYearTest`: rok domyślny bez roku w sesji to 2026 o 2026-12-31 22:59:59 UTC i 2027 o 23:00:00 UTC; test `_known_gap` bez zmian.
- Archiwizacja o 2027-01-31 23:30 UTC (przez `ArchiveCategory`, żeby `archived_at` dostały i Kategoria, i jej pozycja, a nie przez stan fabryki Kategorii): Kategoria jest w Propozycji lutego i jej pozycja zbiera w lutym; lista Kategorii pokazuje `archived_at` `2027-02-01`; `ArchiveCategory` o 2026-12-31 23:30 UTC archiwizuje pozycję w Budżecie 2027 w wykonaniu, a pozycja 2026 zostaje.
- Dashboard o 2026-12-31 23:30 UTC: rok Dashboardu 2027 (`assertInertia`).
- Pozostałe pliki z `today('UTC')` do wyliczenia oczekiwań biorą `AppDay::today()`.

### Success Criteria:

#### Automated Verification:

- Nowe i zmienione testy przechodzą na PostgreSQL: `AppDayTest`, `YearTurnTest`, `BudgetCollectedAtYearStartTest`, `SelectedBudgetYearTest`, `MonthPlanProposalTest`, `IrregularCollectedTest`, `CategoriesPageTest`, `ArchiveCategoryTest`, `DashboardTest` (komenda worktree z AGENTS.md, baza `testing_one_app_day`)
- `grep -rn "today('UTC')" app tests` nic nie wypisuje
- `grep -rnE "now\(\)->(year|month)" app` nic nie wypisuje (`now()->toDateString()` w `PreviewBalanceReconciliationRequest.php:47` przechodzi w fazie 2)
- Pełny gate przechodzi: `composer ci:check` na `testing_one_app_day` (obraz `sail-8.5/app`), `vendor/bin/sloppy ci`, `vendor/bin/pint --dirty --format agent` bez zmian

#### Manual Verification:

- Użytkownik czyta przepisane nazwy i granice w `YearTurnTest` i `BudgetCollectedAtYearStartTest`: opisują przełom o północy w Polsce, zgodnie z ADR-0103

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: „Nie później niż dziś” na serwerze i strażnik PHP

### Overview

Każda reguła „data nie później niż dziś” (wpis ręczny, Import, uzgodnienie salda, przebudowa operacji) porównuje z App Day, komunikat jest po polsku, a test statyczny nie pozwala wrócić zegarowi w `app/`.

### Changes Required:

#### 1. Reguły walidacji

**File**: `app/Concerns/IncomeValidationRules.php`, `ExpenseValidationRules.php`, `OwnTransferValidationRules.php`, `CapitalMovementValidationRules.php`, `OperationRebuildValidationRules.php`; `app/Support/CsvImport/FinancialOperationImportValidator.php`, `OwnTransferImportValidator.php`, `CapitalMovementImportValidator.php`

**Intent**: 15 reguł `before_or_equal:today` porównuje z datą `AppDay::today()`, żeby operacja z wyciągu z datą 01.01 nie była „przyszła” o 00:30 czasu polskiego.

**Contract**: Reguła z datą `Y-m-d` App Day (Critical Implementation Details); bez tekstu `before_or_equal:today` w `app/`.

#### 2. Pozostałe porównania z dniem

**File**: `app/Http/Requests/PreviewBalanceReconciliationRequest.php:47`, `app/Support/CsvImport/CsvImportPreviewValidator.php:21`

**Intent**: Dzień uzgodnienia salda i odrzucenie przyszłej daty w podglądzie Importu (`ImportRowInvalidReason::FutureDate`) liczą się od App Day.

**Contract**: `now()->toDateString()` → `AppDay::today()->toDateString()`; `$today ??= CarbonImmutable::today()` → `$today ??= AppDay::today()`.

#### 3. Komunikat po polsku

**File**: `lang/pl/validation.php`

**Intent**: Pod polem daty w dialogach pojawia się ten sam tekst co w podglądzie Importu, zamiast angielskiego „…before or equal to 2027-01-01.”.

**Contract**: Wpis `'before_or_equal' => 'Data nie może być w przyszłości.'` (tekst jak `ui.php` `import_invalid_reason_future_date`, bez `:date`).

#### 4. Strażnik zegara w PHP

**File**: `tests/Unit/AppDayClockGuardTest.php` (nowy)

**Intent**: `composer ci:check` czerwieni się, gdy kod w `app/` czyta zegar do decyzji domeny poza `AppDay` (ADR-0103, „Server”), wzorem `tests/Unit/MonthPlanHardcodedUiTest.php`.

**Contract**: Skanuje `app/**/*.php` i zgłasza plik i linię dla wzorców (regex wprost, żeby `AppDay::today()` w kontrolerach, czytnikach i akcjach nie był trafieniem):

- `::today(` poprzedzone czymkolwiek innym niż `AppDay` (`/(?<!AppDay)::today\(/`), czyli `CarbonImmutable::today(…)`, `Carbon::today(…)`, `Date::today(…)` ze strefą i bez;
- helper `today(…)` (`/(?<![\w:>$])today\(/`);
- `now()->year`, `now()->month`, `now()->toDateString()` (`/now\(\)->(?:year|month|toDateString)\b/`);
- `before_or_equal:today`.

Wyjątki (lista, która może tylko maleć): `app/Support/AppDay.php`, `app/Support/RehearsalClock.php`, `app/Console/Commands/RehearsalClockCommand.php`. `now()` jako znacznik czasu i `now('Europe/Warsaw')` w `HealthyBackupWasFoundNotification` są dozwolone.

#### 5. Testy

**File**: `tests/Feature/AppDayValidationTest.php` (nowy; jeden plik dla wszystkich ścieżek, bo sprawdza jedną regułę)

**Intent**: Dowodzi, że każda ścieżka wpisu przyjmuje App Day i odrzuca dzień następny przy chwili 2026-12-31 23:30 UTC (z Budżetem 2027 w wykonaniu).

**Contract**: Po jednym przypadku na ścieżkę (reguły dzielą to samo wywołanie `AppDay`): wpis ręczny wydatku (data 2027-01-01 przyjęta, 2027-01-02 odrzucona z błędem `operation_date` „Data nie może być w przyszłości.”), podgląd Importu (wiersz 2027-01-02 ma powód `future_date`, 2027-01-01 nie), zapis Importu (wiersz 2027-01-01 zapisany), podgląd uzgodnienia salda (dzień 2027-01-01 przyjęty, 2027-01-02 odrzucony).

### Success Criteria:

#### Automated Verification:

- Testy walidacji przechodzą na PostgreSQL: `AppDayValidationTest`
- Strażnik PHP przechodzi na kodzie z wywołaniami `AppDay::today()` po fazie 1: `php artisan test --compact tests/Unit/AppDayClockGuardTest.php`
- Celowe zepsucie czerwieni strażnik: dopisanie `CarbonImmutable::today('UTC')` do dowolnego kontrolera daje czerwony test z nazwą pliku i linią (zmiana cofnięta przed commitem)
- `grep -rnE "before_or_equal:today|now\(\)->toDateString" app` nic nie wypisuje
- Pełny gate przechodzi: `composer ci:check` na `testing_one_app_day`, `vendor/bin/sloppy ci`, Pint bez zmian

#### Manual Verification:

- Użytkownik otwiera dialog wydatku z datą jutrzejszą: pod polem daty widzi „Data nie może być w przyszłości.”

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Phase 3: `shell.today` i przeglądarka

### Overview

Serwer wysyła App Day w `shell.today`, a cały kod przeglądarki (dialogi, menu roku, paleta, dialog nowego Budżetu, presety filtrów, etykiety dni, ekran logowania, strona powitalna, kitchen sinki) bierze z niego dzień. Strażnik JS pilnuje, żeby zegar przeglądarki nie wrócił.

### Changes Required:

#### 1. Props `shell.today` po obu stronach

**File**: `app/Support/ShellState.php`, `resources/js/types/shell.ts`, `tests/Feature/AppShellTest.php`

**Intent**: Przeglądarka dostaje dzień od serwera na każdej stronie, także gościa (ADR-0103, „Browser”); obie strony propsa w jednym commicie z `assertInertia` (`.ai/rules/inertia-props.md`).

**Contract**: `ShellState::forRequest` zwraca też `'today' => AppDay::today()->toDateString()` (PHPDoc shape `today: string`); `ShellState` w TS ma `today: string` (`'YYYY-MM-DD'`). Test: przy chwilach 2026-12-31 22:59:59 / 23:00:00 UTC i 2027-06-30 21:59:59 / 22:00:00 UTC `shell.today`, rok Dashboardu i `shell.budgetYear` (użytkownik z Budżetami 2026 i 2027 w wykonaniu, bez roku w sesji) dają ten sam App Day i jego rok; gość na ekranie logowania dostaje `shell.today`.

#### 2. Logika dnia w `lib`

**File**: `resources/js/lib/app-day.ts` (nowy) z `app-day.test.ts`

**Intent**: Czyste funkcje, które z `'YYYY-MM-DD'` wyprowadzają rok i zakres miesiąca (preset „ten miesiąc” i „poprzedni miesiąc”), żeby komponenty nie liczyły dat same.

**Contract**: Funkcje przyjmują App Day jako argument i nie czytają zegara (np. `appDayYear(today: string): number`, `appDayMonthRange(today: string, offset: number): { from: string; to: string }`). Vitest podaje dzień jawnie: 2027-01-15 z przesunięciem −1 daje 2026-12-01…2026-12-31; luty roku przestępnego daje 29 dni.

#### 3. Dialogi z domyślną datą

**File**: `resources/js/components/IncomeFormDialog.vue`, `ExpenseFormDialog.vue`, `OwnTransferFormDialog.vue`, `CapitalMovementFormDialog.vue`, `TermDepositFormDialog.vue`, `AccountFormDialog.vue`, `BalanceReconciliationDialog.vue`

**Intent**: Domyślna data przy otwarciu dialogu to `shell.today`; w uzgodnieniu salda także `:max` i `reconciliationDayIsAllowed`.

**Contract**: Dzień z `useShell().value.today` w chwili otwarcia; `BalanceReconciliationDialog` ma `today` jako `computed` z `useShell()`. `ExpenseFormDialog` bierze `shell.today`, nie `handEntryMonthRemaining.today` (jedno źródło w przeglądarce).

#### 4. Menu roku, paleta, nowy Budżet

**File**: `resources/js/components/AppSidebar.vue`, `BudgetYearSwitcher.vue`, `resources/js/pages/dev/SidebarKitchenSink.vue`, `resources/js/composables/useCommandPalette.ts`, `resources/js/components/CreateBudgetDialog.vue`

**Intent**: „Bieżący” i `createYear` w menu roku, rok w palecie i rok domyślny w dialogu nowego Budżetu to rok App Day (ADR-0098 zastąpione w części przez ADR-0103).

**Contract**: `AppSidebar` podaje `current-year` z `useShell()`; `BudgetYearSwitcher` ma `currentYear: number` jako prop wymagany i nie ma już zapasowego `new Date().getFullYear()`; wszystkie trzy wywołania podają rok: `AppSidebar.vue:73-76`, `dev/SidebarKitchenSink.vue:379-383` (już podaje `frame.currentYear`) i `dev/SidebarKitchenSink.vue:503-506` (dopisać `:current-year="2026"`, zgodnie z `:budget-year="2026"`); `useCommandPalette` czyta `page.props.shell.today` w chwili wywołania (jak dziś `budgetYears`); `CreateBudgetDialog` liczy rok w `computed` z `useShell()` (komponent stale zamontowany, `.ai/rules/js-sidebar.md`).

#### 5. Filtry, etykiety dni i martwy kod

**File**: `resources/js/components/OperationsFilters.vue`, `resources/js/lib/format.ts`, `resources/js/components/OperationsDayRow.vue`, `resources/js/lib/format.test.ts`

**Intent**: Presety „ten miesiąc” i „poprzedni miesiąc” liczą się od App Day; etykieta dnia dodaje rok, gdy różni się od roku App Day; `formatRelativeDate` (bez wywołań) znika.

**Contract**: `OperationsFilters` używa `appDayMonthRange` z `shell.today`; `formatDayLabel(date, today: string)` z wymaganym drugim argumentem (bez domyślnego `new Date()`), `OperationsDayRow` podaje `shell.today`; `format.test.ts` podaje dzień jako `'YYYY-MM-DD'` (plan testów §6.1).

#### 6. Strony gościa

**File**: `resources/js/layouts/auth/AuthCardLayout.vue`, `resources/js/lib/auth-year-progress.ts`, `resources/js/lib/auth-year-progress.test.ts`, `resources/js/pages/Welcome.vue`

**Intent**: Postęp roku na ekranie logowania i rok w stopce strony powitalnej biorą App Day z `shell.today` (ADR-0103: reguła nie ma wyjątku).

**Contract**: `authYearProgress(today: string)` przyjmuje `'YYYY-MM-DD'` (liczenie dni bez zmiany: kalendarzowo, przez `Date.UTC`); test podaje dzień jawnie, w tym 2027-01-01 (0 dni minionych + bieżący) i 2026-12-31.

#### 7. Kitchen sinki

**File**: `resources/js/lib/operation-dialogs-fixtures.ts` i strony `resources/js/pages/dev/*`, które go importują

**Intent**: Stany dialogów w `/dev/ui/dialogs` i `/dev/operations` budują daty od App Day, bez żywego zegara przeglądarki.

**Contract**: Fixture'y z dniem jako argumentem (np. funkcja przyjmująca `today: string`), a strona `dev` podaje `shell.today`; widok stanów bez zmian.

#### 8. Strażnik zegara w JS

**File**: `tests/Unit/AppDayClockGuardTest.php`

**Intent**: Drugi test w tym samym pliku: kod przeglądarki nie czyta zegara (plan testów §6.2, ryzyko #9).

**Contract**: Skanuje `resources/js/**/*.{ts,vue}` poza `*.test.ts` i zgłasza plik i linię dla: `new Date()` bez argumentu, `Date.now()`, `.toISOString().slice(0, 10)`. Metody wołane na przekazanej dacie (`value.getFullYear()` w `format.ts`) są dozwolone; bez listy wyjątków.

### Success Criteria:

#### Automated Verification:

- `AppShellTest` przechodzi na PostgreSQL (granice zimą i latem, gość)
- Vitest przechodzi: `npm test` (`app-day.test.ts`, `format.test.ts`, `auth-year-progress.test.ts`, `balance-reconciliation.test.ts`, `budget-year-menu.test.ts`)
- Strażnik JS przechodzi, a celowe zepsucie (dopisanie `new Date().toISOString().slice(0, 10)` w jednym dialogu) go czerwieni (zmiana cofnięta przed commitem)
- `grep -rnE "new Date\(\)|Date\.now\(\)" resources/js --include='*.ts' --include='*.vue' | grep -v '\.test\.ts'` nic nie wypisuje
- `npm run build`, `npm run types:check`, `npm run check:ui` przechodzą
- Pełny gate przechodzi: `composer ci:check` na `testing_one_app_day`, `vendor/bin/sloppy ci`, Pint bez zmian

#### Manual Verification:

- Kitchen sinki `/dev/ui/dialogs`, `/dev/operations` i `/dev/ui/auth` renderują stany bez błędów w konsoli, a domyślne daty są dzisiejszym App Day

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Phase 4: Dokumenty, reguły i próba na zegarze

### Overview

README, reguły dla agentów i plan testów opisują działające zachowanie, a przeglądarka na zegarze próby pokazuje jeden dzień wokół polskiej północy.

### Changes Required:

#### 1. README „Przełom roku”

**File**: `README.md` (sekcja „Przełom roku”, ok. `:290-305`)

**Intent**: Procedura opisuje zmianę roku o 00:00 czasu polskiego i twardy termin grudnia 31.12 23:59:59 czasu polskiego (ADR-0103, „Year turn”), zamiast 01:00 i 23:59:59 UTC.

**Contract**: Zdanie wstępne i krok 1; w akapicie „Próba przed przełomem” (`README.md:318`) usunięte zdanie „Zegar przeglądarki ustaw osobno (`playwright-cli run-code … page.clock.setFixedTime …`)”, bo przeglądarka bierze dzień z `shell.today` (ADR-0103, „Rehearsal clock”); reszta procedury bez zmian.

#### 2. Reguła App Day dla agentów

**File**: `.ai/rules/app-day.md` (nowy), `.ai/rules/index.md` (przez `php artisan boost:index-rules`)

**Intent**: Agent edytujący `app/` albo `resources/js` wie, skąd brać dzień, zanim strażnik go zatrzyma (ADR-0103, „Consequences”).

**Contract**: Frontmatter `paths:` na `app/**/*.php` i `resources/js/**/*.{ts,vue}`. Treść: „dziś” na serwerze tylko z `AppDay::today()`, dzień chwili z `AppDay::of()`, znaczniki czasu zostają `now()` w UTC; w przeglądarce dzień tylko z `shell.today` (komponenty w layoucie przez `useShell()`), logika w `lib` przyjmuje dzień jako argument; testy na granicach 22:59:59/23:00 UTC i 21:59:59/22:00 UTC, nigdy na północy UTC; strażnik `tests/Unit/AppDayClockGuardTest.php`.

#### 3. Reguła ekranów wejścia

**File**: `.ai/rules/js-auth.md`

**Intent**: Usuwa sprzeczność z ADR-0103: blok roku liczy `authYearProgress` z `shell.today`.

**Contract**: Zdanie „The year block comes from `authYearProgress` …, not from an Inertia prop.” zmienione na wersję z `shell.today`.

#### 4. Plan testów

**File**: `context/foundation/test-plan.md` (§6.1 „Dzień „dziś” w Vitest”, §6.2 „Przełom roku i północ w Polsce”)

**Intent**: Cookbook opisuje stan po S-39 zamiast zapowiedzi.

**Contract**: §6.1: `format.test.ts` podaje już `'YYYY-MM-DD'`, wzorzec `app-day.test.ts`. §6.2: usunięte „Do tego czasu przeglądarka czyta własny zegar”; strażnik to `tests/Unit/AppDayClockGuardTest.php` (PHP: `app/`, JS: `resources/js` bez testów); testy przypięte do północy UTC przepisane w S-39. Tabele §2–§5 bez zmian.

### Success Criteria:

#### Automated Verification:

- Indeks reguł zawiera nową regułę: `grep -c 'app-day.md' .ai/rules/index.md` zwraca `1`
- README nie mówi o UTC przy przełomie ani o zegarze przeglądarki: `grep -n '23:59:59 UTC\|01:00 czasu warszawskiego\|page.clock' README.md` nic nie wypisuje
- `js-auth.md` nie mówi już „not from an Inertia prop”: `grep -c 'not from an Inertia prop' .ai/rules/js-auth.md` zwraca `0`
- Pełny gate przechodzi na gałęzi po rebase na bieżący `development`: `npm run build`, `composer ci:check` na `testing_one_app_day`, `vendor/bin/sloppy ci`

#### Manual Verification:

- Próba w przeglądarce na kopii dev (`worktree-preview.md`, bez `page.clock`): `app:rehearsal-clock "2026-12-31 23:30"` → Dashboard „1 stycznia 2027”, menu roku oznacza 2027 „bieżący”, domyślna data dialogu wydatku i uzgodnienia salda 2027-01-01, wydatek z datą 2027-01-01 zapisuje się (Budżet 2027 w wykonaniu)
- `app:rehearsal-clock "2026-12-31 22:30"` → wszędzie 31 grudnia 2026, grudzień ma „Zatwierdź”; potem `app:rehearsal-clock --off`
- Ekran logowania o 23:30 UTC pokazuje rok 2027 w bloku postępu roku
- Użytkownik czyta README „Przełom roku” i nową regułę `.ai/rules/app-day.md`

**Implementation Note**: Po zielonych kontrolach i ręcznej próbie zmiana jest gotowa do scalenia do `development` (procedura `context/foundation/agents/parallel-changes.md`).

---

## Testing Strategy

### Unit Tests:

- `tests/Unit/AppDayClockGuardTest.php`: dwa strażniki statyczne (PHP `app/`, JS `resources/js` bez testów), każdy sprawdzony celowym zepsuciem.
- Vitest z dniem jako argumentem: `app-day.test.ts` (rok, zakres miesiąca przez przełom roku i luty przestępny), `format.test.ts` (rok w etykiecie dnia względem App Day), `auth-year-progress.test.ts` (1 stycznia i 31 grudnia). Bez fake timerów.

### Integration Tests:

- `AppDayTest`: granice zimą, latem i w dni zmiany czasu, `of()` dla archiwizacji o 00:30 czasu polskiego.
- `AppShellTest`: `shell.today`, rok Dashboardu i `shell.budgetYear` zgodne na czterech granicach; gość dostaje `shell.today`.
- `YearTurnTest`, `BudgetCollectedAtYearStartTest`, `SelectedBudgetYearTest`: granice przeniesione na polską północ (zmiana wymagania).
- Walidacja: po jednym przypadku na ścieżkę wpisu (ręczny, podgląd i zapis Importu, uzgodnienie salda) o 23:30 UTC 31 grudnia.
- Archiwizacja o 23:30 UTC ostatniego dnia miesiąca: Propozycja, Collected, lista Kategorii, `ArchiveCategory`.

### Manual Testing Steps:

1. `app:rehearsal-clock "2026-12-31 23:30"`, zalogować się ponownie (skok zegara >2 h wylogowuje), sprawdzić Dashboard, menu roku, paletę (Ctrl+K: „nowy rok”), dialog wydatku i uzgodnienia salda.
2. `app:rehearsal-clock "2026-12-31 22:30"`: wszystko w 31 grudnia, grudzień do zatwierdzenia.
3. Ekran logowania o 23:30 UTC: rok 2027.
4. `app:rehearsal-clock --off`.

## Migration Notes

Bez migracji. Istniejące `archived_at` Kategorii z pierwszej godziny po północy czasu polskiego (dwóch latem) od wdrożenia liczą się do następnego dnia (ADR-0103, „Data”). Do wdrożenia przed 31 grudnia 2026 (roadmapa, Open Questions 2).

## References

- Roadmapa: S-39 w `context/foundation/roadmap.md` (MS-01, gwiazda przewodnia)
- Reguła: `context/foundation/adr/0103-one-app-day-in-europe-warsaw.md`, wpis **App Day** w `context/foundation/glossary.md`
- Spis miejsc: `context/archive/2026-11-18-app-day-decision/plan.md` („Spis miejsc w kodzie”)
- Próba przełomu: `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md` (T2, luka 4)
- Plan testów: `context/foundation/test-plan.md` (ryzyko #9, §5 bramka „testy przełomu roku”, §6.1, §6.2)
- Precedens strażnika: `tests/Unit/MonthPlanHardcodedUiTest.php`
- Reguły: `.ai/rules/inertia-props.md`, `.ai/rules/js-sidebar.md` („A component mounted in the layout reads `shell` reactively”), `.ai/rules/js-auth.md`, `.ai/rules/js-dialogs.md`
- Kod: `app/Support/ShellState.php:25-45`, `app/Support/SelectedBudgetYear.php:36`, `app/Support/MonthPlanProposal.php:180-184`, `app/Support/RehearsalClock.php`, `resources/js/components/BudgetYearSwitcher.vue:82`, `resources/js/composables/useTranslations.ts:12`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: `AppDay` i dzień na serwerze

#### Automated

- [x] 1.1 Nowe i zmienione testy przechodzą na PostgreSQL (AppDayTest, YearTurnTest, BudgetCollectedAtYearStartTest, SelectedBudgetYearTest, archiwizacja) — 6ad3c98
- [x] 1.2 Brak `today('UTC')` w `app` i `tests` — 6ad3c98
- [x] 1.3 Brak `now()->year|month` w `app` — 6ad3c98
- [x] 1.4 Pełny gate przechodzi (ci:check, Sloppy, Pint) — 6ad3c98

#### Manual

- [ ] 1.5 Użytkownik potwierdza granice polskiej północy w YearTurnTest i BudgetCollectedAtYearStartTest

### Phase 2: „Nie później niż dziś” na serwerze i strażnik PHP

#### Automated

- [x] 2.1 Testy walidacji przechodzą na PostgreSQL — 895a687
- [x] 2.2 Strażnik PHP przechodzi — 895a687
- [x] 2.3 Celowe zepsucie czerwieni strażnik PHP — 895a687
- [x] 2.4 Brak `before_or_equal:today` i `now()->toDateString` w `app` — 895a687
- [x] 2.5 Pełny gate przechodzi (ci:check, Sloppy, Pint) — 895a687

#### Manual

- [ ] 2.6 Dialog wydatku z datą jutrzejszą pokazuje „Data nie może być w przyszłości.”

### Phase 3: `shell.today` i przeglądarka

#### Automated

- [ ] 3.1 AppShellTest przechodzi na PostgreSQL
- [ ] 3.2 Vitest przechodzi
- [ ] 3.3 Strażnik JS przechodzi, a celowe zepsucie go czerwieni
- [ ] 3.4 Brak `new Date()` i `Date.now()` w `resources/js` poza testami
- [ ] 3.5 build, types:check i check:ui przechodzą
- [ ] 3.6 Pełny gate przechodzi (ci:check, Sloppy, Pint)

#### Manual

- [ ] 3.7 Kitchen sinki dialogów, operacji i ekranów wejścia renderują się z dniem App Day

### Phase 4: Dokumenty, reguły i próba na zegarze

#### Automated

- [ ] 4.1 Indeks reguł zawiera app-day.md
- [ ] 4.2 README nie mówi o UTC przy przełomie ani o page.clock
- [ ] 4.3 js-auth.md nie mówi „not from an Inertia prop”
- [ ] 4.4 Pełny gate przechodzi na gałęzi po rebase

#### Manual

- [ ] 4.5 Próba o 2026-12-31 23:30 UTC: jeden dzień 2027-01-01 w Dashboardzie, menu roku i dialogach
- [ ] 4.6 Próba o 2026-12-31 22:30 UTC: wszędzie 31 grudnia, grudzień do zatwierdzenia
- [ ] 4.7 Ekran logowania o 23:30 UTC pokazuje rok 2027
- [ ] 4.8 Użytkownik czyta README „Przełom roku” i regułę app-day.md
