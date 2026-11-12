# Domyślny procent „odkładam” w propozycji planu — plan implementacji

## Overview

Użytkownik odkłada co miesiąc ok. 10 % dochodu (PRD v3 `:65`, `:150`), a dziś wpisuje tę kwotę ręcznie albo dziedziczy ją z poprzedniego miesiąca. Ta zmiana (roadmapa S-33, FR-024) dodaje jedno ustawienie użytkownika: domyślny procent planowanych przychodów. Gdy jest ustawiony, propozycja planu miesiąca wpisuje jako „odkładam” ten procent sumy planowanych przychodów tej propozycji, zaokrąglony w dół do pełnego złotego, zamiast kopiować „odkładam” z poprzedniego miesiąca. Bez ustawienia działa dzisiejsza reguła. Wzór „do wydania” i reguła „odkładam / dobieram” (FR-013) się nie zmieniają, zmienia się tylko kwota startowa propozycji.

## Current State Analysis

- Kwotę „odkładam” propozycji liczy tylko `MonthPlanProposal::computePlan` (`app/Support/MonthPlanProposal.php:181`): `max(0, $source->setAsideMinorUnits ?? 0)`. Źródło to poprzedni miesiąc w dowolnym stanie, a dla stycznia grudzień poprzedniego budżetu w realizacji (`sourceFor`, `:188-203`). To reguła ADR-0033:5 i hasła Set-aside w słowniku (`context/foundation/glossary.md:108-109`), ustalona w S-02 (`context/archive/2026-11-02-set-aside-and-draw/plan-brief.md:23`).
- Propozycja jest odczytem, a nie zapisem. Zatwierdzenie zapisuje kwotę, którą wysłał formularz (`ApproveMonthPlan.php:72-75`, `ApproveMonthPlanRequest.php:28` `required`). Odbiorcy `MonthPlanProposal::forBudget` (`ApproveMonthPlan.php:34`, `MonthToSpend.php:38`, `EntryToSpendInputs.php:86`), a przez `MonthToSpend` także Dashboard, `MonthSummary` i ostrzeżenia operacji oraz Importu, dostaną nową kwotę bez własnych zmian.
- Planowane przychody propozycji to `$amounts[$line->id]` linii, których kategoria jest typu przychód (`BudgetLine::hasType`, `app/Models/BudgetLine.php:70-72`; `category` jest zawsze eager-loaded, `:50`). Linie zarchiwizowane przed pierwszym dniem miesiąca są pomijane (`MonthPlanProposal.php:173-175`). Nie istnieje helper „sumy planowanych przychodów”: `ToSpendCalculator::breakdown` przełącza przychód na kwotę faktyczną po pierwszym wpływie, więc nie jest planem.
- Nie ma tabeli ustawień. `budgets` nie ma kolumn ustawień. Jedyny wzór preferencji użytkownika to `users.first_run_checklist_dismissed_at` (`create_users_table.php:21`): rzutowanie w `User::casts()` (`app/Models/User.php:44-52`), backup (`BackupUserData.php:35`), restore (`RestoreUserData.php:93-97`) i reset (`ResetUserData.php:34-36`).
- Ustawienia mają trzy zakładki: Profil, Bezpieczeństwo, Wygląd (`routes/settings.php`, `resources/js/layouts/settings/Layout.vue:13-26`). Wzór formularza to `ProfileController::update` (`fill(validated())`, toast `Inertia::flash('toast', …)`, `to_route`) i `settings/Profile.vue` (`<Form>` obejmuje `SettingsPanel`, `FormField`, przycisk w stopce; ADR-0049). Strony ustawień są w `SettingsHardcodedUiTest.php` (`SETTINGS_VIEW_FILES`, `:8-17`), w `scripts/check-ui-literals.mjs` (`:55-63`) i w `/dev/ui/settings` (`resources/js/pages/dev/SettingsKitchenSink.vue`).
- Frontend nie wylicza kwoty „odkładam”. `MonthPlan.vue:102-116` wypełnia pole wartością `monthPlan.set_aside_minor_units`. Sekcja „Odkładam / dobieram” jest w `MonthPlanTable.vue:357-418`, z komórką `month-plan-cell-plan` (`:378-405`) i podpisem `meta text-muted-foreground` (`setAsideEffect`, `:406-413`).
- Snapshot ma wersję 10 (`BackupUserData::SNAPSHOT_VERSION`, `:20`). `RestoreUserData` wymienia wersje jawnie (`:36`, `:44`, `:128-154`).

## Desired End State

- W Ustawieniach jest zakładka „Plan miesiąca” (`/settings/month-plan`) z polem „Domyślne odkładam” w procentach. Puste pole zapisuje brak procentu, a dozwolone wartości to liczby całkowite 0–100. Po zapisie pojawia się toast, a wartość spoza zakresu daje błąd pola.
- Przy ustawionym procencie `p` każda propozycja (każdy miesiąc w stanie proposal, także w poprzednim budżecie, gdy służy za źródło) ma „odkładam” = `floor(P × p / 100 / 100 zł)` w pełnych złotych, gdzie `P` to suma planowanych przychodów tej propozycji. Przykład: `p = 10`, `P = 8 437,55 zł` → 843,00 zł; `P = 9 000 zł` → 900,00 zł; `p = 0` → 0. Przy braku procentu (`null`) zachowanie jest jak dziś. Zatwierdzone plany i kwota wpisana ręcznie przed zatwierdzeniem się nie zmieniają.
- W propozycji z ustawionym procentem pod polem „odkładam” widać „10 % planowanych przychodów (9 000,00) · Zmień w ustawieniach”, z linkiem do nowej zakładki. W zatwierdzonym planie, w trybie zmiany i bez procentu tej linii nie ma.
- `user:backup` zapisuje procent w snapshocie wersji 11. Restore wersji 11 go odtwarza, wersje 1–10 przywracają `null`, a wartość spoza 0–100 albo niebędąca liczbą całkowitą odrzuca snapshot. `user:reset-data` zeruje procent.
- Słownik (Set-aside) i ADR-0081 opisują nową regułę propozycji.

### Key Discoveries:

- Jedna linia zmienia kwotę dla wszystkich odbiorców: `MonthPlanProposal.php:181`. Podstawę `P` liczy się z już obliczonych `$amounts` w tej samej pętli (`:172-179`).
- `MonthPlanReading` (`app/Support/MonthPlanReading.php`) to jedyny nośnik między propozycją a kontrolerem (`MonthPlanController.php:194` czyta `$reading->setAsideMinorUnits`), więc podstawa podpowiedzi jedzie tą samą drogą.
- Wzór toastu ustawień: `lang/pl.json:2` (`"Profile updated.": "Profil zapisany."`). Teksty stron są w `lang/pl/ui.php` `settings.*` (`:123-155`), a teksty planu w `month_plan.*` (`:424+`).
- Kwoty w wierszu siatki planu nie mają „zł”, bo jednostka stoi w stopce (ADR-0058, `.ai/rules/js.md`). Podpowiedź używa więc `formatMoneyAmount`, a nie `formatMoney`.
- `MonthPlanSink.vue` przekazuje każdy prop do `<MonthPlanTable>` ręcznie (`:671-700`, `TableConfig` `:125-148`), więc nowy prop dochodzi też tam.

## What We're NOT Doing

- Procent nie przelicza się na żywo przy edycji przychodów w formularzu propozycji. Nie powstaje kopia w TS (decyzja planu, `.ai/rules/month-calculators.md`).
- Bez procentu z miejscem po przecinku i bez zaokrąglania do grosza.
- Bez procentu na budżet roczny i bez pola na ekranie Budżetu.
- Bez ustawiania procentu z poziomu planu miesiąca (jest tylko link do Ustawień).
- Bez wartości startowej 10 % w migracji. Po wdrożeniu procent jest pusty.
- Bez zmiany wzoru „do wydania”, reguły Draw, ostrzeżenia |Draw| > Spendable Liquidity, Podsumowania miesiąca i zatwierdzonych planów.
- Bez procentu od kwot faktycznych przychodów. Podstawą jest plan propozycji.
- Bez celów i nazwanych pozycji odkładania (PRD v3 Non-Goals).

## Implementation Approach

Najpierw decyzja w dokumentach (słownik, ADR-0081), bo zmienia się reguła słownika (AGENTS.md). Potem ustawienie z trwałością (kolumna, zakładka Ustawień, snapshot, reset), które jeszcze niczego nie zmienia w planie. Następnie reguła w `MonthPlanProposal` z testami regresji. Na końcu podpowiedź w planie miesiąca, czyli prop PHP i TS w jednym commicie. Każda faza kończy się zielonymi testami i osobnym commitem `feat(default-set-aside-percent): …`.

## Critical Implementation Details

### State sequencing

Procent czyta się raz w `MonthPlanProposal::forBudget` z relacji `$budget->user`, a nie w `computePlan`, bo `computePlan` jest wołane rekurencyjnie dla 12 + 12 miesięcy (Sloppy flaguje zapytania w pętli). Żaden wywołujący nie ma dziś załadowanego `$budget->user`, a `EntryToSpendInputs::budgetPayload` woła `forBudget` dla każdego budżetu w `map` (`app/Support/EntryToSpendInputs.php:56-62`, `:86`). Dlatego `User::budgets()` dostaje `->chaperone()` (`app/Models/User.php:81-84`): budżety z `$user->budgets()` (`MonthPlanController.php:43`, `DashboardController.php:174`, `EntryToSpendInputs.php:46`) mają właściciela bez zapytania, a Approve (budżet z wiązania trasy) robi jedno zapytanie raz na żądanie. Sygnatura `forBudget` się nie zmienia. Kwotę liczy się po zbudowaniu `$amounts`, z tych samych kwot, które widzi użytkownik, czyli bez linii zarchiwizowanych.

## Faza 1: Słownik i ADR-0081

### Overview

Zapisuje nową regułę propozycji Set-aside, zanim powstanie kod (AGENTS.md, „A change to a glossary rule…”).

### Changes Required:

#### 1. ADR

**File**: `context/foundation/adr/0081-proposal-set-aside-from-default-percent.md`

**Intent**: Decyzja: domyślny procent użytkownika (całkowity 0–100 albo brak) zastępuje w propozycji kopiowanie dodatniego Set-aside ze źródła. Kwota to procent sumy planowanych przychodów tej propozycji, w dół do pełnego złotego. Brak procentu zostawia regułę ADR-0033:5. Uzasadnienie i odrzucone warianty z tego planu: procent tylko przy braku źródła, większa z dwóch kwot, procent na budżet, procent dziesiętny i do grosza, przeliczanie na żywo, migracja z 10 %.

**Contract**: Styl ADR-0075 (tytuł, kontekst, reguła, przykłady 8 437,55 → 843 i 9 000 → 900, relacja do ADR-0033 i ADR-0062, „We rejected:”). Zawiera zdanie, że ADR-0033:5 obowiązuje tylko przy braku procentu.

#### 2. Słownik

**File**: `context/foundation/glossary.md`

**Intent**: Hasło Set-aside mówi, że propozycja wpisuje Default Set-aside Percent planowanych przychodów, gdy jest ustawiony, a w przeciwnym razie kopiuje dodatni Set-aside ze źródła. Dochodzi polski tekst UI ustawienia.

**Contract**: Zmienione zdanie w **Set-aside** (`:109`) z odwołaniem do ADR-0081. Nowe hasło **Default Set-aside Percent**: ustawienie użytkownika, całkowite 0–100 albo brak, podstawa to suma planowanych przychodów propozycji (Proposal), w dół do pełnego złotego, działa tylko na propozycje, Polish UI copy: „domyślne odkładam”, „% planowanych przychodów”; _Avoid_: Savings rate, Reserve, Irregular Rate. W haśle **Month Plan** (`:85`) odwołanie do ADR-0081 obok ADR-0033 i ADR-0066.

### Success Criteria:

#### Automated Verification:

- ADR-0081 istnieje, a słownik odwołuje się do niego: `grep -n "ADR-0081" context/foundation/glossary.md`

#### Manual Verification:

- Hasła Set-aside i Default Set-aside Percent zgadzają się z decyzjami planu (zastępuje kopiowanie, 0–100, w dół do złotego, puste = kopiowanie)

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Ustawienie procentu

### Overview

Kolumna na `users`, zakładka Ustawienia → „Plan miesiąca” z zapisem i walidacją, stan w kitchen sinku, snapshot w wersji 11 i reset. Plan miesiąca jeszcze z tego nie korzysta.

### Changes Required:

#### 1. Migracja i model

**File**: `database/migrations/<timestamp>_add_default_set_aside_percent_to_users_table.php`, `app/Models/User.php`, `database/factories/UserFactory.php`

**Intent**: Dodać nullable kolumnę procentu (bez wartości domyślnej). Zrzutować ją na `integer` i opisać w `@property`.

**Contract**: `users.default_set_aside_percent` `unsignedTinyInteger`, nullable. `down()` usuwa kolumnę (stratnie: ginie tylko zapisany procent). Rzutowanie `'default_set_aside_percent' => 'integer'`. Kolumna nie trafia do `#[Fillable]`, a zapis idzie przez `forceFill` albo jawne przypisanie, jak `first_run_checklist_dismissed_at`.

#### 2. Trasa, kontroler, request

**File**: `routes/settings.php`, `app/Http/Controllers/Settings/MonthPlanSettingsController.php`, `app/Http/Requests/Settings/MonthPlanSettingsUpdateRequest.php`, `lang/pl.json`

**Intent**: Strona i zapis ustawienia według wzoru `ProfileController`.

**Contract**: W grupie `auth` (obok profilu): `GET settings/month-plan` → `month-plan-settings.edit` renderuje `settings/MonthPlan` z propem `defaultSetAsidePercent: int|null`; `PATCH settings/month-plan` → `month-plan-settings.update`, reguła `default_set_aside_percent` `nullable|integer|between:0,100`, puste pole zapisuje `null`. Toast `__('Month plan settings updated.')` → „Ustawienia planu zapisane.” w `lang/pl.json`. Przekierowanie `to_route('month-plan-settings.edit')`.

#### 3. Strona i nawigacja

**File**: `resources/js/pages/settings/MonthPlan.vue`, `resources/js/layouts/settings/Layout.vue`, `resources/js/components/FormField.vue`, `lang/pl/ui.php`

**Intent**: Zakładka „Plan miesiąca” po „Wygląd”, z jednym `SettingsPanel`: pole liczby z sufiksem „%”, opis „Propozycja planu wpisuje ten procent planowanych przychodów miesiąca jako odkładam, w dół do pełnego złotego. Puste pole: propozycja kopiuje odkładam z poprzedniego miesiąca.” i przycisk „Zapisz”.

**Contract**: ADR-0049 (bez `h1` i `Heading`, `<Form v-bind="MonthPlanSettingsController.update.form()">` obejmuje panel, `FormField` z `errors.default_set_aside_percent` i `unit="%"`, `Input` `type="number"` `min=0` `max=100` `step=1` `inputmode="numeric"`, `name="default_set_aside_percent"`). Nowe klucze `settings.month_plan`, `settings.month_plan_title`, `settings.month_plan_description`, `settings.default_set_aside_percent`, `settings.default_set_aside_percent_help`. Nawigacja importuje `edit as editMonthPlanSettings` z `@/routes/month-plan-settings`. `defineProps<{ defaultSetAsidePercent: number | null }>()`. `FormField` dostaje opcjonalny prop `unit?: string` (sufiks w `.form-field-unit`, ta sama `controlClass` co przy `money`); `money` dalej pokazuje `MONEY_UNIT`, więc obecne pola się nie zmieniają, a `components/ui` zostaje nietknięte (`.ai/rules/js-dialogs.md` przed edycją).

#### 4. Strażnicy UI i kitchen sink

**File**: `tests/Unit/SettingsHardcodedUiTest.php`, `scripts/check-ui-literals.mjs`, `resources/js/pages/dev/SettingsKitchenSink.vue`

**Intent**: Nowa strona trafia na obie listy strażników. Sink pokazuje ją w stanach: pusta, 10 %, błąd pola („musi być między 0 a 100”) i zapis w toku.

**Contract**: `resources/js/pages/settings/MonthPlan.vue` w `SETTINGS_VIEW_FILES` i w `FILES` skryptu. Sekcja `data-section="month-plan"` (i warianty) w sinku, z jawnie podanym propem `defaultSetAsidePercent`. Tekst `stateMatrix` w `SettingsKitchenSink.vue` (`:56`, dziś „Profil, Bezpieczeństwo … i Wygląd”) wymienia też „Plan miesiąca”.

#### 5. Snapshot i reset

**File**: `app/Actions/BackupUserData.php`, `app/Actions/RestoreUserData.php`, `app/Actions/ResetUserData.php`, `.ai/rules/snapshot.md`, `README.md`

**Intent**: Procent jest częścią danych użytkownika jak `first_run_checklist_dismissed_at`. Wersja 11 go niesie, starsze wersje przywracają `null`, a reset go zeruje. Dochodzi też notatka o wycofaniu S-33.

**Contract**: `SNAPSHOT_VERSION = 11`; klucz najwyższego poziomu `default_set_aside_percent` (`int|null`). W `RestoreUserData` 10 dochodzi do każdej listy wersji, która dziś kończy się stałą (`LIST_FORMAT_VERSIONS` `:36`, `RHYTHM_VERSIONS` `:44`, listy w `:128-154`), klucz jest wymagany tylko w 11, a `UserSnapshotException` odrzuca wartość, która nie jest liczbą całkowitą 0–100 ani `null`. `ResetUserData` ustawia `null`. W `.ai/rules/snapshot.md` zdanie „Version 11 (S-33) adds `default_set_aside_percent`…”. W README akapit „Wycofanie S-33”: migracja dodaje jedną kolumnę, liczby kontrolne się nie zmieniają, `down()` traci tylko procent, a snapshot v11 nie wczyta się w kodzie sprzed zmiany. W README wiersz `user:backup` (`:124`, dziś nieaktualne „wersja 9”) mówi „wersja 11”. W `tests/Feature/UserSnapshotCommandTest.php` asercje `assertSame(10, $snapshot['version'])` (`:57`, `:240`, `:1018`, `:1413`) zmieniają się na 11, bo zmienia się wymaganie. Typ TS `User` (`resources/js/types/auth.ts`) ma sygnaturę indeksu i zostaje bez zmian.

#### 6. Testy

**File**: `tests/Feature/Settings/MonthPlanSettingsTest.php`, `tests/Feature/Migrations/DefaultSetAsidePercentMigrationTest.php`, `tests/Feature/UserSnapshotCommandTest.php`, `tests/Feature/ResetUserDataCommandTest.php`, `tests/Feature/SettingsKitchenSinkTest.php`

**Intent**: Pokrycie granic i trwałości.

**Contract**:
- Strona: gość jest przekierowany, a zalogowany widzi `assertInertia` `component('settings/MonthPlan')->where('defaultSetAsidePercent', null|10)`.
- Zapis: 10, 0, 100 i puste (`null`) przechodzą z toastem „Ustawienia planu zapisane.”; −1, 101, 10.5 i „abc” dają błąd pola, a wartość się nie zmienia.
- Snapshot: backup v11 zawiera procent i round-trip go odtwarza; snapshot v10 (zbudowany w teście, jak `asV9Snapshot`) przywraca `null`; 101 i „10” jako tekst odrzucają snapshot.
- Reset: zeruje procent.
- Migracja (`.ai/rules/migrations.md`, wzór `tests/Feature/Migrations/CategoryDescriptionMigrationTest.php`, tylko pgsql): `migrate:rollback --path` samej migracji usuwa kolumnę, a przebieg w przód daje nullable kolumnę całkowitą (`smallint` na PostgreSQL) i `null` u istniejących użytkowników.

### Success Criteria:

#### Automated Verification:

- Testy fazy przechodzą na PostgreSQL (worktree, `DB_DATABASE=testing_default_set_aside_percent`): `artisan test --compact tests/Feature/Settings/MonthPlanSettingsTest.php tests/Feature/Migrations/DefaultSetAsidePercentMigrationTest.php tests/Feature/UserSnapshotCommandTest.php tests/Feature/ResetUserDataCommandTest.php tests/Feature/SettingsKitchenSinkTest.php tests/Unit/SettingsHardcodedUiTest.php`
- `npm run build` i `npm run types:check` przechodzą (nowa trasa Wayfinder)
- `npm run check:ui` przechodzi z nową stroną na liście
- `vendor/bin/pint --dirty --format agent` nie zostawia zmian, a `vendor/bin/sloppy ci` nie zgłasza nowych znalezisk

#### Manual Verification:

- W `/settings/month-plan` zapis 10 pokazuje toast i wartość po przeładowaniu; puste pole wraca jako puste; 101 pokazuje błąd pola
- `/dev/ui/settings` pokazuje nową zakładkę we wszystkich stanach w trybie jasnym i ciemnym

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: Propozycja liczy procent

### Overview

`MonthPlanProposal` wpisuje procent planowanych przychodów jako „odkładam” propozycji, gdy użytkownik ma ustawiony procent. Najpierw nieprzechodzące testy reguły, potem kod.

### Changes Required:

#### 1. Odczyt planu

**File**: `app/Support/MonthPlanReading.php`

**Intent**: Odczyt propozycji niesie podstawę kwoty, żeby kontroler mógł pokazać podpowiedź (faza 4) bez ponownego liczenia.

**Contract**: Nowy opcjonalny parametr konstruktora `?array $setAsideDefault = null` o kształcie `array{percent: int, income_minor_units: int}`, ustawiany tylko w stanie `proposal` przy niepustym procencie. Pozostałe stany i istniejące wywołania się nie zmieniają.

#### 2. Reguła propozycji

**File**: `app/Support/MonthPlanProposal.php`, `app/Models/User.php`

**Intent**: W `forBudget` odczytać procent użytkownika budżetu (raz, z `$budget->user`; `User::budgets()` z `->chaperone()`, patrz State sequencing). W `computePlan`, w gałęzi propozycji, przy niepustym procencie zsumować `$amounts` linii przychodów i wpisać `intdiv(max(0, P) × p, 10 000) × 100` jako Set-aside (w dół do pełnego złotego). Bez procentu zostaje `max(0, $source->setAsideMinorUnits ?? 0)`. To samo obowiązuje dla propozycji poprzedniego budżetu, bo procent należy do użytkownika.

**Contract**: Nowe pole konstruktora `?int $defaultSetAsidePercent`. `forBudget(Budget, CarbonImmutable)` bez zmiany sygnatury. Gałęzie `approved` i `none` bez zmian. Docblock klasy z odwołaniem do ADR-0081.

#### 3. Testy

**File**: `tests/Feature/MonthPlanProposalTest.php` (oraz `tests/Feature/MonthPlanPageTest.php` dla jednego przypadku end-to-end przez prop)

**Intent**: Regresja nowej reguły, dopisana i czerwona przed zmianą w kodzie.

**Contract**: Przypadki:
- procent 10, przychody propozycji 8 437,55 → 843,00, a 9 000 → 900,00;
- procent zastępuje kopiowanie, gdy poprzedni miesiąc jest zatwierdzony z „odkładam” 1 500 i przychodami 9 000 → 900;
- procent 0 → 0, także przy dodatnim źródle;
- brak procentu → dzisiejsze kopiowanie (dodatnie źródło kopiowane, Draw daje 0);
- styczeń bez poprzedniego budżetu z procentem → procent przychodów stycznia;
- linia przychodu zarchiwizowana przed miesiącem nie wchodzi do podstawy;
- miesiąc bez przychodów → 0;
- zatwierdzony plan zachowuje zapisaną kwotę po ustawieniu procentu;
- `setAsideDefault` ma procent i podstawę tylko w propozycji przy procencie;
- strona planu: `monthPlan.set_aside_minor_units` równe procentowi;
- budżety zapytań: odczyt właściciela to świadomie nowe wymaganie, więc `tests/Feature/MonthToSpendTest.php:160` (`assertSame(9)` → 10) i limit `tests/Feature/MonthPlanProposalTest.php:443` rosną o jedno zapytanie z komentarzem (budżet z fabryki nie ma relacji `user`); `EntryToSpendInputsTest` pokazuje, że budżety z `$user->budgets()` nie dodają zapytania na budżet.

### Success Criteria:

#### Automated Verification:

- Nowe testy w `MonthPlanProposalTest.php` są czerwone przed zmianą `MonthPlanProposal.php` i zielone po niej
- Testy planu przechodzą na PostgreSQL: `artisan test --compact tests/Feature/MonthPlanProposalTest.php tests/Feature/MonthPlanPageTest.php tests/Feature/ApproveMonthPlanTest.php tests/Feature/ChangeMonthPlanTest.php tests/Feature/MonthToSpendTest.php tests/Feature/EntryToSpendInputsTest.php`
- `vendor/bin/pint --dirty --format agent` nie zostawia zmian, a `vendor/bin/sloppy ci` nie zgłasza nowych znalezisk (brak zapytania w `computePlan`)

#### Manual Verification:

- Na bazie podglądu z procentem 10 propozycja bieżącego lub następnego miesiąca pokazuje „odkładam” równe 10 % planowanych przychodów w dół do złotego, a zatwierdzony miesiąc pokazuje kwotę bez zmian

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: Podpowiedź w planie miesiąca

### Overview

Pod polem „odkładam” w propozycji z procentem widać, skąd jest kwota, z linkiem do Ustawień. Prop PHP i typ TS lądują w jednym commicie z `assertInertia` (`.ai/rules/inertia-props.md`).

### Changes Required:

#### 1. Prop

**File**: `app/Http/Controllers/MonthPlanController.php`, `resources/js/types/month-plan.ts`

**Intent**: Przekazać podstawę z `MonthPlanReading::$setAsideDefault`.

**Contract**: Klucz `set_aside_default` (`array{percent: int, income_minor_units: int}|null`) w kształcie phpdoc (`:134-160`) i w tablicy zwrotnej (`:182-205`), obok `set_aside_minor_units`. `null` poza propozycją z procentem. W TS `set_aside_default: { percent: number; income_minor_units: number } | null` w `MonthPlanReading`, obok `set_aside_minor_units` (`:97`).

#### 2. Linia podpowiedzi

**File**: `resources/js/components/MonthPlanTable.vue`, `resources/js/pages/MonthPlan.vue`, `lang/pl/ui.php`

**Intent**: Pod polem w `month-plan-cell-plan` pojawia się wiersz `meta text-muted-foreground`: „{percent} % planowanych przychodów ({kwota})” i `TextLink` „Zmień w ustawieniach” do `month-plan-settings.edit`. Wiersz pojawia się tylko wtedy, gdy pole jest edytowalne w propozycji (nie w trybie zmiany) i `set_aside_default` nie jest `null`.

**Contract**: Nowy prop `MonthPlanTable` `setAsideDefault: { percent: number; incomeMinorUnits: number } | null` (strona przekazuje `null` w trybie zmiany). Kwota przez `formatMoneyAmount` (bez „zł”, ADR-0058). Id wiersza (`month-plan-set-aside-default`) w `aria-describedby` pola obok id błędu. Klucze `month_plan.set_aside_default_basis` (z `:percent` i `:amount`) i `month_plan.set_aside_default_change`. Nowa klasa w `app.css` tylko wtedy, gdy obecne klasy nie wystarczą (`.ai/rules/js-month-plan.md`).

#### 3. Kitchen sink i strażnicy

**File**: `resources/js/lib/month-plan-fixtures.ts`, `resources/js/pages/dev/MonthPlanSink.vue`, `tests/Unit/MonthPlanHardcodedUiTest.php`

**Intent**: Stan „propozycja z domyślnym procentem” w `/dev/month-plan`. Pozostałe readingi fixture dostają `set_aside_default: null`.

**Contract**: Pole `setAsideDefault` w `TableConfig` i `tableConfig()`, przekazane do `<MonthPlanTable>`. Nowa pozycja `tableConfig('proposal-default-percent', …)` z 10 % i podstawą 9 000,00. `buildMonthPlanReading` z domyślnym `set_aside_default: null`. Istniejące pliki są już na listach strażników.

#### 4. Testy

**File**: `tests/Feature/MonthPlanPageTest.php`

**Intent**: Kontrakt propu.

**Contract**: `assertInertia`: `monthPlan.set_aside_default` = `{percent: 10, income_minor_units: …}` w propozycji z procentem; `null` bez procentu, w zatwierdzonym miesiącu i w miesiącu przeszłym.

### Success Criteria:

#### Automated Verification:

- Testy propu przechodzą na PostgreSQL: `artisan test --compact tests/Feature/MonthPlanPageTest.php tests/Feature/DevMonthPlanSinkTest.php tests/Unit/MonthPlanHardcodedUiTest.php`
- `npm run build`, `npm run types:check`, `npm run check:ui` i testy Vitest planu miesiąca przechodzą
- Pełna bramka przechodzi na PostgreSQL (`ci:check` w worktree, `testing_default_set_aside_percent`) plus `vendor/bin/sloppy ci` na hoście

#### Manual Verification:

- Propozycja z procentem 10 pokazuje „10 % planowanych przychodów (9 000,00) · Zmień w ustawieniach”, a link otwiera zakładkę „Plan miesiąca”
- Po poprawce przychodów w formularzu „odkładam” się nie zmienia, a podpowiedź pokazuje starą podstawę
- Zatwierdzony plan, tryb zmiany i propozycja bez procentu nie pokazują podpowiedzi; `/dev/month-plan` pokazuje nowy stan w trybie jasnym i ciemnym

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- Hardcoded UI (`SettingsHardcodedUiTest`, `MonthPlanHardcodedUiTest`) dla nowej strony i linii.

### Integration Tests:

- `MonthPlanProposalTest`: reguła procentu i jej granice (faza 3).
- `MonthPlanSettingsTest`: walidacja 0–100, puste pole i toast (faza 2).
- `UserSnapshotCommandTest`: v11 round-trip, v10 jako `null`, odrzucenia (faza 2).
- `MonthPlanPageTest`: `set_aside_minor_units` i `set_aside_default` przez `assertInertia` (fazy 3–4).

### Manual Testing Steps:

1. Ustaw 10 % w `/settings/month-plan` i otwórz propozycję miesiąca z przychodami 9 000: „odkładam” to 900, a pod polem jest podpowiedź z linkiem.
2. Wyczyść procent: propozycja wraca do kopiowania „odkładam” z poprzedniego miesiąca, a podpowiedź znika.
3. Zatwierdź propozycję z 900, potem zmień procent na 20: zatwierdzony miesiąc zostaje przy 900.

## Performance Considerations

Procent czyta się raz na `forBudget` z relacji `user` (dzięki `chaperone()` bez zapytania dla budżetów z `$user->budgets()`, także w pętli `EntryToSpendInputs`), a nie na miesiąc. Suma przychodów to przejście po już obliczonych `$amounts`.

## Migration Notes

Migracja dodaje jedną nullable kolumnę i nie zmienia żadnego wiersza, więc `app:control-numbers` przed i po `migrate` daje te same liczby. Po wdrożeniu procent jest `null` u wszystkich, więc propozycje się nie zmieniają, dopóki użytkownik nie ustawi procentu. Wycofanie: `scripts/artisan.sh migrate:rollback --force` nowym kodem (`down()` usuwa kolumnę i traci tylko zapisany procent), potem `git revert` i `scripts/deploy.sh`. Snapshot v11 nie wczyta się w kodzie sprzed zmiany, więc drogą powrotu jest `down()` (README „Wycofanie S-33”).

## Addendum (impl-review 2026-11-12)

- Reguła procentu siedzi w `app/Support/DefaultSetAside.php` (`plannedIncome()` i `amount()`, czyste funkcje bez zapytań), a `MonthPlanProposal::computePlan` buduje z nich `MonthPlanReading`, jak w gałęzi bez procentu (impl-review F3).
- Podpowiedź opisuje kwotę startową propozycji i pokazuje się tylko przy edytowalnym polu (`proposal` z `can_approve`, poza trybem zmiany). Propozycja, której nie da się zatwierdzić, pokazuje kwotę z procentu bez podpowiedzi, a po ręcznej zmianie „odkładam” podpowiedź zostaje ze starą podstawą. Oba zachowania są zamierzone, zgodnie z ADR-0081 (bez przeliczania na żywo) (impl-review F4).
- Trasy `month-plan-settings.*` stoją w grupie `auth` + `verified` obok Wyglądu, tak jak plan miesiąca; profil zostaje w samym `auth`. Dziś `verified` niczego nie blokuje, bo `User` nie implementuje `MustVerifyEmail`, więc to tylko spójność grup tras (impl-review F5).

## References

- Roadmapa: `context/foundation/roadmap.md` S-33; PRD: `context/foundation/prd-v3.md` FR-024 (`:220`), `:65`, `:150`, `:190`
- Reguła dziś: ADR-0033:5, `context/foundation/glossary.md:108-109`, `context/archive/2026-11-02-set-aside-and-draw/plan-brief.md:23`
- Kod: `app/Support/MonthPlanProposal.php:141-182`, `app/Support/MonthPlanReading.php`, `app/Http/Controllers/MonthPlanController.php:134-205`, `resources/js/components/MonthPlanTable.vue:357-418`
- Wzór ustawień: `app/Http/Controllers/Settings/ProfileController.php:20-44`, `resources/js/pages/settings/Profile.vue`, ADR-0049
- Snapshot: `.ai/rules/snapshot.md`, `app/Actions/BackupUserData.php:20,35`, `app/Actions/RestoreUserData.php:36-154`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Słownik i ADR-0081

#### Automated

- [x] 1.1 ADR-0081 istnieje, a słownik odwołuje się do niego: `grep -n "ADR-0081" context/foundation/glossary.md` — bf253bb

#### Manual

- [x] 1.2 Hasła Set-aside i Default Set-aside Percent zgadzają się z decyzjami planu (zastępuje kopiowanie, 0–100, w dół do złotego, puste = kopiowanie) — bf253bb

### Phase 2: Ustawienie procentu

#### Automated

- [x] 2.1 Testy fazy przechodzą na PostgreSQL (worktree, `DB_DATABASE=testing_default_set_aside_percent`): `artisan test --compact tests/Feature/Settings/MonthPlanSettingsTest.php tests/Feature/Migrations/DefaultSetAsidePercentMigrationTest.php tests/Feature/UserSnapshotCommandTest.php tests/Feature/ResetUserDataCommandTest.php tests/Feature/SettingsKitchenSinkTest.php tests/Unit/SettingsHardcodedUiTest.php` — c35bad7
- [x] 2.2 `npm run build` i `npm run types:check` przechodzą (nowa trasa Wayfinder) — c35bad7
- [x] 2.3 `npm run check:ui` przechodzi z nową stroną na liście — c35bad7
- [x] 2.4 `vendor/bin/pint --dirty --format agent` nie zostawia zmian, a `vendor/bin/sloppy ci` nie zgłasza nowych znalezisk — c35bad7

#### Manual

- [x] 2.5 W `/settings/month-plan` zapis 10 pokazuje toast i wartość po przeładowaniu; puste pole wraca jako puste; 101 pokazuje błąd pola — c35bad7
- [x] 2.6 `/dev/ui/settings` pokazuje nową zakładkę we wszystkich stanach w trybie jasnym i ciemnym — c35bad7

### Phase 3: Propozycja liczy procent

#### Automated

- [x] 3.1 Nowe testy w `MonthPlanProposalTest.php` są czerwone przed zmianą `MonthPlanProposal.php` i zielone po niej — a606ba0
- [x] 3.2 Testy planu przechodzą na PostgreSQL: `artisan test --compact tests/Feature/MonthPlanProposalTest.php tests/Feature/MonthPlanPageTest.php tests/Feature/ApproveMonthPlanTest.php tests/Feature/ChangeMonthPlanTest.php tests/Feature/MonthToSpendTest.php tests/Feature/EntryToSpendInputsTest.php` — a606ba0
- [x] 3.3 `vendor/bin/pint --dirty --format agent` nie zostawia zmian, a `vendor/bin/sloppy ci` nie zgłasza nowych znalezisk (brak zapytania w `computePlan`) — a606ba0

#### Manual

- [x] 3.4 Na bazie podglądu z procentem 10 propozycja bieżącego lub następnego miesiąca pokazuje „odkładam” równe 10 % planowanych przychodów w dół do złotego, a zatwierdzony miesiąc pokazuje kwotę bez zmian — a606ba0

### Phase 4: Podpowiedź w planie miesiąca

#### Automated

- [x] 4.1 Testy propu przechodzą na PostgreSQL: `artisan test --compact tests/Feature/MonthPlanPageTest.php tests/Feature/DevMonthPlanSinkTest.php tests/Unit/MonthPlanHardcodedUiTest.php` — c828422
- [x] 4.2 `npm run build`, `npm run types:check`, `npm run check:ui` i testy Vitest planu miesiąca przechodzą — c828422
- [x] 4.3 Pełna bramka przechodzi na PostgreSQL (`ci:check` w worktree, `testing_default_set_aside_percent`) plus `vendor/bin/sloppy ci` na hoście — c828422

#### Manual

- [x] 4.4 Propozycja z procentem 10 pokazuje „10 % planowanych przychodów (9 000,00) · Zmień w ustawieniach”, a link otwiera zakładkę „Plan miesiąca” — c828422
- [x] 4.5 Po poprawce przychodów w formularzu „odkładam” się nie zmienia, a podpowiedź pokazuje starą podstawę — c828422
- [x] 4.6 Zatwierdzony plan, tryb zmiany i propozycja bez procentu nie pokazują podpowiedzi; `/dev/month-plan` pokazuje nowy stan w trybie jasnym i ciemnym — c828422
